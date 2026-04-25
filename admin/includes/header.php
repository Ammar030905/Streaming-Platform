<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StreamHub Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset_url('/assets/css/style.css') ?>">
</head>
<body>
    <?php if(isset($_SESSION['admin_id'])): ?>
    <div class="d-flex" style="min-height: 100vh;">
        <!-- Sidebar -->
        <div class="bg-dark border-end border-secondary" style="width: 250px;">
            <div class="p-3 border-bottom border-secondary text-primary text-center">
                <h4 class="mb-0 fw-bold">Admin Panel</h4>
            </div>
            <div class="list-group list-group-flush mt-3">
                <a href="<?= BASE_URL ?>/admin/dashboard.php" class="list-group-item list-group-item-action bg-dark text-light border-secondary">Dashboard</a>
                <a href="<?= BASE_URL ?>/admin/users.php" class="list-group-item list-group-item-action bg-dark text-light border-secondary">Manage Users</a>
                <a href="<?= BASE_URL ?>/admin/events.php" class="list-group-item list-group-item-action bg-dark text-light border-secondary">Manage Events</a>
                <a href="<?= BASE_URL ?>/admin/gallery.php" class="list-group-item list-group-item-action bg-dark text-light border-secondary">Manage Gallery</a>
                <form method="POST" action="<?= BASE_URL ?>/admin/logout.php" class="mt-5">
                    <?= csrf_field() ?>
                    <button type="submit" class="list-group-item list-group-item-action bg-dark text-danger border-secondary w-100 text-start">Logout</button>
                </form>
            </div>
        </div>
        <!-- Main Content -->
        <div class="flex-grow-1 p-4 bg-transparent">
    <?php else: ?>
    <!-- Login Layout -->
    <main class="container py-5">
    <?php endif; ?>
