<?php

require_once __DIR__ . '/config.php';

$pushConfigPath = __DIR__ . '/push_config.php';
if (file_exists($pushConfigPath)) {
    require_once $pushConfigPath;
}

if (!function_exists('getOneSignalConfig')) {
    function getOneSignalConfig(): array
    {
        $appId = getenv('ONESIGNAL_APP_ID');
        if ($appId === false || $appId === '') {
            $appId = defined('ONESIGNAL_APP_ID') ? ONESIGNAL_APP_ID : '';
        }

        $restKey = getenv('ONESIGNAL_REST_API_KEY');
        if ($restKey === false || $restKey === '') {
            $restKey = defined('ONESIGNAL_REST_API_KEY') ? ONESIGNAL_REST_API_KEY : '';
        }

        return [
            'app_id' => trim((string)$appId),
            'rest_key' => trim((string)$restKey),
        ];
    }
}

if (!function_exists('oneSignalEnabled')) {
    function oneSignalEnabled(): bool
    {
        $cfg = getOneSignalConfig();
        return $cfg['app_id'] !== '' && $cfg['rest_key'] !== '';
    }
}

if (!function_exists('getAbsoluteUrl')) {
    function getAbsoluteUrl(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $base = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';
        if ($base !== '') {
            $parts = parse_url($base);
            $scheme = $parts['scheme'] ?? 'http';
            $host = $parts['host'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $port = isset($parts['port']) ? ':' . $parts['port'] : '';
            return $scheme . '://' . $host . $port . $path;
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $host . $path;
    }
}

if (!function_exists('sendOneSignalToUser')) {
    function sendOneSignalToUser(int $userId, array $payload): bool
    {
        if (!oneSignalEnabled()) {
            return false;
        }

        $cfg = getOneSignalConfig();
        $body = array_merge([
            'app_id' => $cfg['app_id'],
            'include_external_user_ids' => [(string)$userId],
        ], $payload);

        $ch = curl_init('https://onesignal.com/api/v1/notifications');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Basic ' . $cfg['rest_key'],
            ],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('[PUSH] OneSignal cURL error: ' . $curlError);
            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('[PUSH] OneSignal error HTTP ' . $httpCode . ' - Resp: ' . $response);
            return false;
        }

        return true;
    }
}

if (!function_exists('sendPushNovoAgendamento')) {
    function sendPushNovoAgendamento(int $userId, string $message, string $linkPath, int $agendamentoId): bool
    {
        $payload = [
            'headings' => ['pt' => 'Novo agendamento'],
            'contents' => ['pt' => $message],
            'url' => getAbsoluteUrl($linkPath),
            'data' => [
                'tag' => 'novo-agendamento-' . $agendamentoId,
            ],
        ];

        return sendOneSignalToUser($userId, $payload);
    }
}

if (!function_exists('sendPushLembreteAgendamento')) {
    function sendPushLembreteAgendamento(PDO $pdo, int $agendamentoId, int $minutosAntes = 60): bool
    {
        $sql = "
            SELECT 
                a.id,
                a.user_id,
                a.servico,
                a.data_agendamento,
                a.horario,
                c.nome AS cliente_nome
            FROM agendamentos a
            LEFT JOIN clientes c ON c.id = a.cliente_id
            WHERE a.id = ?
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$agendamentoId]);
        $ag = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ag) {
            return false;
        }

        $data = $ag['data_agendamento'] ?? '';
        $hora = $ag['horario'] ?? '';
        $cliente = $ag['cliente_nome'] ?? 'Cliente';
        $servico = $ag['servico'] ?? 'Servico';

        $agendaPath = (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com')
            ? '/agenda'
            : '/karen_site/controle-salao/pages/agenda/agenda.php';
        $agendaPath .= '?view=day&data=' . urlencode($data) . '&focus_id=' . urlencode($agendamentoId);

        $payload = [
            'headings' => ['pt' => 'Agendamento proximo'],
            'contents' => ['pt' => "Lembrete: {$cliente} - {$servico} em {$data} as {$hora} (faltam {$minutosAntes} min)."],
            'url' => getAbsoluteUrl($agendaPath),
            'data' => [
                'tag' => 'lembrete-agendamento-' . $agendamentoId,
            ],
        ];

        return sendOneSignalToUser((int)$ag['user_id'], $payload);
    }
}
