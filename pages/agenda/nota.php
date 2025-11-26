<?php
// pages/agenda/nota.php
// Gera uma "nota" simples em PDF para um agendamento com layout de Nota Fiscal

// ===============================================
// 1. VERIFICAÇÃO E INCLUSÃO DE DEPENDÊNCIAS
// ===============================================

// Tenta carregar o Dompdf/Composer Autoload
$autoload_path = '../../vendor/autoload.php';
if (!file_exists($autoload_path)) {
    // MENSAGEM DE ERRO CRÍTICA SE O AUTOLOAD NÃO FOR ENCONTRADO
    die('ERRO FATAL: O arquivo de dependências do Composer (autoload.php) não foi encontrado em: ' . $autoload_path . '. Certifique-se de que o Composer foi executado e os arquivos existem.');
}
require_once $autoload_path;

// Tenta carregar a conexão com o banco de dados
$db_path = '../../includes/db.php';
if (!file_exists($db_path)) {
    die('ERRO FATAL: O arquivo de conexão com o banco de dados (db.php) não foi encontrado em: ' . $db_path);
}
include $db_path;

use Dompdf\Dompdf;
use Dompdf\Options; // Boa prática para definir opções

// ===============================================
// 2. BUSCA DE DADOS
// ===============================================

if (!isset($_GET['id'])) {
    die('Agendamento não especificado.');
}

$id = (int)$_GET['id'];

// Busca dados do Agendamento e Cliente
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

// ============================
// Dados do profissional (usuario)
// ============================
$stmtUser = $pdo->prepare("
    SELECT nome, email, telefone,
           cep, endereco, numero, bairro, cidade, estado
      FROM usuarios
     WHERE id = ?
     LIMIT 1
");
$stmtUser->execute([$ag['user_id']]);
$prof = $stmtUser->fetch();

// Garante que todas as variáveis do profissional/usuário estejam definidas
$nomeProfissional     = $prof['nome']     ?? 'Profissional de Beleza';
$emailProfissional    = $prof['email']    ?? '';
$telefoneProfissional = $prof['telefone'] ?? '';

// Monta endereço completo do profissional
$enderecoPartes = [];
if (!empty($prof['endereco'])) {
    $linha = $prof['endereco'];
    if (!empty($prof['numero'])) {
        $linha .= ', ' . $prof['numero'];
    }
    $enderecoPartes[] = $linha;
}
if (!empty($prof['bairro'])) {
    $enderecoPartes[] = $prof['bairro'];
}
$cidadeEstado = trim(
    ((string)($prof['cidade'] ?? '')) . 
    (!empty($prof['estado']) ? ' - ' . $prof['estado'] : '')
);
if (!empty($cidadeEstado)) {
    $enderecoPartes[] = $cidadeEstado;
}
if (!empty($prof['cep'])) {
    $enderecoPartes[] = 'CEP: ' . $prof['cep'];
}
$enderecoProfissional = implode(' • ', array_filter($enderecoPartes));

// ============================
// Valor do serviço e formatação
// ============================
$stmtValor = $pdo->prepare("
    SELECT preco 
      FROM servicos 
     WHERE nome = ? 
       AND user_id = ? 
     LIMIT 1
");
$stmtValor->execute([$ag['servico'], $ag['user_id']]);
$precoAtual     = $stmtValor->fetchColumn();
$valorServico   = $precoAtual !== false ? $precoAtual : ($ag['valor'] ?? 0); 

// ============================
// Datas e formatações
// ============================
// Garante que as variáveis de data estejam definidas antes da formatação
$data_agendamento = $ag['data_agendamento'] ?? date('Y-m-d');
$horario          = $ag['horario'] ?? date('H:i:s');

$dataFormatada      = date('d/m/Y', strtotime($data_agendamento));
$horaFormatada      = date('H:i', strtotime($horario));
$emitidoEm          = date('d/m/Y H:i');

// Garante que as variáveis do agendamento estejam definidas
$statusAgendamento  = $ag['status']         ?? 'Pendente';
$notaNumero         = str_pad($id, 4, '0', STR_PAD_LEFT);
$valorFormatado     = number_format($valorServico, 2, ',', '.'); 
$valorTotalComSimbolo = 'R$ ' . $valorFormatado;

// ===============================================
// 3. MONTAGEM DO HTML (Layout Cupom/DANFE Compacto)
// ===============================================
$html  = '<html><head><meta charset="UTF-8">';
$html .= '<style>
    * {
        /* Fonte compacta, ideal para documentos fiscais */
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 9px; /* Reduzindo o tamanho da fonte */
        box-sizing: border-box;
    }
    body {
        margin: 0;
        padding: 0;
    }
    .nota-container {
        width: 100%;
        padding: 5px; /* Reduzindo o padding */
        border: 1px solid #000;
    }
    .box {
        border: 1px solid #000;
        margin-bottom: 5px; /* Reduzindo o espaço entre os blocos */
        padding: 3px 5px;
    }
    .box-title {
        font-size: 10px;
        font-weight: bold;
        background-color: #e0e0e0;
        padding: 2px 0;
        margin: -3px -5px 3px -5px; /* Ajusta para preencher a largura */
        text-align: center;
        border-bottom: 1px solid #000;
        text-transform: uppercase;
    }
    .data-line {
        line-height: 1.2;
    }
    .label {
        font-weight: bold;
        padding-right: 3px;
    }
    /* Header Principal (Prestador/Nota) */
    .header-table {
        width: 100%;
        border-collapse: collapse;
    }
    .header-table td {
        border: 1px solid #000;
        padding: 4px;
        vertical-align: top;
    }
    .company-name {
        font-size: 11px;
        font-weight: bold;
        margin: 0 0 1px 0;
    }
    .doc-info-title {
        font-size: 10px;
        font-weight: bold;
        text-align: center;
        margin-bottom: 2px;
        background-color: #f0f0f0;
    }
    
    /* Tabela de Serviços */
    .service-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 5px;
    }
    .service-table th, .service-table td {
        border: 1px solid #000;
        padding: 3px;
        text-align: left;
        font-size: 8.5px; /* Fonte menor para os itens */
    }
    .service-table th {
        background-color: #e0e0e0;
        text-transform: uppercase;
        font-size: 8.5px;
        text-align: center;
    }
    .col-cod, .col-qtd { width: 5%; text-align: center; }
    .col-valor-unit, .col-valor-total { width: 15%; text-align: right; }

    /* Totais */
    .totais-box {
        margin-top: 5px;
        padding-top: 4px;
        border-top: 1px solid #000;
        text-align: right;
    }
    .totais-box .label {
        font-size: 10px;
        font-weight: bold;
    }
    .totais-box .total-value {
        font-size: 11px;
        font-weight: bold;
        padding-left: 10px;
        color: #000;
    }

    /* Rodapé/Observações */
    .rodape {
        margin-top: 5px;
        padding-top: 5px;
        border-top: 1px dashed #000; /* Linha tracejada como em cupom */
        text-align: center;
        font-size: 7.5px;
        color: #333;
    }
    .observacoes-content {
        padding: 0;
        margin: 0;
        font-size: 8.5px;
    }
</style>';
$html .= '</head><body>';

$html .= '<div class="nota-container">';

// 1. CABEÇALHO PRINCIPAL (Prestador de Serviço e Dados da Nota)
$html .= '<table class="header-table" cellspacing="0" cellpadding="0">
            <tr>
                <td style="width: 65%;">
                    <p class="company-name">' . htmlspecialchars($nomeProfissional) . '</p>
                    <div class="data-line">Atendimento de Beleza & Cuidados Pessoais</div>';
if (!empty($enderecoProfissional)) {
    $html .= '              <div class="data-line">' . htmlspecialchars($enderecoProfissional) . '</div>';
}
if (!empty($telefoneProfissional) || !empty($emailProfissional)) {
    $contatosLinha = [];
    if (!empty($telefoneProfissional)) { $contatosLinha[] = 'Tel: ' . htmlspecialchars($telefoneProfissional); }
    if (!empty($emailProfissional)) { $contatosLinha[] = 'Email: ' . htmlspecialchars($emailProfissional); }
    $html .= '              <div class="data-line">' . implode(' | ', $contatosLinha) . '</div>';
}
$html .= '              <div class="data-line"><span class="label">Status do Agendamento:</span> ' . htmlspecialchars($statusAgendamento) . '</div>
                </td>
                <td style="width: 35%; text-align: right;">
                    <p class="doc-info-title">NOTA DE SERVIÇO</p>
                    <div class="data-line"><span class="label">Nº Nota:</span> ' . $notaNumero . '</div>
                    <div class="data-line"><span class="label">Emissão:</span> ' . $emitidoEm . '</div>
                    <div class="data-line"><span class="label">Agendamento:</span> ' . $dataFormatada . ' ' . $horaFormatada . '</div>
                </td>
            </tr>
        </table>';

// 2. DADOS DO CLIENTE
$html .= '<div class="box">
            <div class="box-title">Dados do Cliente (Tomador)</div>
            <table width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td style="width: 50%;"><div class="data-line"><span class="label">Nome:</span> ' . htmlspecialchars($ag['cliente_nome'] ?? 'N/A') . '</div></td>
                    <td style="width: 50%;">' . (!empty($ag['cliente_cpf']) ? '<div class="data-line"><span class="label">CPF:</span> ' . htmlspecialchars($ag['cliente_cpf']) . '</div>' : '') . '</td>
                </tr>
                <tr>
                    <td colspan="2">' . (!empty($ag['cliente_email']) ? '<div class="data-line"><span class="label">E-mail:</span> ' . htmlspecialchars($ag['cliente_email']) . '</div>' : '') . '</td>
                </tr>
            </table>
          </div>';

// 3. DETALHES DO SERVIÇO (Itens)
$html .= '<div class="box">
            <div class="box-title">Detalhamento dos Serviços Prestados</div>
            <table class="service-table" cellspacing="0" cellpadding="0">
                <thead>
                    <tr>
                        <th class="col-cod">CÓD.</th>
                        <th>DESCRIÇÃO DO SERVIÇO</th>
                        <th class="col-qtd">QTD.</th>
                        <th class="col-valor-unit">UNITÁRIO (R$)</th>
                        <th class="col-valor-total">VALOR TOTAL (R$)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="col-cod">1</td>
                        <td>' . htmlspecialchars($ag['servico'] ?? 'Serviço não especificado') . '</td>
                        <td class="col-qtd">1</td>
                        <td class="col-valor-unit">' . $valorFormatado . '</td>
                        <td class="col-valor-total">' . $valorFormatado . '</td>
                    </tr>
                </tbody>
            </table>
          </div>';

// 4. TOTAIS
$html .= '<div class="totais-box">
            <span class="label">VALOR TOTAL DOS SERVIÇOS:</span>
            <span class="total-value">' . $valorTotalComSimbolo . '</span>
          </div>';

// 5. OBSERVAÇÕES
$observacoes = $ag['observacoes'] ?? '';
if (!empty($observacoes)) {
    $html .= '<div class="box">
                <div class="box-title">Observações do Serviço</div>
                <div class="observacoes-content">' . nl2br(htmlspecialchars($observacoes)) . '</div>
              </div>';
}

// 6. RODAPÉ INFORMATIVO
$html .= '<div class="rodape">
            **COMPROVANTE DE AGENDAMENTO/SERVIÇO**
            <br>Este documento não possui validade de Nota Fiscal Eletrônica (NF-e) ou Cupom Fiscal regulamentado.
            <br>Emitido eletronicamente em ' . $emitidoEm . '.
          </div>';

$html .= '</div>'; // .nota-container
$html .= '</body></html>';

// ===============================================
// 4. GERAÇÃO DO PDF
// ===============================================
// Instancia o Dompdf. Adiciona o Options para evitar warnings
$options = new Options();
$options->set('defaultFont', 'DejaVu Sans'); // Mantém a compatibilidade com caracteres especiais (Unicode)
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html); 
$dompdf->setPaper('A5', 'portrait'); 
$dompdf->render();

// Garante que a variável para o nome do arquivo esteja definida
$file_id = $id ?? 'N_A';
$dompdf->stream('nota-servico-' . $file_id . '.pdf', ['Attachment' => true]);
exit;