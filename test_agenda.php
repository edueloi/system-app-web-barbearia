<?php
session_start();
$_SESSION['user_id'] = 1;
$_SERVER['HTTP_HOST'] = 'salao.develoi.com';
$_SERVER['PHP_SELF'] = '/agenda.php';
ob_start();
require_once 'pages/agenda/agenda.php';
ob_end_clean();
echo "Success\n";
