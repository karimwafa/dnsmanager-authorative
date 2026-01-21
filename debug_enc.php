<?php
require_once 'config.php';
// We need to inspect the raw DB value too, so we make a new connection or use valid variable if available
// config.php already fetches and decrypts $servers.

echo "APP_SECRET: " . APP_SECRET . "\n";

foreach ($servers as $s) {
    echo "Server: " . $s['name'] . "\n";
    echo "Decrypted Key from App: " . $s['api_key'] . "\n";

    // Let's do a raw fetch to see what's in DB
    $stmt = $pdo->prepare("SELECT api_key FROM cluster_servers WHERE id = ?");
    $stmt->execute([$s['id']]);
    $raw = $stmt->fetchColumn();
    echo "Raw DB Value: " . $raw . "\n";

    $decryptedManual = decryptData($raw);
    echo "Decrypted Manual: " . $decryptedManual . "\n";
    echo "-------------------\n";
}
