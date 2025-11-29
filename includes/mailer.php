<?php
// includes/mailer.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function sendMailDeveloi($toEmail, $toName, $subject, $htmlBody) {
    $mail = new PHPMailer(true);

    try {
        // CONFIG SMTP HOSTGATOR (dados do cPanel)
        $mail->isSMTP();
        $mail->Host       = 'salao.develoi.com';          // Servidor de saída
        $mail->SMTPAuth   = true;
        $mail->Username   = 'contato@salao.develoi.com';  // Seu e-mail completo
        $mail->Password   = 'Edu@06051992';             // <<< coloca a senha da conta
        $mail->Port       = 465;                          // Porta SMTP
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;  // SSL na porta 465

        // REMETENTE (usa o mesmo e-mail do SMTP)
        $mail->setFrom('contato@salao.develoi.com', 'Develoi Agenda');
        $mail->addReplyTo('contato@salao.develoi.com', 'Develoi Agenda');

        // DESTINATÁRIO
        $mail->addAddress($toEmail, $toName ?: $toEmail);

        // CONTEÚDO
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Erro ao enviar e-mail: ' . $mail->ErrorInfo);
        return false;
    }
}
