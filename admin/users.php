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
    } elseif($id > 0 && $action == 'force_logout'){
        $pdo->prepare("UPDATE users SET session_token = NULL, last_logout_at = NOW() WHERE id = ?")->execute([$id]);
        $pdo->prepare("UPDATE watch_sessions SET ended_at = NOW() WHERE user_id = ? AND ended_at IS NULL")->execute([$id]);
        logAction('force_logout', "Force-logged out user ID $id");
    }
    redirect('/admin/users.php');
}

// Fetch all users with their current watch session (if any)
$users = $pdo->query("
    SELECT
        u.*,
        ws.stream_id        AS watching_stream_id,
        ws.last_ping        AS watching_last_ping,
        e.title             AS watching_event_title,
        e.id                AS watching_event_id,
        ws.ip_address       AS watch_ip,
        ws.started_at       AS watch_started_at,
        (SELECT COUNT(*) FROM watch_sessions w2 WHERE w2.user_id = u.id) AS total_watches
    FROM users u
    LEFT JOIN watch_sessions ws
        ON ws.user_id = u.id
        AND ws.ended_at IS NULL
        AND ws.last_ping > NOW() - INTERVAL '90 seconds'
    LEFT JOIN events e ON e.id = ws.event_id
    ORDER BY u.created_at DESC
")->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary">
    <h2>Manage Users <span class="badge bg-secondary fs-6"><?= count($users) ?></span></h2>
</div>

<div class="card">
    <div class="card-body p-0 table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle small">
            <thead class="table-secondary text-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Last Logout</th>
                    <th>Last IP</th>
                    <th>Device</th>
                    <th>Logins</th>
                    <th>Watches</th>
                    <th>Now Watching</th>
                    <th>Password Hash</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $user): ?>
                <?php $isOnline = !empty($user['watching_stream_id']); ?>
                <tr class="<?= $isOnline ? 'table-danger' : '' ?>">
                    <td><?= $user['id'] ?></td>
                    <td>
                        <a href="user_detail.php?id=<?= (int)$user['id'] ?>" class="text-info text-decoration-none fw-bold">
                            <?= e($user['name']) ?>
                        </a>
                    </td>
                    <td><?= e($user['email']) ?></td>
                    <td>
                        <?php if($user['status']=='approved') echo '<span class="badge bg-success">Approved</span>'; ?>
                        <?php if($user['status']=='pending')  echo '<span class="badge bg-warning text-dark">Pending</span>'; ?>
                        <?php if($user['status']=='rejected') echo '<span class="badge bg-danger">Rejected</span>'; ?>
                        <?php if($user['session_token'])      echo '<span class="badge bg-info text-dark ms-1">Online</span>'; ?>
                    </td>
                    <td><?= $user['last_login_at']  ? date('Y-m-d H:i', strtotime($user['last_login_at']))  : '<span class="text-muted">Never</span>' ?></td>
                    <td><?= $user['last_logout_at'] ? date('Y-m-d H:i', strtotime($user['last_logout_at'])) : '<span class="text-muted">Never</span>' ?></td>
                    <td><code><?= e($user['last_ip'] ?? '—') ?></code></td>
                    <td>
                        <?php if($user['last_user_agent']): ?>
                            <span title="<?= e($user['last_user_agent']) ?>" style="cursor:help;">
                                <?php
                                    $ua = $user['last_user_agent'];
                                    if(str_contains($ua,'Mobile'))       echo '📱 Mobile';
                                    elseif(str_contains($ua,'Tablet'))   echo '📟 Tablet';
                                    else                                  echo '🖥️ Desktop';
                                ?>
                            </span>
                        <?php else: echo '<span class="text-muted">—</span>'; endif; ?>
                    </td>
                    <td><?= (int)$user['login_count'] ?></td>
                    <td><?= (int)$user['total_watches'] ?></td>
                    <td>
                        <?php if($isOnline): ?>
                            <span class="badge badge-live">
                                🔴 <?= e($user['watching_event_title']) ?>
                            </span>
                            <div class="text-muted" style="font-size:0.7rem;">
                                since <?= date('H:i', strtotime($user['watch_started_at'])) ?>
                                · ping <?= date('H:i:s', strtotime($user['watching_last_ping'])) ?>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code style="font-size:0.65rem;word-break:break-all;" title="Bcrypt hash — not reversible">
                            <?= e(substr($user['password'], 0, 30)) ?>…
                        </code>
                    </td>
                    <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            <?php if($user['status'] != 'approved'): ?>
                                <form method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                    <input type="hidden" name="user_action" value="approve">
                                    <button class="btn btn-sm btn-success">Approve</button>
                                </form>
                            <?php endif; ?>
                            <?php if($user['status'] != 'rejected'): ?>
                                <form method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                    <input type="hidden" name="user_action" value="reject">
                                    <button class="btn btn-sm btn-warning text-dark">Reject</button>
                                </form>
                            <?php endif; ?>
                            <?php if($user['session_token']): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Force logout this user?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                    <input type="hidden" name="user_action" value="force_logout">
                                    <button class="btn btn-sm btn-danger">⏏ Kick</button>
                                </form>
                            <?php endif; ?>
                            <a href="user_detail.php?id=<?= (int)$user['id'] ?>" class="btn btn-sm btn-outline-info">Detail</a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user permanently?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <input type="hidden" name="user_action" value="delete">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($users)): ?>
                <tr><td colspan="14" class="text-center py-4">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
