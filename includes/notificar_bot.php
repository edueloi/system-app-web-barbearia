<?php
// includes/notificar_bot.php

/**
 * Retorna a URL base do BOT (local ou produção).
 */
if (!function_exists('getBotBaseUrl')) {
    function getBotBaseUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isLocalDev = in_array($host, ['localhost', '127.0.0.1']);

        // Quando estiver desenvolvendo TUDO na mesma máquina (PHP + bot)
        if ($isLocalDev) {
            // Bot rodando localmente na porta 3333
            return 'http://localhost:3333';
        }

        // EM PRODUÇÃO (HostGator chamando o bot na VPS)
        // Bot está escutando na porta 80 (sem :3333)
        // No futuro, quando o subdomínio estiver pronto, pode usar:
        // return 'http://bot.develoi.com';
        return 'http://72.61.221.59';
    }
}

/**
 * Notifica o BOT SECRETÁRIO sempre que um novo agendamento é criado.
 *
 * @param PDO $pdo
 * @param int $agendamentoId ID do agendamento recém-criado
 */
if (!function_exists('notificarBotNovoAgendamento')) {
    function notificarBotNovoAgendamento(PDO $pdo, int $agendamentoId): void
    {
        try {
            // ====================================
            // URL DO WEBHOOK
            // ====================================
            $baseUrl    = getBotBaseUrl();
            $webhookUrl = rtrim($baseUrl, '/') . '/webhook/novo-agendamento';

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

            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // ====================================
            // LOG PARA DEBUG
            // ====================================
            if ($curlError) {
                error_log("[BOT] Erro cURL ao notificar bot (novo agendamento): {$curlError}");
            } else {
                error_log("[BOT] Novo agendamento -> {$webhookUrl} HTTP {$httpCode} - Resp: {$response}");
            }

        } catch (Throwable $e) {
            error_log('[BOT] Exceção ao notificar bot (novo agendamento): ' . $e->getMessage());
        }
    }
}

/**
 * Notifica o BOT quando um agendamento é CONFIRMADO pelo profissional.
 * Envia mensagem diferenciada para o cliente.
 *
 * @param PDO $pdo
 * @param int $agendamentoId ID do agendamento confirmado
 */
if (!function_exists('notificarBotAgendamentoConfirmado')) {
    function notificarBotAgendamentoConfirmado(PDO $pdo, int $agendamentoId): void
    {
        try {
            // ====================================
            // URL DO WEBHOOK
            // ====================================
            $baseUrl    = getBotBaseUrl();
            $webhookUrl = rtrim($baseUrl, '/') . '/webhook/agendamento-confirmado';

            // ====================================
            // BUSCAR DADOS DO AGENDAMENTO
            // ====================================
            $sql = "
                SELECT 
                    a.id,
                    a.user_id,
                    a.cliente_id,
                    a.cliente_nome      AS ag_cliente_nome,   -- nome salvo no agendamento
                    a.servico,
                    a.valor,
                    a.data_agendamento,
                    a.horario,
                    a.status,
                    a.observacoes,
                    
                    u.telefone          AS telefone_profissional,
                    u.nome              AS profissional_nome,
                    u.estabelecimento   AS estabelecimento,
                    
                    c.nome              AS cliente_nome,
                    c.telefone          AS cliente_telefone
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

            // ================================
            // RESOLVE TELEFONE DO CLIENTE
            // ================================
            $telefoneCliente = $ag['cliente_telefone'] ?? null;

            // Se não veio telefone pelo JOIN, tenta achar pelo nome do agendamento
            if (empty($telefoneCliente) && !empty($ag['ag_cliente_nome'])) {
                error_log("[BOT-DEBUG] Agendamento {$agendamentoId} sem telefone pelo JOIN. Tentando fallback por nome: '{$ag['ag_cliente_nome']}'.");

                $stmtCli = $pdo->prepare("
                    SELECT id, telefone 
                    FROM clientes 
                    WHERE user_id = :user_id 
                      AND nome = :nome
                    LIMIT 1
                ");
                $stmtCli->execute([
                    ':user_id' => $ag['user_id'],
                    ':nome'    => $ag['ag_cliente_nome']
                ]);
                $cli = $stmtCli->fetch(PDO::FETCH_ASSOC);

                if ($cli && !empty($cli['telefone'])) {
                    $telefoneCliente = $cli['telefone'];

                    // Se o agendamento ainda não tinha cliente_id, aproveita e corrige
                    if (empty($ag['cliente_id'])) {
                        try {
                            $upd = $pdo->prepare("UPDATE agendamentos SET cliente_id = :cid WHERE id = :id");
                            $upd->execute([
                                ':cid' => $cli['id'],
                                ':id'  => $agendamentoId
                            ]);
                            error_log("[BOT-DEBUG] Vinculado cliente_id={$cli['id']} ao agendamento {$agendamentoId} via nome.");
                        } catch (Throwable $e) {
                            error_log("[BOT-DEBUG] Falha ao atualizar cliente_id do agendamento {$agendamentoId}: " . $e->getMessage());
                        }
                    }

                    error_log("[BOT-DEBUG] Fallback por nome funcionou para o agendamento {$agendamentoId}.");
                } else {
                    error_log("[BOT-DEBUG] Nenhum cliente encontrado por nome '{$ag['ag_cliente_nome']}' para user_id={$ag['user_id']}.");
                }
            }

            // Se ainda assim não tiver telefone -> aborta
            if (empty($telefoneCliente)) {
                error_log("[BOT] Cliente sem telefone cadastrado (agendamento {$agendamentoId}). "
                    . "ag_cliente_nome='{$ag['ag_cliente_nome']}', cliente_id=" . ($ag['cliente_id'] ?? 'null'));
                return;
            }

            // ====================================
            // MONTAR PAYLOAD PARA CONFIRMAÇÃO
            // ====================================
            $payload = [
                'telefone_cliente'  => $telefoneCliente,
                'cliente_nome'      => $ag['ag_cliente_nome'] ?? $ag['cliente_nome'] ?? 'Cliente',
                'profissional_nome' => $ag['profissional_nome'] ?? 'Profissional',
                'estabelecimento'   => $ag['estabelecimento'] ?? 'Salão',
                'servico'           => $ag['servico'] ?? 'Serviço',
                'data'              => $ag['data_agendamento'] ?? null,
                'horario'           => $ag['horario'] ?? null,
                'valor'             => $ag['valor'] ?? null,
                'observacoes'       => $ag['observacoes'] ?? null,
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

            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // ====================================
            // LOG
            // ====================================
            if ($curlError) {
                error_log("[BOT] Erro cURL ao confirmar agendamento: {$curlError}");
            } else {
                error_log("[BOT] Confirmação enviada -> {$webhookUrl} HTTP {$httpCode} - Resp: {$response}");
            }

        } catch (Throwable $e) {
            error_log('[BOT] Exceção ao confirmar agendamento: ' . $e->getMessage());
        }
    }
}


if (!function_exists('notificarBotLembreteAgendamento')) {
    /**
     * Notifica o BOT para enviar lembrete ao cliente E ao profissional
     * sobre agendamento próximo (ex: 1 hora antes).
     *
     * @param PDO $pdo
     * @param int $agendamentoId ID do agendamento
     * @param int $minutosAntes Quantos minutos antes do horário (padrão: 60)
     */
    function notificarBotLembreteAgendamento(PDO $pdo, int $agendamentoId, int $minutosAntes = 60): void
    {
        try {
            // ====================================
            // URL DO WEBHOOK
            // ====================================
            $baseUrl = getBotBaseUrl();
            $webhookUrl = rtrim($baseUrl, '/') . '/webhook/lembrete-agendamento';

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
                    a.lembrete_enviado,
                    
                    u.telefone      AS telefone_profissional,
                    u.nome          AS profissional_nome,
                    u.estabelecimento,
                    
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
                error_log("[BOT] Lembrete: Agendamento {$agendamentoId} não encontrado.");
                return;
            }

            // Verificar se já foi enviado
            if (!empty($ag['lembrete_enviado']) && $ag['lembrete_enviado'] == 1) {
                error_log("[BOT] Lembrete já enviado para agendamento {$agendamentoId}.");
                return;
            }

            // Verificar se tem telefones
            if (empty($ag['telefone_profissional']) && empty($ag['cliente_telefone'])) {
                error_log("[BOT] Lembrete: Sem telefones cadastrados para agendamento {$agendamentoId}.");
                return;
            }

            // ====================================
            // CALCULAR TEMPO ATÉ O AGENDAMENTO
            // ====================================
            $dataHoraAgendamento = $ag['data_agendamento'] . ' ' . $ag['horario'];
            $timestampAgendamento = strtotime($dataHoraAgendamento);
            $timestampAtual = time();
            $minutosRestantes = floor(($timestampAgendamento - $timestampAtual) / 60);

            // ====================================
            // MONTAR PAYLOAD
            // ====================================
            $payload = [
                'agendamento_id'        => $ag['id'],
                'telefone_profissional' => $ag['telefone_profissional'] ?? null,
                'telefone_cliente'      => $ag['cliente_telefone'] ?? null,
                'cliente_nome'          => $ag['cliente_nome'] ?? 'Cliente',
                'profissional_nome'     => $ag['profissional_nome'] ?? 'Profissional',
                'estabelecimento'       => $ag['estabelecimento'] ?? 'Salão',
                'servico'               => $ag['servico'] ?? 'Serviço',
                'data'                  => $ag['data_agendamento'] ?? null,
                'horario'               => $ag['horario'] ?? null,
                'valor'                 => $ag['valor'] ?? null,
                'observacoes'           => $ag['observacoes'] ?? null,
                'minutos_restantes'     => $minutosRestantes,
                'minutos_antes_configurado' => $minutosAntes,
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
            // MARCAR COMO ENVIADO
            // ====================================
            if ($httpCode >= 200 && $httpCode < 300) {
                $stmtUpdate = $pdo->prepare("UPDATE agendamentos SET lembrete_enviado = 1 WHERE id = ?");
                $stmtUpdate->execute([$agendamentoId]);
                error_log("[BOT] Lembrete enviado com sucesso para agendamento {$agendamentoId}");
            } else {
                error_log("[BOT] Erro ao enviar lembrete - HTTP {$httpCode} - Resp: {$response}");
            }

            // ====================================
            // LOG
            // ====================================
            if ($curlError) {
                error_log("[BOT] Erro cURL ao enviar lembrete: {$curlError}");
            }

        } catch (Throwable $e) {
            error_log('[BOT] Exceção ao enviar lembrete: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/mailer.php';

if (!function_exists('processarLembretesAutomaticos')) {
    /**
     * Processa todos os agendamentos que precisam de lembrete automático.
     * DEVE SER EXECUTADO POR UM CRON JOB A CADA 5-10 MINUTOS.
     *
     * @param PDO $pdo
     * @param int $minutosAntes Tempo de antecedência para enviar lembrete (padrão: 60)
     * @return int Número de lembretes enviados
     */
    function processarLembretesAutomaticos(PDO $pdo, int $minutosAntes = 60): int
    {
        try {
            error_log("[BOT] Processando lembretes automáticos ({$minutosAntes} minutos antes)...");

            // ====================================
            // BUSCAR AGENDAMENTOS QUE PRECISAM DE LEMBRETE
            // ====================================

            $sql = "
                SELECT 
                    a.id,
                    a.user_id,
                    a.data_agendamento,
                    a.horario,
                    CAST((julianday(a.data_agendamento || ' ' || a.horario) - julianday('now', 'localtime')) * 24 * 60 AS INTEGER) AS minutos_ate_agendamento
                FROM agendamentos a
                WHERE a.status IN ('Confirmado', 'Pendente')
                  AND (a.lembrete_enviado IS NULL OR a.lembrete_enviado = 0)
                  AND datetime(a.data_agendamento || ' ' || a.horario) > datetime('now', 'localtime')
                  AND datetime(a.data_agendamento || ' ' || a.horario) <= datetime('now', 'localtime', '+{$minutosAntes} minutes')
                ORDER BY a.data_agendamento ASC, a.horario ASC
            ";

            $stmt = $pdo->query($sql);
            $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalEnviados = 0;

            foreach ($agendamentos as $ag) {
                // Envia WhatsApp/bot
                notificarBotLembreteAgendamento($pdo, $ag['id'], $minutosAntes);

                // Busca config de lembrete por e-mail do dono
                $userStmt = $pdo->prepare("SELECT nome, email, lembrete_email_ativo, lembrete_email_tempo, lembrete_email_unidade FROM usuarios WHERE id = ? LIMIT 1");
                $userStmt->execute([$ag['user_id']]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);

                if ($user && !empty($user['email']) && intval($user['lembrete_email_ativo']) === 1) {
                    // Calcula diferença de tempo para disparo
                    $unidade = $user['lembrete_email_unidade'] ?? 'horas';
                    $tempo = intval($user['lembrete_email_tempo'] ?? 4);
                    $dataHoraAgendamento = $ag['data_agendamento'] . ' ' . $ag['horario'];
                    $timestampAgendamento = strtotime($dataHoraAgendamento);
                    $timestampAtual = time();
                    $minutosRestantes = floor(($timestampAgendamento - $timestampAtual) / 60);

                    $disparar = false;
                    if ($unidade === 'horas') {
                        if ($minutosRestantes <= ($tempo * 60) && $minutosRestantes > ($tempo * 60 - 10)) {
                            $disparar = true;
                        }
                    } else { // minutos
                        if ($minutosRestantes <= $tempo && $minutosRestantes > $tempo - 10) {
                            $disparar = true;
                        }
                    }

                    if ($disparar) {
                        // Busca dados do agendamento para o e-mail
                        $agStmt = $pdo->prepare("SELECT a.*, c.nome as cliente_nome, c.telefone as cliente_telefone FROM agendamentos a LEFT JOIN clientes c ON c.id = a.cliente_id WHERE a.id = ? LIMIT 1");
                        $agStmt->execute([$ag['id']]);
                        $agDet = $agStmt->fetch(PDO::FETCH_ASSOC);

                        $subject = 'Lembrete de agendamento próximo';
                        $body = '<p>Olá ' . htmlspecialchars($user['nome']) . ',</p>' .
                                '<p>Você tem um agendamento próximo:</p>' .
                                '<ul>' .
                                '<li><b>Cliente:</b> ' . htmlspecialchars($agDet['cliente_nome'] ?? '-') . '</li>' .
                                '<li><b>Serviço:</b> ' . htmlspecialchars($agDet['servico'] ?? '-') . '</li>' .
                                '<li><b>Data:</b> ' . htmlspecialchars($agDet['data_agendamento'] ?? '-') . '</li>' .
                                '<li><b>Horário:</b> ' . htmlspecialchars($agDet['horario'] ?? '-') . '</li>' .
                                '<li><b>Valor:</b> R$ ' . number_format($agDet['valor'] ?? 0, 2, ',', '.') . '</li>' .
                                '</ul>' .
                                '<p>Faltam <b>' . $minutosRestantes . ' minutos</b> para o atendimento.</p>';

                        sendMailDeveloi($user['email'], $user['nome'], $subject, $body);
                    }
                }

                $totalEnviados++;
                // Pausa de 1 segundo entre envios para não sobrecarregar
                sleep(1);
            }

            error_log("[BOT] Processamento concluído: {$totalEnviados} lembrete(s) enviado(s).");
            return $totalEnviados;

        } catch (Throwable $e) {
            error_log('[BOT] Exceção ao processar lembretes automáticos: ' . $e->getMessage());
            return 0;
        }
    }
}
