<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN receber_email_diario INTEGER DEFAULT 0");
    echo "Migration successful: 'receber_email_diario' column added to 'usuarios' table.";
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}
