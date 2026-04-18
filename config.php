<?php
require_once 'db.php';

// KEEP THIS SECRET SAFE!
define('APP_SECRET', 'ea9e44d3bfe3d77121f4adf5b11ebd3a91e2022f4c27ccd1b7fdc59ae6fe17ad');

function encryptData($data)
{
    if (empty($data)) return $data;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', APP_SECRET, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function decryptData($data)
{
    if (empty($data)) return $data;

    // Try to decode base64 first
    $decoded = base64_decode($data, true);
    if ($decoded === false) return $data; // Not base64 -> return original

    // Check if decoded data contains our delimiter '::'
    if (strpos($decoded, '::') !== false) {
        list($encrypted_data, $iv) = explode('::', $decoded, 2);
        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', APP_SECRET, 0, $iv);
        return ($decrypted === false) ? $data : $decrypted;
    }

    return $data;
}

// Fetch servers from MySQL
$stmt = $pdo->query("SELECT * FROM cluster_servers ORDER BY id ASC");
$serversRaw = $stmt->fetchAll();
$servers = [];

if ($serversRaw) {
    foreach ($serversRaw as $s) {
        // Automatically decrypt key if encrypted
        $s['api_key'] = decryptData($s['api_key']);
        $servers[] = $s;
    }
}
