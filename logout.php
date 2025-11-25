<?php
// Logout simples
session_start();
session_destroy();
header('Location: index.php');
exit;
