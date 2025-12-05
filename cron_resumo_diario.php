<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';

function processarResumoDiario() {
    global $pdo;

    $stmt = $pdo->query("SELECT * FROM usuarios WHERE receber_email_diario = 1");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $user) {
        $stmt = $pdo->prepare("SELECT * FROM agendamentos WHERE user_id = ? AND data_agendamento = CURDATE() ORDER BY hora_agendamento ASC");
        $stmt->execute([$user['id']]);
        $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($agendamentos) > 0) {
            $subject = "Resumo de Agendamentos para Hoje";
            $body = "<h1>Resumo de Agendamentos para Hoje</h1>";
            $body .= "<table border='1'><tr><th>Horário</th><th>Cliente</th><th>Serviço</th><th>Ação</th></tr>";

            foreach ($agendamentos as $agendamento) {
                $link = "https://salao.develoi.com/pages/agenda/agenda.php?view=day&data=" . $agendamento['data_agendamento'] . "&focus_id=" . $agendamento['id'];
                $body .= "<tr>";
                $body .= "<td>" . htmlspecialchars($agendamento['hora_agendamento']) . "</td>";
                $body .= "<td>" . htmlspecialchars($agendamento['cliente_nome']) . "</td>";
                $body .= "<td>" . htmlspecialchars($agendamento['servico']) . "</td>";
                $body .= "<td><a href='" . $link . "'>Ver</a></td>";
                $body .= "</tr>";
            }

            $body .= "</table>";

            sendMailDeveloi($user['email'], $subject, $body);
        }
    }
}

processarResumoDiario();
