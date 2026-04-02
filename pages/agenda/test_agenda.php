<?php
session_start();
$_SESSION['user_id']=1;
$_SERVER['HTTP_HOST']='salao.develoi.com';
$_SERVER['PHP_SELF']='/agenda.php';
$_SERVER['HTTP_REFERER']='https://salao.develoi.com/dashboard';
ob_start();
require_once 'agenda.php';
ob_end_clean();
echo 'Success' . PHP_EOL;
