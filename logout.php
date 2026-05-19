<?php
require_once 'config.php';

require_post();
verify_csrf_or_fail();

if(isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    // Record logout time and clear session token
    $pdo->prepare("UPDATE users SET last_logout_at = NOW(), session_token = NULL WHERE id = ?")
        ->execute([$uid]);
    // End any open watch sessions
    $pdo->prepare("UPDATE watch_sessions SET ended_at = NOW() WHERE user_id = ? AND ended_at IS NULL")
        ->execute([$uid]);
    logAction('user_logout', 'User logged out: ' . $uid);
}

$_SESSION = [];
session_unset();
session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit;
