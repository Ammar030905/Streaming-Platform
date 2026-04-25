<?php
require_once '../config.php';

require_post();
verify_csrf_or_fail();

if(isset($_SESSION['admin_id'])) {
    logAction('admin_logout', 'Admin logged out: ' . $_SESSION['admin_id']);
}
$_SESSION = [];
session_unset();
session_destroy();
header('Location: ' . BASE_URL . '/admin/login.php');
exit;
