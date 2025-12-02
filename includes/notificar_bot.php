<?php
// includes/notificar_bot.php

/**
 * Notifica o BOT SECRETÁRIO sempre que um novo agendamento é criado.
 *
 * @param PDO $pdo
 * @param int $agendamentoId ID do agendamento recém-criado
 */
function notificarBotNovoAgendamento(PDO $pdo, int $agendamentoId): void
{
    try {
        // 1) Buscar dados do agendamento + profissional + cliente
        $sql = "
            SELECT 
                a.id,
                a.user_id,
                a.cliente_id,
                a.servico,
                a.valor,
                a.data_agendamento,
                a.horario,
                a.observacoes,
                
                u.telefone AS telefone_profissional,
                
                c.nome     AS cliente_nome,
                c.telefone AS cliente_telefone
                
            FROM agendamentos a
            JOIN usuarios u ON u.id = a.user_id
            LEFT JOIN clientes c ON c.id = a.cliente_id
            WHERE a.id = ?
            LIMIT 1
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$agendamentoId]);
        $ag = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ag) {
            error_log("notificarBotNovoAgendamento: agendamento {$agendamentoId} não encontrado.");
            return;
        }

        if (empty($ag['telefone_profissional'])) {
            // Sem telefone do profissional, não tem pra quem mandar
            error_log("notificarBotNovoAgendamento: usuário {$ag['user_id']} sem telefone cadastrado.");
            return;
        }

        // 2) Ajustar formatos de data/horário (opcional, só pra ficar bonito)
        $dataBr = $ag['data_agendamento']
            ? date('d/m/Y', strtotime($ag['data_agendamento']))
            : '';

        $hora = $ag['horario'];
        if ($hora && strlen($hora) >= 5) {
            $hora = substr($hora, 0, 5); // 08:45
        }

        // 3) Montar payload exatamente como o bot espera
        $payload = [
            'telefone_profissional' => $ag['telefone_profissional'],   // ex: 15992675429
            'cliente_nome'          => $ag['cliente_nome'] ?? 'Cliente',
            'cliente_telefone'      => $ag['cliente_telefone'] ?? null,
            'servico'               => $ag['servico'] ?? '',
            'data'                  => $dataBr,
            'horario'               => $hora,
            'valor'                 => $ag['valor'] ?? null,
            'observacoes'           => $ag['observacoes'] ?? null,
        ];

        // 4) URL do BOT (ajuste se o Node não estiver no mesmo servidor)
        $url = 'http://localhost:3333/webhook/novo-agendamento';
        // se o bot estiver em outro servidor:
        // $url = 'http://IP_DO_SERVIDOR_NODE:3333/webhook/novo-agendamento';

        // 5) Enviar requisição HTTP para o bot
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 5,
        ]);

        $res      = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res === false) {
            error_log('notificarBotNovoAgendamento: erro ao chamar bot - ' . $curlErr);
        } else {
            error_log("notificarBotNovoAgendamento: HTTP {$httpCode} resposta bot: {$res}");
        }

    } catch (Throwable $e) {
        error_log('notificarBotNovoAgendamento: exceção - ' . $e->getMessage());
    }
}
