<?php
require_once 'db.php';

try {
    // 1. Table for Cluster Servers
    $pdo->exec("CREATE TABLE IF NOT EXISTS cluster_servers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        host VARCHAR(255) NOT NULL,
        port INT DEFAULT 8081,
        api_key VARCHAR(255) NOT NULL,
        role ENUM('sync', 'standalone') DEFAULT 'sync',
        type ENUM('native', 'master', 'slave') DEFAULT 'native',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'cluster_servers' created or already exists.\n";

    // 2. Table for API Keys
    $pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        label VARCHAR(255) NOT NULL,
        description TEXT,
        key_string VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'api_keys' created or already exists.\n";

    // 3. Table for Users (using app_users to avoid conflict with existing users table)
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'app_users' verified or created.\n";
} catch (PDOException $e) {
    die("DB Setup Error: " . $e->getMessage() . "\n");
}
