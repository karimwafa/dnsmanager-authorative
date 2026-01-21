<?php
require_once 'db.php';

// Check if any server exists
$stmt = $pdo->query("SELECT COUNT(*) FROM cluster_servers");
$count = $stmt->fetchColumn();

if ($count == 0) {
    $name = "Primary Server (Local)";
    $host = "127.0.0.1";
    $port = 8081;
    // Hardcoded key from previous steps or recovery
    $key = "e052acdac7f6774345e13deadd5363f3";

    $stmt = $pdo->prepare("INSERT INTO cluster_servers (name, host, port, api_key, role) VALUES (?, ?, ?, ?, 'sync')");
    $stmt->execute([$name, $host, $port, $key]);
    echo "Seeded Primary Server into DB.\n";
} else {
    echo "Servers already exist in DB.\n";
}
