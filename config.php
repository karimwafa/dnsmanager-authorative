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

// Helper to get local API Key from configuration files
function getLocalApiKey() {
    $paths = ['/etc/powerdns/pdns.d/api.conf', '/etc/powerdns/pdns.conf'];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            $content = file_get_contents($path);
            if (preg_match('/api-key=([^\s#]+)/', $content, $matches)) {
                return $matches[1];
            }
        }
    }
    return null;
}

// Fetch servers from MySQL
$servers = [];
try {
    $stmt = $pdo->query("SELECT * FROM cluster_servers ORDER BY id ASC");
    $serversRaw = $stmt->fetchAll();
    if ($serversRaw) {
        foreach ($serversRaw as $s) {
            $s['api_key'] = decryptData($s['api_key']);
            $servers[] = $s;
        }
    }
} catch (PDOException $e) {
    // Silent fail if table is missing
}
