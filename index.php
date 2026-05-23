<?php
require_once 'config.php';

// Fetch live and upcoming events
$stmt = $pdo->query("SELECT * FROM events WHERE status IN ('live', 'upcoming') ORDER BY schedule_date ASC LIMIT 6");
$events = $stmt->fetchAll();

$pageTitle = 'StreamHub | Community Live Events';
$pageDescription = 'Watch trusted community live streams with secure access, smooth playback, and upcoming event schedules.';

require_once 'includes/header.php';
?>

<div class="row mb-5 text-center mt-4">
    <div class="col-md-8 mx-auto">
        <h1 class="display-4 fw-bold mb-3">Welcome to StreamHub</h1>
        <p class="lead text-muted">Join our exclusive community live streaming platform. Watch live events from anywhere.</p>
        <?php if(!isset($_SESSION['user_id'])): ?>
            <a href="register.php" class="btn btn-primary btn-lg mt-3 px-5 shadow-sm">Join Now</a>
            <p class="mt-3 text-muted small">Access requires admin approval</p>
        <?php else: ?>
            <a href="user/dashboard.php" class="btn btn-primary btn-lg mt-3 px-5 shadow-sm">Go to Dashboard</a>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-4">
    <h3 class="mb-0">Latest & Upcoming Events</h3>
</div>

<div class="row">
    <?php if(count($events) > 0): ?>
        <?php foreach($events as $event): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <?php if($event['thumbnail']): ?>
                        <img src="<?= BASE_URL ?>/assets/images/<?= e($event['thumbnail']) ?>" class="card-img-top thumbnail-img" alt="Thumbnail">
                    <?php else: ?>
                        <div class="bg-light thumbnail-img d-flex align-items-center justify-content-center">
                            <span class="text-muted">No Thumbnail</span>
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <h5 class="card-title fw-bold"><?= e($event['title']) ?></h5>
                        <p class="card-text text-muted small mb-3">
                            <i class="far fa-calendar-alt"></i> <?= date('F j, Y, g:i a', strtotime($event['schedule_date'])) ?>
                        </p>
                        
                        <?php if($event['status'] == 'live'): ?>
                            <span class="badge badge-live mb-3 px-3 py-2">LIVE NOW</span>
                        <?php else: ?>
                            <span class="badge bg-secondary mb-3 px-3 py-2 text-white">Upcoming</span>
                            <div class="countdown-chip mb-3">
                                Starts in <strong data-countdown-target="<?= e(gmdate('c', strtotime($event['schedule_date']))) ?>">Calculating...</strong>
                            </div>
                        <?php endif; ?>
                        
                        <p class="card-text text-secondary"><?= e(substr($event['description'], 0, 100)) ?>...</p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-light border text-center py-5">
                <p class="mb-0 text-muted">No events scheduled at the moment.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
