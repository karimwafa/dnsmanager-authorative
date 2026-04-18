<?php
require_once '/var/www/html/config.php';

echo "Starting cleanup of duplicate local nodes...\n";

// 1. Get all local IPs
$localIPs = explode(' ', shell_exec("hostname -I") . " 127.0.0.1");
$localIPs = array_map('trim', array_filter($localIPs));
$placeholders = implode(',', array_fill(0, count($localIPs), '?'));

// 2. Identify potential local nodes
$sql = "SELECT id, name, host, port FROM cluster_servers WHERE host IN ($placeholders) OR name LIKE '%Local%' ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($localIPs);
$nodes = $stmt->fetchAll();

if (count($nodes) <= 1) {
    echo "No duplicates found.\n";
    exit;
}

echo "Found " . count($nodes) . " entries. Keeping the newest one (ID: {$nodes[0]['id']})...\n";

$keepId = $nodes[0]['id'];
$toDelete = [];
for ($i = 1; $i < count($nodes); $i++) {
    $toDelete[] = $nodes[$i]['id'];
}

$deletePlaceholders = implode(',', array_fill(0, count($toDelete), '?'));
$delStmt = $pdo->prepare("DELETE FROM cluster_servers WHERE id IN ($deletePlaceholders)");
$delStmt->execute($toDelete);

echo "Deleted " . count($toDelete) . " duplicate entries.\n";
echo "Cleanup complete.\n";
