<?php
require_once 'config.php';

require_post();
verify_csrf_or_fail();

if(isset($_SESSION['user_id'])) {
    logAction('user_logout', 'User logged out: ' . $_SESSION['user_id']);
}
$_SESSION = [];
session_unset();
session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit;
