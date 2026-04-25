<?php
require_once 'config.php';

if(isset($_SESSION['user_id'])){
    redirect('/user/dashboard.php');
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    verify_csrf_or_fail();

    $throttleKey = 'user_login_' . md5(currentClientIp());
    if (!throttle_attempts($throttleKey, 6, 300)) {
        $error = 'Too many login attempts. Please wait a few minutes and try again.';
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if(empty($error) && (empty($email) || empty($password))){
        $error = 'Please enter email and password.';
    } elseif (empty($error)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if($user && password_verify($password, $user['password'])){
            if($user['status'] == 'pending'){
                $error = 'Your account is pending admin approval.';
            } elseif($user['status'] == 'rejected') {
                $error = 'Your account has been rejected.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                clear_throttle($throttleKey);
                logAction('user_login', 'User logged in: ' . $user['email']);
                redirect('/user/dashboard.php');
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header pb-3 pt-3">
                <h4 class="mb-0 text-center">User Login</h4>
            </div>
            <div class="card-body p-4">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Email address</label>
                        <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <!-- Forgot password simulation -->
                    <div class="d-flex justify-content-between mb-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label text-muted" for="remember">Remember me</label>
                        </div>
                        <a href="#" onclick="alert('Password reset link sent to your email (simulated).')" class="text-muted">Forgot password?</a>
                    </div>
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">Login</button>
                    </div>
                    <div class="text-center text-muted">
                        Don't have an account? <a href="<?= BASE_URL ?>/register.php" class="text-decoration-none fw-bold" style="color: var(--primary-color);">Register here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
