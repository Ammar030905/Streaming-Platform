<?php
require_once '../config.php';

if(isset($_SESSION['admin_id'])){
    redirect('/admin/dashboard.php');
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    verify_csrf_or_fail();

    $throttleKey = 'admin_login_' . md5(currentClientIp() . '|' . strtolower(trim((string)($_POST['email'] ?? ''))));
    if (!throttle_attempts($throttleKey, 6, 300)) {
        $error = 'Too many login attempts. Please wait a few minutes and try again.';
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if(empty($error) && (empty($email) || empty($password))){
        $error = 'Email and password required.';
    } elseif (empty($error)) {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if($admin && password_verify($password, $admin['password'])){
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['username'];
            $_SESSION['ua_fingerprint'] = session_user_agent_fingerprint();
            clear_throttle($throttleKey);
            logAction('admin_login', 'Admin logged in: ' . $admin['email']);
            redirect('/admin/dashboard.php');
        } else {
            usleep(300000);
            $error = 'Invalid credentials.';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-5">
        <div class="card shadow">
            <div class="card-header bg-danger text-white text-center py-3">
                <h4 class="mb-0">Secure Admin Login</h4>
            </div>
            <div class="card-body p-4">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label>Email address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-4">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 btn-lg">Login to Admin Panel</button>
                    <p class="text-center mt-3 small text-muted">Go back to <a href="<?= BASE_URL ?>" class="text-white">Home</a></p>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
