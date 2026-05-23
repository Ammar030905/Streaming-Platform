<?php
require_once '../config.php';

if(!isset($_SESSION['admin_id'])){
    redirect('/admin/login.php');
}

// Fetch stats
$users_total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$users_pending = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
$events_total = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$streams_live = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'live'")->fetchColumn();

// Fetch recent lists
$recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recent_events = $pdo->query("SELECT * FROM events ORDER BY created_at DESC LIMIT 5")->fetchAll();

$liveViewerSessions = 0;
$avgWatchMinutes = 0;
$loginsToday = 0;

if (table_exists('watch_sessions')) {
    $liveViewerSessions = (int) $pdo->query("SELECT COUNT(*) FROM watch_sessions WHERE ended_at IS NULL AND last_ping > NOW() - INTERVAL '90 seconds'")->fetchColumn();

    $avgDurationSeconds = (float) $pdo->query("SELECT COALESCE(AVG(EXTRACT(EPOCH FROM (COALESCE(ended_at, NOW()) - started_at))), 0) FROM watch_sessions")->fetchColumn();
    $avgWatchMinutes = (int) round($avgDurationSeconds / 60);
}

$loginsToday = (int) $pdo->query("SELECT COUNT(*) FROM logs WHERE action IN ('user_login','admin_login') AND created_at::date = CURRENT_DATE")->fetchColumn();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary">
    <h2>Admin Dashboard</h2>
    <span>Signed in as <strong><?= e($_SESSION['admin_name']) ?></strong></span>
</div>

<div class="row mb-5">
    <div class="col-md-3">
        <div class="card bg-primary text-white text-center p-3 h-100 border-0">
            <h3><?= $users_total ?></h3>
            <p class="mb-0">Total Users</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark text-center p-3 h-100 border-0">
            <h3><?= $users_pending ?></h3>
            <p class="mb-0">Pending Users</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-dark text-center p-3 h-100 border-0">
            <h3><?= $events_total ?></h3>
            <p class="mb-0">Total Events</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white text-center p-3 h-100 border-0">
            <h3><?= $streams_live ?></h3>
            <p class="mb-0">Live Streams</p>
        </div>
    </div>
</div>

<div class="metric-grid mb-4">
    <div class="metric-card">
        <p class="metric-value"><?= $liveViewerSessions ?></p>
        <p class="metric-label">Active Viewers (Last 90s)</p>
    </div>
    <div class="metric-card">
        <p class="metric-value"><?= $avgWatchMinutes ?></p>
        <p class="metric-label">Avg Watch Duration (min)</p>
    </div>
    <div class="metric-card">
        <p class="metric-value"><?= $loginsToday ?></p>
        <p class="metric-label">Logins Today</p>
    </div>
    <div class="metric-card">
        <p class="metric-value"><?= date('H:i') ?></p>
        <p class="metric-label">Dashboard Refreshed At</p>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recent Users</span>
                <a href="users.php" class="btn btn-sm btn-outline-light">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-dark table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_users as $u): ?>
                        <tr>
                            <td><?= e($u['name']) ?></td>
                            <td>
                                <?php if($u['status']=='approved') echo '<span class="badge bg-success">Approved</span>'; ?>
                                <?php if($u['status']=='pending') echo '<span class="badge bg-warning text-dark">Pending</span>'; ?>
                                <?php if($u['status']=='rejected') echo '<span class="badge bg-danger">Rejected</span>'; ?>
                            </td>
                            <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($recent_users)) echo '<tr><td colspan="3" class="text-center">No users found.</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recent Events</span>
                <a href="events.php" class="btn btn-sm btn-outline-light">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-dark table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Schedule Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_events as $ev): ?>
                        <tr>
                            <td><?= e($ev['title']) ?></td>
                            <td>
                                <?php if($ev['status']=='upcoming') echo '<span class="badge bg-secondary">Upcoming</span>'; ?>
                                <?php if($ev['status']=='live') echo '<span class="badge bg-danger">Live</span>'; ?>
                                <?php if($ev['status']=='ended') echo '<span class="badge bg-dark text-light border">Ended</span>'; ?>
                            </td>
                            <td><?= date('M j, Y', strtotime($ev['schedule_date'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($recent_events)) echo '<tr><td colspan="3" class="text-center">No events found.</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
