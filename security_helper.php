<?php
if (session_status() === PHP_SESSION_NONE) {
    // malicious user could set session id via cookie before scripts starts
    // but in our case, we should control when session starts.
    // However, to be safe, we will just start it if not started.
}

/**
 * Secure Session Start
 * Enforces secure cookie parameters.
 */
function secure_session_start()
{
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }

        session_start();
    }
}

/**
 * Send Security Headers
 */
function secure_headers()
{
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    // header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com; img-src 'self' data:;");
    // CSP is commented out for now to prevent breaking existing scripts/styles. 
    // It requires careful tuning.
}

/**
 * Generate CSRF Token
 */
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output CSRF Input Field
 */
function csrf_field()
{
    $token = generate_csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Verify CSRF Token
 */
function verify_csrf_token()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die('CSRF validation failed. Refresh the page and try again.');
        }
    }
}

/**
 * Rate Limiting Logic
 */
function check_login_limit($pdo, $ip)
{
    // Cleanup old attempts (older than 15 minutes)
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute();

    // Check count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    $attempts = $stmt->fetchColumn();

    if ($attempts >= 5) {
        return false; // Blocked
    }
    return true; // Allowed
}

function log_failed_login($pdo, $ip, $username)
{
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
    $stmt->execute([$ip, $username]);
}

function clear_login_attempts($pdo, $ip)
{
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
}
