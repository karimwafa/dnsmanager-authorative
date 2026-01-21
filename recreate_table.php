<?php
require_once 'db.php';
try {
    $pdo->exec("DROP TABLE IF EXISTS api_keys");
    $pdo->exec("CREATE TABLE api_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        label VARCHAR(255) NOT NULL,
        description TEXT,
        key_string VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Recreated table 'api_keys'.";
} catch (PDOException $e) {
    echo $e->getMessage();
}
