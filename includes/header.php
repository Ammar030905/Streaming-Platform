<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
        $pageTitle = $pageTitle ?? 'StreamHub | Community Live Telecast';
        $pageDescription = $pageDescription ?? 'Secure community live streaming platform with scheduled events, protected playback, and modern watch experience.';
    ?>
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="robots" content="index,follow">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:url" content="<?= e((string)($_SERVER['REQUEST_URI'] ?? BASE_URL)) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#0f172a">
    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = stored || (prefersDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= asset_url('/assets/css/style.css') ?>">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <nav class="navbar navbar-expand-lg navbar-light bg-white pb-3 pt-3">
        <div class="container">
            <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">StreamHub</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/gallery.php">Gallery</a>
                    </li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/user/dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item ms-lg-2">
                            <button class="btn btn-sm btn-outline-primary theme-toggle" type="button" aria-label="Toggle theme">Theme</button>
                        </li>
                        <li class="nav-item">
                            <form method="POST" action="<?= BASE_URL ?>/logout.php" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="nav-link btn btn-link p-0">Logout</button>
                            </form>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/register.php">Register</a>
                        </li>
                        <li class="nav-item ms-lg-2">
                            <button class="btn btn-sm btn-outline-primary theme-toggle" type="button" aria-label="Toggle theme">Theme</button>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <main id="main-content" class="container py-5">
