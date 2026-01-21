<?php
require_once 'db.php';
$stmt = $pdo->query("DESCRIBE api_keys");
print_r($stmt->fetchAll());
