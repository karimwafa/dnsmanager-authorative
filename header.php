<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Default title if not set
if (!isset($title)) {
    $title = "DNS Author Manager";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-cloud-check-fill text-primary me-2"></i>DNS Author Manager
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">Clusters</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="key_generator.php">API Keys</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="batch_ptr.php">Batch PTR</a>
                        </li>
                        <li class="nav-item ms-lg-3">
                            <a class="btn btn-outline-danger btn-sm mt-1 mt-lg-0" href="logout.php">Logout</a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Spacer for fixed navbar -->
    <div style="margin-top: 80px;"></div>