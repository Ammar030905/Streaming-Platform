<?php
require_once '../config.php';

if(!isset($_SESSION['admin_id'])){
    redirect('/admin/login.php');
}

// Handle actions
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_action'])){
    verify_csrf_or_fail();

    $id = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['user_action'] ?? '';
    
    if($id > 0 && $action == 'approve'){
        $pdo->prepare("UPDATE users SET status = 'approved'::user_status WHERE id = ?")->execute([$id]);
        logAction('approve_user', "Approved user ID $id");
    } elseif($id > 0 && $action == 'reject'){
        $pdo->prepare("UPDATE users SET status = 'rejected'::user_status WHERE id = ?")->execute([$id]);
        logAction('reject_user', "Rejected user ID $id");
    } elseif($id > 0 && $action == 'delete'){
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        logAction('delete_user', "Deleted user ID $id");
    }
    redirect('/admin/users.php');
}

$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary">
    <h2>Manage Users</h2>
</div>

<div class="card">
    <div class="card-body p-0 table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Reg Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $user): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= e($user['name']) ?></td>
                    <td><?= e($user['email']) ?></td>
                    <td><?= e(ucfirst($user['role'])) ?></td>
                    <td>
                        <?php if($user['status']=='approved') echo '<span class="badge bg-success">Approved</span>'; ?>
                        <?php if($user['status']=='pending') echo '<span class="badge bg-warning text-dark">Pending</span>'; ?>
                        <?php if($user['status']=='rejected') echo '<span class="badge bg-danger">Rejected</span>'; ?>
                    </td>
                    <td><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                    <td>
                        <?php if($user['status'] != 'approved'): ?>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <input type="hidden" name="user_action" value="approve">
                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                            </form>
                        <?php endif; ?>
                        <?php if($user['status'] != 'rejected'): ?>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <input type="hidden" name="user_action" value="reject">
                                <button type="submit" class="btn btn-sm btn-warning text-dark">Reject</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" class="d-inline ms-2" onsubmit="return confirm('Are you sure you want to delete this user?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                            <input type="hidden" name="user_action" value="delete">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($users)): ?>
                <tr><td colspan="7" class="text-center py-4">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
