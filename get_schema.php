<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/banco_salao.sqlite');
$stmt = $pdo->query('PRAGMA table_info(agendamentos)');
$cols = [];
foreach ($stmt as $row) {
    $cols[] = $row['name'];
}
file_put_contents(__DIR__ . '/cols.json', json_encode($cols));
