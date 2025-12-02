<?php
// includes/notificar_bot.php

if (!function_exists('notificarBotNovoAgendamento')) {
    /**
     * Notifica o BOT SECRETÁRIO sempre que um novo agendamento é criado.
     *
     * @param PDO $pdo
     * @param int $agendamentoId ID do agendamento recém-criado
     */
    function notificarBotNovoAgendamento(PDO $pdo, int $agendamentoId): void
    {
        try {
            // ====================================
            // DETECTAR AMBIENTE (LOCAL vs PRODUÇÃO)
            // ====================================
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $isProd = ($host === 'salao.develoi.com');

            // 🔗 URLs DO BOT WEBHOOK
            $WEBHOOK_LOCAL = 'http://localhost:3333/webhook/novo-agendamento';
            
            // ⚠️ TROCAR PELO IP/DOMÍNIO DA VPS ONDE O BOT ESTÁ RODANDO
            // Opção 1 - Com IP da VPS:
            // $WEBHOOK_PROD = 'http://123.456.789.10:3333/webhook/novo-agendamento';
            // 
            // Opção 2 - Com subdomínio apontando para VPS:
            // $WEBHOOK_PROD = 'http://bot.salao.develoi.com:3333/webhook/novo-agendamento';
            $WEBHOOK_PROD = 'http://bot.salao.develoi.com:3333/webhook/novo-agendamento';


            $webhookUrl = $isProd ? $WEBHOOK_PROD : $WEBHOOK_LOCAL;

            // ====================================
            // BUSCAR DADOS DO AGENDAMENTO
            // ====================================
            $sql = "
                SELECT 
                    a.id,
                    a.user_id,
                    a.cliente_id,
                    a.servico,
                    a.valor,
                    a.data_agendamento,
                    a.horario,
                    a.status,
                    a.observacoes,
                    
                    u.telefone      AS telefone_profissional,
                    u.nome          AS profissional_nome,
                    
                    c.nome          AS cliente_nome,
                    c.telefone      AS cliente_telefone
                FROM agendamentos a
                JOIN usuarios u ON u.id = a.user_id
                LEFT JOIN clientes c ON c.id = a.cliente_id
                WHERE a.id = :id
                LIMIT 1
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $agendamentoId]);
            $ag = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ag) {
                error_log("[BOT] Agendamento {$agendamentoId} não encontrado.");
                return;
            }

            // Se não tiver telefone do profissional, não tem pra quem mandar
            if (empty($ag['telefone_profissional'])) {
                error_log("[BOT] Profissional sem telefone cadastrado (user_id={$ag['user_id']}).");
                return;
            }

            // ====================================
            // MONTAR PAYLOAD PARA O BOT
            // ====================================
            $payload = [
                'telefone_profissional' => $ag['telefone_profissional'],
                'cliente_nome'          => $ag['cliente_nome'] ?? 'Cliente',
                'cliente_telefone'      => $ag['cliente_telefone'] ?? null,
                'servico'               => $ag['servico'] ?? 'Serviço',
                'data'                  => $ag['data_agendamento'] ?? null,
                'horario'               => $ag['horario'] ?? null,
                'valor'                 => $ag['valor'] ?? null,
                'observacoes'           => $ag['observacoes'] ?? null,
            ];

            // ====================================
            // ENVIAR REQUISIÇÃO HTTP PARA O BOT
            // ====================================
            $ch = curl_init($webhookUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_TIMEOUT        => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // ====================================
            // LOG PARA DEBUG
            // ====================================
            if ($curlError) {
                error_log("[BOT] Erro cURL ao notificar bot: {$curlError}");
            } else {
                error_log("[BOT] Webhook {$webhookUrl} HTTP {$httpCode} - Resp: {$response}");
            }

        } catch (Throwable $e) {
            error_log('[BOT] Exceção ao notificar bot: ' . $e->getMessage());
        }
    }
}

if (!function_exists('notificarBotAgendamentoConfirmado')) {
    /**
     * Notifica o BOT quando um agendamento é CONFIRMADO pelo profissional.
     * Envia mensagem diferenciada para o cliente.
     *
     * @param PDO $pdo
     * @param int $agendamentoId ID do agendamento confirmado
     */
    function notificarBotAgendamentoConfirmado(PDO $pdo, int $agendamentoId): void
    {
        try {
            // ====================================
            // DETECTAR AMBIENTE
            // ====================================
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $isProd = ($host === 'salao.develoi.com');

            $WEBHOOK_LOCAL = 'http://localhost:3333/webhook/agendamento-confirmado';
            $WEBHOOK_PROD = 'http://bot.salao.develoi.com:3333/webhook/agendamento-confirmado';

            $webhookUrl = $isProd ? $WEBHOOK_PROD : $WEBHOOK_LOCAL;

            // ====================================
            // BUSCAR DADOS DO AGENDAMENTO
            // ====================================
            $sql = "
                SELECT 
                    a.id,
                    a.user_id,
                    a.cliente_id,
                    a.servico,
                    a.valor,
                    a.data_agendamento,
                    a.horario,
                    a.status,
                    a.observacoes,
                    
                    u.telefone      AS telefone_profissional,
                    u.nome          AS profissional_nome,
                    u.estabelecimento AS estabelecimento,
                    
                    c.nome          AS cliente_nome,
                    c.telefone      AS cliente_telefone
                FROM agendamentos a
                JOIN usuarios u ON u.id = a.user_id
                LEFT JOIN clientes c ON c.id = a.cliente_id
                WHERE a.id = :id
                LIMIT 1
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $agendamentoId]);
            $ag = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ag) {
                error_log("[BOT] Agendamento confirmado {$agendamentoId} não encontrado.");
                return;
            }

            // Se não tiver telefone do cliente, não tem pra quem confirmar
            if (empty($ag['cliente_telefone'])) {
                error_log("[BOT] Cliente sem telefone cadastrado (agendamento {$agendamentoId}).");
                return;
            }

            // ====================================
            // MONTAR PAYLOAD PARA CONFIRMAÇÃO
            // ====================================
            $payload = [
                'telefone_cliente'      => $ag['cliente_telefone'],
                'cliente_nome'          => $ag['cliente_nome'] ?? 'Cliente',
                'profissional_nome'     => $ag['profissional_nome'] ?? 'Profissional',
                'estabelecimento'       => $ag['estabelecimento'] ?? 'Salão',
                'servico'               => $ag['servico'] ?? 'Serviço',
                'data'                  => $ag['data_agendamento'] ?? null,
                'horario'               => $ag['horario'] ?? null,
                'valor'                 => $ag['valor'] ?? null,
                'observacoes'           => $ag['observacoes'] ?? null,
            ];

            // ====================================
            // ENVIAR REQUISIÇÃO
            // ====================================
            $ch = curl_init($webhookUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_TIMEOUT        => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // ====================================
            // LOG
            // ====================================
            if ($curlError) {
                error_log("[BOT] Erro cURL ao confirmar agendamento: {$curlError}");
            } else {
                error_log("[BOT] Confirmação enviada - HTTP {$httpCode} - Resp: {$response}");
            }

        } catch (Throwable $e) {
            error_log('[BOT] Exceção ao confirmar agendamento: ' . $e->getMessage());
        }
    }
}
