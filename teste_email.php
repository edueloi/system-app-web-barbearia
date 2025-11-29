<?php
include 'includes/mailer.php';

$ok = sendMailDeveloi('seuemail@gmail.com', 'Edu', 'Teste Develoi', '<p>Teste de envio</p>');

echo $ok ? 'OK, enviado' : 'Falhou';
