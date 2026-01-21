<?php
require_once 'security_helper.php';
secure_session_start();
secure_headers();
require_once 'config.php';

$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Security Checks
    require_once 'db.php';

    // 1. Check Rate Limit
    if (!check_login_limit($pdo, $_SERVER['REMOTE_ADDR'])) {
        $error = 'Too many failed attempts. Please try again in 15 minutes.';
    } else {
        // 2. Authenticate

        // Temporarily suppress CSRF check for initial rollout if needed, but per plan we enable it.
        // verify_csrf_token(); // Check token (Helper function already includes die())
        // Actually, helper dies on failure. Let's wrap it or just call it.
        // Ideally we want to show error in $error instead of dying, but helper `die`s. 
        // For better UX let's do a check here manually or rely on helper. 
        // Helper `verify_csrf_token` checks POST and dies. let's use it.
        verify_csrf_token();

        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM app_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Success
            clear_login_attempts($pdo, $_SERVER['REMOTE_ADDR']);

            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            header('Location: index.php');
            exit;
        } else {
            // Invalid
            $error = 'Invalid username or password';
            log_failed_login($pdo, $_SERVER['REMOTE_ADDR'], $username);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - DNS Author Manager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="login-body">
    <div class="container">
        <div class="card login-card">
            <div class="card-body">
                <div class="text-center mb-4"> <!-- Reduced from mb-5 -->
                    <div class="mb-2 text-primary"> <!-- Reduced from mb-3 -->
                        <i class="bi bi-cloud-check-fill" style="font-size: 2.5rem;"></i> <!-- Reduced from 3rem -->
                    </div>
                    <h4 class="fw-bold mb-1">DNS Author Manager</h4>
                    <p class="text-muted small mb-0">Enter your credentials to access the console</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center py-2 text-small mb-4 border-0 bg-danger-subtle text-danger">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?php csrf_field(); ?>

                    <div class="mb-3">
                        <label class="form-label small fw-medium text-muted text-uppercase">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" class="form-control border-start-0 ps-0" placeholder="admin" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-medium text-muted text-uppercase">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="••••••••" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold shadow-sm">
                        Sign In <i class="bi bi-arrow-right-short ms-1"></i>
                    </button>
                    <div class="text-center mt-4">
                        <small class="text-muted opacity-75">Protected System &copy; <?= date('Y') ?></small>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>