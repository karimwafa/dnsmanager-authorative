<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'PDNSClient.php';
require_once 'db.php'; // Database connection needed for users
require_once 'config.php';
require_once 'PDNSClient.php';
require_once 'security_helper.php';

// Verify CSRF for all POST actions
verify_csrf_token();

// Initialize Clients (servers loaded from config.php -> MySQL)
$clients = [];
foreach ($servers as $server) {
    if (isset($server['host']) && isset($server['port']) && isset($server['api_key'])) {
        // Only add if role is sync (default) or explicitly set to sync
        $role = $server['role'] ?? 'sync';
        if ($role === 'sync') {
            $clients[$server['name']] = new PDNSClient($server['host'], $server['port'], $server['api_key']);
        }
    }
}

$action = $_REQUEST['action'] ?? '';

// --- USER MANAGEMENT ACTIONS ---

if ($action === 'add_user') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($password !== $confirm) {
        die("Passwords do not match. <a href='users.php'>Go back</a>");
    }

    // check if exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM app_users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        die("Username already exists. <a href='users.php'>Go back</a>");
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO app_users (username, password_hash) VALUES (?, ?)");

    try {
        $stmt->execute([$username, $hash]);
        header("Location: users.php?msg=User created");
    } catch (PDOException $e) {
        die("Error creating user: " . $e->getMessage());
    }
    exit;
}

if ($action === 'delete_user') {
    $id = $_POST['id'];
    // Prevent deleting self
    // Need to fetch current user id, but simple check against session username is safer for now if we stored it
    // For now, let's just delete by ID.

    $stmt = $pdo->prepare("DELETE FROM app_users WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: users.php?msg=User deleted");
    exit;
}

if ($action === 'edit_user') {
    $id = $_POST['id'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (!empty($password)) {
        if ($password !== $confirm) {
            die("Passwords do not match. <a href='users.php'>Go back</a>");
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE app_users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $id]);
        $msg = "User password updated";
    } else {
        $msg = "No changes made";
    }

    header("Location: users.php?msg=" . urlencode($msg));
    exit;
}

// --- SERVER MANAGEMENT ACTIONS ---

if ($action === 'add_server') {
    $name = trim($_POST['name']);
    $host = trim($_POST['host']);
    $port = (int)$_POST['port'];
    $key = trim($_POST['key']); // In DB this is column 'api_key'

    if ($name && $host && $port && $key) {
        $encryptedKey = encryptData($key);
        $stmt = $pdo->prepare("INSERT INTO cluster_servers (name, host, port, api_key, role) VALUES (?, ?, ?, ?, 'sync')");
        $stmt->execute([$name, $host, $port, $encryptedKey]);
    }
    header("Location: settings.php");
    exit;
}

if ($action === 'update_role') {
    $index = (int)$_POST['index']; // Wait, in DB we use ID, but settings.php used index?
    // We need to fix settings.php to pass ID instead of index.
    // For now assuming settings.php passes ID in 'index' field or we fix it.
    // Let's assume we will pass 'id' from settings.php
    $id = (int)$_POST['id'];
    $role = $_POST['role'];

    $stmt = $pdo->prepare("UPDATE cluster_servers SET role = ? WHERE id = ?");
    $stmt->execute([$role, $id]);

    header("Location: settings.php");
    exit;
}

if ($action === 'delete_server') {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM cluster_servers WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: settings.php");
    exit;
}

// --- ZONE MANAGEMENT ACTIONS ---

if ($action === 'create_zone') {
    $domain = trim($_POST['domain']);
    $nameservers = array_map('trim', explode(',', $_POST['nameservers']));

    // Validate
    if (substr($domain, -1) !== '.') $domain .= '.';
    foreach ($nameservers as &$ns) {
        if (substr($ns, -1) !== '.') $ns .= '.';
    }

    $results = [];
    foreach ($clients as $name => $client) {
        try {
            $res = $client->createZone($domain, $nameservers);
            $results[$name] = $res['code'];
        } catch (Exception $e) {
            $results[$name] = 'Error: ' . $e->getMessage();
        }
    }

    header("Location: index.php");
    exit;
}

if ($action === 'delete_zone') {
    $zoneId = $_POST['zone'];

    foreach ($clients as $client) {
        try {
            $client->deleteZone($zoneId);
        } catch (Exception $e) {
            // Log error or continue
        }
    }

    header("Location: index.php");
    exit;
}

if ($action === 'add_record') {
    $zoneId = $_POST['zone_id'];
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $content = trim($_POST['content']);

    // Auto-append domain if simple name used
    if (strpos($name, '.') === false && $name !== '@') {
        $name .= "." . $zoneId;
    }
    if ($name === '@') $name = $zoneId;

    foreach ($clients as $nameClient => $client) {
        try {
            $client->addRecord($zoneId, $name, $type, $content);
        } catch (Exception $e) {
            // Log error
        }
    }

    header("Location: view_zone.php?zone=" . urlencode($zoneId));
    exit;
}

if ($action === 'delete_record') {
    $zoneId = $_POST['zone_id'];
    $name = trim($_POST['name']); // Fully qualified name from the table
    $type = $_POST['type'];

    foreach ($clients as $client) {
        try {
            $client->deleteRecord($zoneId, $name, $type);
        } catch (Exception $e) {
            // Log error
        }
    }

    header("Location: view_zone.php?zone=" . urlencode($zoneId));
    exit;
}

if ($action === 'edit_record') {
    $zoneId = $_POST['zone_id'];
    $originalName = trim($_POST['original_name']);
    $originalType = $_POST['original_type'];

    $newName = trim($_POST['name']);
    $newType = $_POST['type'];
    $newContent = trim($_POST['content']);
    $newTTL = (int)$_POST['ttl'];

    // Auto-append domain if simple name used
    if (strpos($newName, '.') === false && $newName !== '@') {
        $newName .= "." . $zoneId;
    }
    if ($newName === '@') $newName = $zoneId;

    foreach ($clients as $client) {
        try {
            // 1. Delete the old record
            // Note: This deletes the ENTIRE RRSet for that name/type
            $client->deleteRecord($zoneId, $originalName, $originalType);

            // 2. Add the new record
            $client->addRecord($zoneId, $newName, $newType, $newContent, $newTTL);
        } catch (Exception $e) {
            // Log error
        }
    }

    header("Location: view_zone.php?zone=" . urlencode($zoneId));
    exit;
}
