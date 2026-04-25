<?php
require_once '../config.php';

if(!isset($_SESSION['admin_id'])){
    redirect('/admin/login.php');
}

$msg = '';

// Handle Create Event
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_event'])){
    verify_csrf_or_fail();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $schedule_date = $_POST['schedule_date'] ?? '';

    if ($title === '' || $schedule_date === '') {
        $msg = 'Event title and schedule date are required.';
    } elseif (strtotime($schedule_date) === false) {
        $msg = 'Invalid schedule date.';
    } else {
        $thumbnail = null;
        if(isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK){
            $maxFileSize = 3 * 1024 * 1024;
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
            $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            $mime = mime_content_type($_FILES['thumbnail']['tmp_name']);

            if (!in_array($ext, $allowedExtensions, true) || !in_array($mime, $allowedMime, true)) {
                $msg = 'Invalid thumbnail format. Allowed: jpg, jpeg, png, gif, webp.';
            } elseif ((int) $_FILES['thumbnail']['size'] > $maxFileSize) {
                $msg = 'Thumbnail exceeds 3MB size limit.';
            } else {
                $thumbnail = bin2hex(random_bytes(12)) . '.' . $ext;
                if(!is_dir('../assets/images')) {
                    mkdir('../assets/images', 0755, true);
                }

                if (!move_uploaded_file($_FILES['thumbnail']['tmp_name'], '../assets/images/' . $thumbnail)) {
                    $msg = 'Failed to upload thumbnail.';
                }
            }
        }

        if ($msg === '') {
            $stmt = $pdo->prepare("INSERT INTO events (title, description, schedule_date, thumbnail) VALUES (?, ?, ?, ?)");
            if($stmt->execute([$title, $description, $schedule_date, $thumbnail])){
                $event_id = $pdo->lastInsertId();
                $stream_key = 'live_' . bin2hex(random_bytes(10));
                $pdo->prepare("INSERT INTO streams (event_id, stream_key) VALUES (?, ?)")->execute([$event_id, $stream_key]);

                logAction('create_event', "Created event ID $event_id");
                $msg = 'Event created successfully!';
            }
        }
    }
}

// Handle Actions (Start/Stop stream, Delete event)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['event_action'])){
    verify_csrf_or_fail();

    $id = (int)($_POST['event_id'] ?? 0);
    $action = $_POST['event_action'] ?? '';
    
    if($id > 0 && $action == 'start'){
        $pdo->prepare("UPDATE events SET status = 'live' WHERE id = ?")->execute([$id]);
        $pdo->prepare("UPDATE streams SET status = 'online', started_at = NOW(), ended_at = NULL WHERE event_id = ?")->execute([$id]);
        logAction('stream_start', "Started stream for event ID $id");
    } elseif($id > 0 && $action == 'stop'){
        $pdo->prepare("UPDATE events SET status = 'ended' WHERE id = ?")->execute([$id]);
        $pdo->prepare("UPDATE streams SET status = 'offline', ended_at = NOW() WHERE event_id = ?")->execute([$id]);
        logAction('stream_stop', "Stopped stream for event ID $id");
    } elseif($id > 0 && $action == 'delete'){
        $pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$id]);
        logAction('delete_event', "Deleted event ID $id");
    }
    redirect('/admin/events.php');
}

// Fetch events with stream key
$stmt = $pdo->query("SELECT e.*, s.stream_key FROM events e LEFT JOIN streams s ON e.id = s.event_id ORDER BY e.created_at DESC");
$events = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary">
    <h2>Manage Events</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEventModal">+ Create New Event</button>
</div>

<?php if($msg): ?>
    <div class="alert alert-<?= strpos($msg, 'successfully') !== false ? 'success' : 'danger' ?>"><?= e($msg) ?></div>
<?php endif; ?>

<div class="row">
    <?php foreach($events as $event): ?>
    <div class="col-md-6 mb-4">
        <div class="card h-100 <?= $event['status'] == 'live' ? 'border-danger' : '' ?>">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><?= e($event['title']) ?></span>
                <?php if($event['status'] == 'upcoming') echo '<span class="badge bg-secondary">Upcoming</span>'; ?>
                <?php if($event['status'] == 'live') echo '<span class="badge badge-live px-2 py-1">LIVE NOW</span>'; ?>
                <?php if($event['status'] == 'ended') echo '<span class="badge bg-dark border">Ended</span>'; ?>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-4">
                        <?php if($event['thumbnail']): ?>
                            <img src="<?= BASE_URL ?>/assets/images/<?= e($event['thumbnail']) ?>" class="img-fluid rounded" alt="Thumbnail">
                        <?php else: ?>
                            <div class="bg-dark text-muted d-flex align-items-center justify-content-center rounded" style="height: 100px;">No Image</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-8">
                        <p class="mb-1"><strong>Schedule:</strong> <?= date('M j, Y H:i', strtotime($event['schedule_date'])) ?></p>
                        <hr class="border-secondary my-2">
                        <p class="mb-1 text-info small"><strong>RTMP URL:</strong> rtmp://your-server-ip/live</p>
                        <p class="mb-1 text-warning small"><strong>Stream Key:</strong> <?= e($event['stream_key'] ?? 'N/A') ?></p>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-secondary d-flex justify-content-between">
                <div>
                    <?php if($event['status'] == 'upcoming'): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Start the live stream now?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                            <input type="hidden" name="event_action" value="start">
                            <button type="submit" class="btn btn-sm btn-success">Start Stream</button>
                        </form>
                    <?php elseif($event['status'] == 'live'): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Stop and end the stream?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                            <input type="hidden" name="event_action" value="stop">
                            <button type="submit" class="btn btn-sm btn-danger">Stop Stream</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this event?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                        <input type="hidden" name="event_action" value="delete">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($events)): ?>
        <div class="col-12"><div class="alert alert-dark text-center">No events found.</div></div>
    <?php endif; ?>
</div>

<!-- Create Event Modal -->
<div class="modal fade" id="createEventModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-light border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">Create New Event</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
          <div class="modal-body">
                <input type="hidden" name="create_event" value="1">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label>Event Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label>Schedule Date & Time</label>
                    <input type="datetime-local" name="schedule_date" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Thumbnail (Optional)</label>
                    <input type="file" name="thumbnail" class="form-control" accept="image/*">
                </div>
          </div>
          <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Create Event</button>
          </div>
      </form>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
