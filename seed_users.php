<?php
require_once 'db.php';

echo "Seeding Users Table...\n";

// Check if admin exists
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
$stmt->execute(['admin']);
$count = $stmt->fetchColumn();

if ($count == 0) {
    // Create default admin
    $username = 'admin';
    $password = 'admin'; // Keeping the requested default, user should change this
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')");
    $stmt->execute([$username, $hash]);

    echo "Default user 'admin' created with password 'admin'.\n";
    echo "PLEASE CHANGE THIS PASSWORD IMMEDIATELY!\n";
} else {
    echo "User 'admin' already exists. Skipping.\n";
}

echo "Seeding complete.\n";
