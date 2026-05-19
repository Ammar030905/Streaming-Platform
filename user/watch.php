<?php
require_once '../config.php';

if(!isset($_SESSION['user_id'])){
    redirect('/login.php');
}
validate_user_session();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    redirect('/user/dashboard.php');
}

$stmt = $pdo->prepare("SELECT e.*, s.id AS stream_id, s.stream_key FROM events e LEFT JOIN streams s ON e.id = s.event_id WHERE e.id = ?");
$stmt->execute([$id]);
$event = $stmt->fetch();

if(!$event){
    die("Event not found.");
}

// Record watch session (only if stream is live)
$watchSessionId = null;
if($event['status'] === 'live' && $event['stream_id']) {
    // End any previous open watch session for this user on this stream
    $pdo->prepare("UPDATE watch_sessions SET ended_at = NOW() WHERE user_id = ? AND stream_id = ? AND ended_at IS NULL")
        ->execute([$_SESSION['user_id'], $event['stream_id']]);

    $stmt2 = $pdo->prepare("INSERT INTO watch_sessions (user_id, stream_id, event_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?) RETURNING id");
    $stmt2->execute([
        $_SESSION['user_id'],
        $event['stream_id'],
        $event['id'],
        currentClientIp(),
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    $watchSessionId = $stmt2->fetchColumn();
}

require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-md-9 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><?= e($event['title']) ?></h3>
            <?php if($event['status'] == 'live'): ?>
                <span class="badge badge-live px-3 py-2">LIVE</span>
            <?php else: ?>
                <span class="badge bg-secondary px-3 py-2">OFFLINE</span>
            <?php endif; ?>
        </div>
        
        <div class="card mb-4">
            <div class="card-body p-0">
                <?php if($event['status'] == 'live' && !empty($event['stream_key'])): ?>
                    <?php $manifestUrl = rtrim(HLS_BASE_URL, '/') . '/' . rawurlencode($event['stream_key']) . '.m3u8'; ?>
                    <div class="video-container" data-manifest-url="<?= e($manifestUrl) ?>">
                        <video id="stream-player" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="metadata" playsinline webkit-playsinline crossorigin="anonymous" data-setup='{"fluid": true}' data-manifest-url="<?= e($manifestUrl) ?>">
                            <p class="vjs-no-js">To view this video please enable JavaScript.</p>
                        </video>
                        <div class="stream-status alert alert-dark border-0 mt-3 mb-0" role="status">
                            Connecting to the live stream...
                        </div>
                    </div>
                <?php else: ?>
                    <div class="video-container d-flex align-items-center justify-content-center flex-column text-center p-5">
                        <h4 class="text-muted mb-3">Stream is currently offline</h4>
                        <?php if($event['thumbnail']): ?>
                            <img src="<?= BASE_URL ?>/assets/images/<?= e($event['thumbnail']) ?>" alt="Thumbnail" class="img-fluid rounded" style="max-height: 300px; opacity: 0.5;">
                        <?php endif; ?>
                        <p class="mt-3 text-muted">Scheduled for: <?= date('F j, Y, g:i a', strtotime($event['schedule_date'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer pb-3 pt-3">
                <h5>About this stream</h5>
                <p class="text-muted mb-0"><?= nl2br(e($event['description'])) ?></p>
            </div>
        </div>
        
        <a href="dashboard.php" class="btn btn-outline-light mb-5">&larr; Back to Dashboard</a>
    </div>
</div>

<script src="https://vjs.zencdn.net/8.6.1/video.min.js"></script>
<?php if($watchSessionId): ?>
<script>
    const watchSessionId = <?= (int)$watchSessionId ?>;
    const pingUrl = '<?= BASE_URL ?>/user/watch_ping.php';
    setInterval(() => {
        fetch(pingUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'watch_session_id=' + watchSessionId + '&csrf_token=<?= e(csrf_token()) ?>'
        });
    }, 30000);
    window.addEventListener('beforeunload', () => {
        navigator.sendBeacon(pingUrl + '?end=1', new URLSearchParams({
            watch_session_id: watchSessionId,
            csrf_token: '<?= e(csrf_token()) ?>'
        }));
    });
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
