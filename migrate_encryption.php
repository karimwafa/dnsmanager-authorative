<?php
require_once 'config.php'; // Includes db.php and helpers

echo "Encrypting Cluster Servers...\n";
$stmt = $pdo->query("SELECT id, api_key FROM cluster_servers");
while ($row = $stmt->fetch()) {
    if (strpos($row['api_key'], '::') === false) { // Only if not already encrypted (naive check)
        $enc = encryptData($row['api_key']);
        $upd = $pdo->prepare("UPDATE cluster_servers SET api_key = ? WHERE id = ?");
        $upd->execute([$enc, $row['id']]);
        echo " - Encrypted Server ID {$row['id']}\n";
    }
}

echo "Encrypting Generated Keys...\n";
$stmt = $pdo->query("SELECT id, key_string FROM api_keys");
while ($row = $stmt->fetch()) {
    if (strpos($row['key_string'], '::') === false) {
        $enc = encryptData($row['key_string']);
        $upd = $pdo->prepare("UPDATE api_keys SET key_string = ? WHERE id = ?");
        $upd->execute([$enc, $row['id']]);
        echo " - Encrypted Key ID {$row['id']}\n";
    }
}

echo "Done.\n";
