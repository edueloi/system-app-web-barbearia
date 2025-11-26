<?php
// pages/agenda/nota.php
// Gera uma "nota" simples em PDF para um agendamento

require_once '../../vendor/autoload.php'; // Dompdf
include '../../includes/db.php';

use Dompdf\Dompdf;

if (!isset($_GET['id'])) {
    die('Agendamento não especificado.');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT a.*, c.email AS cliente_email
      FROM agendamentos a
 LEFT JOIN clientes c 
        ON a.cliente_nome = c.nome 
       AND a.user_id = c.user_id
     WHERE a.id = ?
");
$stmt->execute([$id]);
$ag = $stmt->fetch();

if (!$ag) {
    die('Agendamento não encontrado.');
}

// Busca valor atualizado do serviço
$stmtValor = $pdo->prepare("SELECT preco FROM servicos WHERE nome = ? AND user_id = ? LIMIT 1");
$stmtValor->execute([$ag['servico'], $ag['user_id']]);
$precoAtual   = $stmtValor->fetchColumn();
$valorServico = $precoAtual !== false ? $precoAtual : ($ag['valor'] ?? 0);

// Formata data/hora
$dataFormatada = date('d/m/Y', strtotime($ag['data_agendamento']));
$horaFormatada = date('H:i', strtotime($ag['horario']));

// Monta HTML da nota
$html  = '<div style="font-family:Arial, sans-serif; font-size:12px;">';
$html .= '<h2 style="color:#6366f1; margin-bottom:4px;">Salão Top</h2>';
$html .= '<span style="font-size:11px;color:#6b7280;">Nota de Serviço</span>';
$html .= '<hr style="margin:8px 0 12px;">';

$html .= '<strong>Cliente:</strong> ' . htmlspecialchars($ag['cliente_nome']) . '<br>';
if (!empty($ag['cliente_email'])) {
    $html .= '<strong>E-mail:</strong> ' . htmlspecialchars($ag['cliente_email']) . '<br>';
}
$html .= '<strong>Serviço:</strong> ' . htmlspecialchars($ag['servico']) . '<br>';
$html .= '<strong>Data:</strong> ' . $dataFormatada . '<br>';
$html .= '<strong>Horário:</strong> ' . $horaFormatada . '<br>';
$html .= '<strong>Valor:</strong> R$ ' . number_format($valorServico, 2, ',', '.') . '<br>';

if (!empty($ag['observacoes'])) {
    $html .= '<br><strong>Observações:</strong><br>' . nl2br(htmlspecialchars($ag['observacoes'])) . '<br>';
}

$html .= '<br><small>Emitido em ' . date('d/m/Y H:i') . '</small>';
$html .= '</div>';

// Gera PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A5', 'portrait');
$dompdf->render();
$dompdf->stream('nota-servico-' . $id . '.pdf', ['Attachment' => true]);
exit;
