<?php
session_start();
$_SESSION['user_id']=2;
$_SERVER['HTTP_HOST']='salao.develoi.com';
$_SERVER['PHP_SELF']='/agenda.php';
$_SERVER['HTTP_REFERER']='https://salao.develoi.com/dashboard';
try {
    ob_start();
    require_once 'pages/agenda/agenda.php';
    ob_end_clean();
    echo 'Success';
} catch (Throwable $t) {
    echo "Fatal Error: " . $t->getMessage() . " in " . $t->getFile() . ":" . $t->getLine();
}
