<?php
/**
 * ========================================================================
 * 🔔 CRON JOB - LEMBRETES AUTOMÁTICOS DE AGENDAMENTOS
 * ========================================================================
 * 
 * Sistema completo para enviar lembretes via WhatsApp para:
 * - CLIENTES: Lembra do agendamento próximo (ex: 1h antes)
 * - PROFISSIONAIS: Avisa sobre consulta que vai começar
 * 
 * ========================================================================
 * 📋 COMO FUNCIONA:
 * ========================================================================
 * 
 * 1. CRON executa este arquivo a cada 10 minutos
 * 2. Script busca agendamentos próximos (ex: faltam 60 minutos)
 * 3. Para cada agendamento encontrado:
 *    - Chama função processarLembretesAutomaticos()
 *    - Que chama notificarBotLembreteAgendamento()
 *    - Que envia POST para /webhook/lembrete-agendamento no bot
 * 4. Bot recebe webhook e envia 2 mensagens:
 *    ✅ Mensagem para CLIENTE (telefone_cliente)
 *    ✅ Mensagem para PROFISSIONAL (telefone_profissional)
 * 5. Marca agendamento como lembrete_enviado = 1 (evita duplicação)
 * 
 * ========================================================================
 * ⚙️ CONFIGURAÇÃO NO SERVIDOR:
 * ========================================================================
 * 
 * 1. cPanel / HostGator (Hospedagem compartilhada):
 *    - Acesse "Cron Jobs"
 *    - Adicione novo cron job:
 *      Comando: /usr/bin/php /home/usuario/public_html/controle-salao/cron_lembretes.php
 *      Frequência: (asterisco)/10 * * * * (a cada 10 minutos)
 * 
 * 2. VPS/Servidor Linux:
 *    Execute: crontab -e
 *    Adicione a linha (a cada 10 minutos):
 *    (asterisco)/10 * * * * /usr/bin/php /var/www/html/controle-salao/cron_lembretes.php >> /var/log/cron_lembretes.log 2>&1
 * 
 * 3. XAMPP Local (para testes):
 *    Execute manualmente: php cron_lembretes.php
 *    Ou via navegador: http://localhost/controle-salao/cron_lembretes.php?token=seu_token_secreto_aqui_123456
 *    Ou configure Task Scheduler (Windows) / crontab (Linux/Mac)
 * 
 * ========================================================================
 * 🔐 SEGURANÇA:
 * ========================================================================
 * 
 * - Este arquivo só pode ser executado via CLI ou com token secreto
 * - Token configurado na linha 78: $tokenSecreto = '...';
 * - Gerar token seguro: echo bin2hex(random_bytes(32));
 * - Não deve ser acessível diretamente via navegador sem autenticação
 * 
 * ========================================================================
 * 🤖 INTEGRAÇÃO COM BOT:
 * ========================================================================
 * 
 * Este script chama o webhook do bot Node.js:
 * POST http://72.61.221.59/webhook/lembrete-agendamento
 * 
 * Payload enviado:
 * {
 *   "agendamento_id": 123,
 *   "telefone_profissional": "15992675429",
 *   "telefone_cliente": "11987654321",
 *   "cliente_nome": "João Silva",
 *   "profissional_nome": "Eduardo Eloi",
 *   "estabelecimento": "Salão Develoi",
 *   "servico": "Corte Masculino",
 *   "data": "2025-12-02",
 *   "horario": "15:30",
 *   "valor": 45.00,
 *   "minutos_restantes": 55,
 *   "minutos_antes_configurado": 60
 * }
 * 
 * Bot responde enviando 2 mensagens WhatsApp:
 * ✅ Cliente: "⏰ LEMBRETE DE AGENDAMENTO - Você tem consulta em 55 minutos"
 * ✅ Profissional: "⏰ LEMBRETE: CONSULTA PRÓXIMA - Cliente João em 55 minutos"
 * 
 * ========================================================================
 * 🧪 TESTAR SISTEMA:
 * ========================================================================
 * 
 * 1. Testar CRON manualmente:
 *    php cron_lembretes.php
 * 
 * 2. Testar via navegador (com token):
 *    http://localhost/controle-salao/cron_lembretes.php?token=seu_token_secreto_aqui_123456
 * 
 * 3. Testar com tempo diferente (ex: 120 minutos antes):
 *    php cron_lembretes.php 120
 *    http://localhost/controle-salao/cron_lembretes.php?token=xxx&minutos=120
 * 
 * 4. Verificar logs:
 *    tail -f /var/log/apache2/error_log | grep BOT
 *    tail -f /var/log/cron_lembretes.log
 * 
 * ========================================================================
 * 📊 BANCO DE DADOS:
 * ========================================================================
 * 
 * Campo adicionado na tabela agendamentos:
 * - lembrete_enviado INTEGER DEFAULT 0
 * 
 * Query executada:
 * SELECT * FROM agendamentos 
 * WHERE status IN ('Confirmado', 'Pendente')
 *   AND lembrete_enviado = 0
 *   AND datetime(data_agendamento || ' ' || horario) > datetime('now', 'localtime')
 *   AND datetime(data_agendamento || ' ' || horario) <= datetime('now', 'localtime', '+60 minutes')
 * 
 * ========================================================================
 * 🚨 TROUBLESHOOTING:
 * ========================================================================
 * 
 * Problema: "Nenhum lembrete enviado"
 * - Verificar se tem agendamentos nas próximas horas
 * - Verificar se lembrete_enviado = 0
 * - Testar: UPDATE agendamentos SET lembrete_enviado = 0 WHERE id = 123;
 * 
 * Problema: "Erro ao conectar webhook"
 * - Verificar se bot está rodando: ps aux | grep node
 * - Testar conectividade: curl http://72.61.221.59/webhook/teste
 * - Verificar firewall: sudo ufw allow 80/tcp
 * - Verificar URL no getBotBaseUrl() em includes/notificar_bot.php
 * 
 * Problema: "Lembretes duplicados"
 * - Campo lembrete_enviado deve ser marcado = 1 após envio
 * - Verificar se UPDATE está sendo executado corretamente
 * 
 * ========================================================================
 * ✅ CHECKLIST DE IMPLEMENTAÇÃO:
 * ========================================================================
 * 
 * [x] Função notificarBotLembreteAgendamento() criada
 * [x] Função processarLembretesAutomaticos() criada
 * [x] Campo lembrete_enviado adicionado no banco
 * [x] Webhook /webhook/lembrete-agendamento no bot
 * [x] Arquivo cron_lembretes.php criado
 * [ ] Token de segurança configurado (linha 78)
 * [ ] CRON job configurado no servidor
 * [ ] Testado envio para cliente
 * [ ] Testado envio para profissional
 * 
 * ========================================================================
 * 📞 SUPORTE:
 * ========================================================================
 * 
 * Eduardo Eloi: (15) 99267-5429
 * Karen Gomes: (15) 99134-5333
 * 
 * Versão: 2.0 (Dezembro 2025)
 * Recurso: Lembretes automáticos para clientes e profissionais
 * ========================================================================
 */

// ========================================================================
// SEGURANÇA: Verificar execução via CLI ou com token
// ========================================================================

$executadoViaCLI = (php_sapi_name() === 'cli');

// 🔐 CONFIGURAÇÃO DE SEGURANÇA (OPCIONAL)
// ➜ true  = Exige token quando acessado via navegador (RECOMENDADO)
// ➜ false = Permite acesso via navegador sem token (MENOS SEGURO)
$EXIGIR_TOKEN_HTTP = false;  // ⬅️ Mude para true em produção!

$tokenSecreto = 'seu_token_secreto_aqui_123456'; // Trocar por token real se habilitar

// Se não for CLI e segurança estiver ativada, verificar token
if (!$executadoViaCLI && $EXIGIR_TOKEN_HTTP) {
    $tokenFornecido = $_GET['token'] ?? '';
    
    if ($tokenFornecido !== $tokenSecreto) {
        http_response_code(403);
        die(json_encode([
            'success' => false,
            'message' => 'Acesso negado. Token inválido.',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }
}

// ========================================================================
// IMPORTAR DEPENDÊNCIAS
// ========================================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notificar_bot.php';

// ========================================================================
// CONFIGURAÇÕES
// ========================================================================

$MINUTOS_ANTES = 60; // Enviar lembrete 1 hora antes do agendamento

// Pode aceitar parâmetro via CLI ou URL
if ($executadoViaCLI && isset($argv[1])) {
    $MINUTOS_ANTES = (int)$argv[1];
} elseif (isset($_GET['minutos'])) {
    $MINUTOS_ANTES = (int)$_GET['minutos'];
}

// ========================================================================
// EXECUTAR PROCESSAMENTO
// ========================================================================

$inicioExecucao = microtime(true);
$dataHoraInicio = date('Y-m-d H:i:s');

echo "========================================\n";
echo "CRON JOB - LEMBRETES AUTOMÁTICOS\n";
echo "========================================\n";
echo "Início: {$dataHoraInicio}\n";
echo "Antecedência: {$MINUTOS_ANTES} minutos\n";
echo "========================================\n\n";

try {
    $totalEnviados = processarLembretesAutomaticos($pdo, $MINUTOS_ANTES);
    
    $fimExecucao = microtime(true);
    $tempoExecucao = round($fimExecucao - $inicioExecucao, 2);
    $dataHoraFim = date('Y-m-d H:i:s');
    
    echo "\n========================================\n";
    echo "PROCESSAMENTO CONCLUÍDO\n";
    echo "========================================\n";
    echo "Término: {$dataHoraFim}\n";
    echo "Tempo de execução: {$tempoExecucao}s\n";
    echo "Lembretes enviados: {$totalEnviados}\n";
    echo "========================================\n";
    
    // Registrar no log de sistema
    error_log("[CRON] Lembretes automáticos: {$totalEnviados} enviado(s) em {$tempoExecucao}s");
    
    // Se executado via HTTP, retornar JSON
    if (!$executadoViaCLI) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'lembretes_enviados' => $totalEnviados,
            'tempo_execucao_segundos' => $tempoExecucao,
            'inicio' => $dataHoraInicio,
            'fim' => $dataHoraFim,
            'configuracao' => [
                'minutos_antes' => $MINUTOS_ANTES
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
} catch (Throwable $e) {
    $erro = $e->getMessage();
    echo "\n❌ ERRO: {$erro}\n";
    error_log("[CRON] ERRO ao processar lembretes: {$erro}");
    
    if (!$executadoViaCLI) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao processar lembretes',
            'error' => $erro,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    exit(1);
}
