<?php
require_once '../config.php';

if(!isset($_SESSION['admin_id'])){
    redirect('/admin/login.php');
}

$id = (int)($_GET['id'] ?? 0);
if($id <= 0) redirect('/admin/users.php');

$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$id]);
$user = $user->fetch();
if(!$user) redirect('/admin/users.php');

$hasWatchSessions = table_exists('watch_sessions');
$hasSessionToken = column_exists('users', 'session_token');
$hasLastLogin = column_exists('users', 'last_login_at');
$hasLastLogout = column_exists('users', 'last_logout_at');
$hasLoginCount = column_exists('users', 'login_count');
$hasLastIp = column_exists('users', 'last_ip');
$hasUserAgent = column_exists('users', 'last_user_agent');

$watches = [];
if ($hasWatchSessions) {
    $watchesStmt = $pdo->prepare("
        SELECT ws.*, e.title AS event_title, s.stream_key
        FROM watch_sessions ws
        JOIN events e ON e.id = ws.event_id
        JOIN streams s ON s.id = ws.stream_id
        WHERE ws.user_id = ?
        ORDER BY ws.started_at DESC
        LIMIT 100
    ");
    $watchesStmt->execute([$id]);
    $watches = $watchesStmt->fetchAll();
}

// Login logs from logs table
$loginLogs = $pdo->prepare("
    SELECT * FROM logs
    WHERE (action = 'user_login' OR action = 'user_logout' OR action = 'force_logout')
      AND details ILIKE ?
    ORDER BY created_at DESC
    LIMIT 50
");
$loginLogs->execute(['%' . $user['email'] . '%']);
$loginLogs = $loginLogs->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary">
    <h2>User Detail — <?= e($user['name']) ?></h2>
    <a href="users.php" class="btn btn-outline-light btn-sm">&larr; Back to Users</a>
</div>

<div class="row g-4 mb-4">
    <!-- Identity Card -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Identity & Account</div>
            <div class="card-body">
                <table class="table table-dark table-sm mb-0">
                    <tr><th>ID</th><td><?= $user['id'] ?></td></tr>
                    <tr><th>Name</th><td><?= e($user['name']) ?></td></tr>
                    <tr><th>Email</th><td><?= e($user['email']) ?></td></tr>
                    <tr><th>Role</th><td><?= e(ucfirst($user['role'])) ?></td></tr>
                    <tr><th>Status</th><td>
                        <?php if($user['status']=='approved') echo '<span class="badge bg-success">Approved</span>'; ?>
                        <?php if($user['status']=='pending')  echo '<span class="badge bg-warning text-dark">Pending</span>'; ?>
                        <?php if($user['status']=='rejected') echo '<span class="badge bg-danger">Rejected</span>'; ?>
                    </td></tr>
                    <tr><th>Registered</th><td><?= date('Y-m-d H:i:s', strtotime($user['created_at'])) ?></td></tr>
                    <tr><th>Active Session</th><td>
                        <?= ($hasSessionToken && !empty($user['session_token']))
                            ? '<span class="badge bg-info text-dark">Yes — token: <code>' . e(substr($user['session_token'],0,16)) . '…</code></span>'
                            : '<span class="text-muted">No active session</span>' ?>
                    </td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Activity Card -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Activity & Device</div>
            <div class="card-body">
                <table class="table table-dark table-sm mb-0">
                    <tr><th>Last Login</th><td><?= ($hasLastLogin && !empty($user['last_login_at']))  ? date('Y-m-d H:i:s', strtotime($user['last_login_at']))  : '<span class="text-muted">Never</span>' ?></td></tr>
                    <tr><th>Last Logout</th><td><?= ($hasLastLogout && !empty($user['last_logout_at'])) ? date('Y-m-d H:i:s', strtotime($user['last_logout_at'])) : '<span class="text-muted">Never</span>' ?></td></tr>
                    <tr><th>Total Logins</th><td><?= $hasLoginCount ? (int)$user['login_count'] : 0 ?></td></tr>
                    <tr><th>Total Watches</th><td><?= count($watches) ?></td></tr>
                    <tr><th>Last IP</th><td><code><?= e($hasLastIp ? ($user['last_ip'] ?? '—') : '—') ?></code></td></tr>
                    <tr><th>Last Device</th><td style="word-break:break-all;font-size:0.75rem;"><?= e($hasUserAgent ? ($user['last_user_agent'] ?? '—') : '—') ?></td></tr>
                    <tr><th>Password Hash</th><td><code style="font-size:0.7rem;word-break:break-all;"><?= e($user['password']) ?></code></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Force Logout -->
<?php if($hasSessionToken && !empty($user['session_token'])): ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center mb-4">
    <span>⚠️ This user currently has an active session.</span>
    <form method="POST" action="users.php" onsubmit="return confirm('Force logout this user?');">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
        <input type="hidden" name="user_action" value="force_logout">
        <button class="btn btn-danger btn-sm">⏏ Force Logout Now</button>
    </form>
</div>
<?php endif; ?>

<?php if(!$hasWatchSessions): ?>
<div class="alert alert-warning mb-4">
    Watch history is unavailable because the watch_sessions table is missing in this environment.
</div>
<?php endif; ?>

<!-- Watch History -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between">
        <span>Watch History</span>
        <span class="badge bg-secondary"><?= count($watches) ?> sessions</span>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-dark table-hover table-sm mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Event</th>
                    <th>Stream Key</th>
                    <th>Started</th>
                    <th>Last Ping</th>
                    <th>Ended</th>
                    <th>Duration</th>
                    <th>IP</th>
                    <th>Device</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($watches as $i => $w): ?>
                <?php
                    $start = strtotime($w['started_at']);
                    $end   = $w['ended_at'] ? strtotime($w['ended_at']) : null;
                    $dur   = $end ? gmdate('H:i:s', $end - $start) : '<span class="badge badge-live">Live</span>';
                    $ua    = $w['user_agent'] ?? '';
                    $device = str_contains($ua,'Mobile') ? '📱' : (str_contains($ua,'Tablet') ? '📟' : '🖥️');
                ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($w['event_title']) ?></td>
                    <td><code><?= e($w['stream_key']) ?></code></td>
                    <td><?= date('Y-m-d H:i:s', $start) ?></td>
                    <td><?= date('H:i:s', strtotime($w['last_ping'])) ?></td>
                    <td><?= $w['ended_at'] ? date('H:i:s', strtotime($w['ended_at'])) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= $dur ?></td>
                    <td><code><?= e($w['ip_address'] ?? '—') ?></code></td>
                    <td title="<?= e($ua) ?>" style="cursor:help;"><?= $device ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($watches)): ?>
                <tr><td colspan="9" class="text-center py-3 text-muted">No watch history.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Login / Logout Logs -->
<div class="card mb-5">
    <div class="card-header d-flex justify-content-between">
        <span>Login / Logout Log</span>
        <span class="badge bg-secondary"><?= count($loginLogs) ?> entries</span>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-dark table-hover table-sm mb-0">
            <thead>
                <tr><th>Time</th><th>Action</th><th>IP</th><th>Details</th></tr>
            </thead>
            <tbody>
                <?php foreach($loginLogs as $log): ?>
                <tr>
                    <td><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                    <td>
                        <?php
                            $badge = match($log['action']) {
                                'user_login'   => 'bg-success',
                                'user_logout'  => 'bg-secondary',
                                'force_logout' => 'bg-danger',
                                default        => 'bg-dark'
                            };
                        ?>
                        <span class="badge <?= $badge ?>"><?= e($log['action']) ?></span>
                    </td>
                    <td><code><?= e($log['ip_address'] ?? '—') ?></code></td>
                    <td><?= e($log['details']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($loginLogs)): ?>
                <tr><td colspan="4" class="text-center py-3 text-muted">No logs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
