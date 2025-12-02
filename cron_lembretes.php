<?php
/**
 * ========================================================================
 * CRON JOB - LEMBRETES AUTOMÁTICOS DE AGENDAMENTOS
 * ========================================================================
 * 
 * Este script deve ser executado periodicamente via CRON JOB.
 * 
 * CONFIGURAÇÃO NO SERVIDOR:
 * 
 * 1. cPanel / Hospedagens compartilhadas:
 *    - Acesse "Cron Jobs"
 *    - Adicione novo cron job:
 *      Comando: /usr/bin/php /home/usuario/public_html/cron_lembretes.php
 *      Frequência: A cada 10 minutos
 * 
 * 2. VPS/Servidor Linux:
 *    Execute: crontab -e
 *    Adicione a linha (a cada 10 minutos):
 *    (asterisco)/10 * * * * /usr/bin/php /var/www/html/controle-salao/cron_lembretes.php
 * 
 * 3. XAMPP Local (para testes):
 *    Execute manualmente: php cron_lembretes.php
 *    Ou configure Task Scheduler (Windows) / crontab (Linux/Mac)
 * 
 * SEGURANÇA:
 * - Este arquivo só pode ser executado via CLI ou com token secreto
 * - Não deve ser acessível diretamente via navegador sem autenticação
 * ========================================================================
 */

// ========================================================================
// SEGURANÇA: Verificar execução via CLI ou com token
// ========================================================================

$executadoViaCLI = (php_sapi_name() === 'cli');
$tokenSecreto = 'seu_token_secreto_aqui_123456'; // 🔐 TROCAR POR TOKEN REAL

// Se não for CLI, verificar token na URL
if (!$executadoViaCLI) {
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
