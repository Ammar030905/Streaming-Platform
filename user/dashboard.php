<?php
require_once '../config.php';

if(!isset($_SESSION['user_id'])){
    redirect('/login.php');
}

// Fetch events
$stmt = $pdo->query("SELECT * FROM events WHERE status != 'ended' ORDER BY status ASC, schedule_date ASC");
$events = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
    <h2>User Dashboard</h2>
    <span>Welcome, <strong><?= e($_SESSION['user_name']) ?></strong></span>
</div>

<div class="row">
    <div class="col-md-12 mb-4">
        <h4>Live & Upcoming Streams</h4>
    </div>
    
    <?php if(count($events) > 0): ?>
        <?php foreach($events as $event): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 <?= $event['status'] == 'live' ? 'border-danger' : '' ?>">
                    <?php if($event['thumbnail']): ?>
                        <img src="<?= BASE_URL ?>/assets/images/<?= e($event['thumbnail']) ?>" class="card-img-top thumbnail-img" alt="Thumbnail">
                    <?php else: ?>
                        <div class="bg-dark thumbnail-img d-flex align-items-center justify-content-center">
                            <span class="text-muted">No Thumbnail</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h5 class="card-title"><?= e($event['title']) ?></h5>
                        <p class="card-text text-muted small">Scheduled: <?= date('F j, Y, g:i a', strtotime($event['schedule_date'])) ?></p>
                        
                        <?php if($event['status'] == 'live'): ?>
                            <div class="mb-3">
                                <span class="badge badge-live px-3 py-2">LIVE NOW</span>
                            </div>
                            <a href="<?= BASE_URL ?>/user/watch.php?id=<?= $event['id'] ?>" class="btn btn-primary w-100">Watch Stream</a>
                        <?php else: ?>
                            <div class="mb-3">
                                <span class="badge bg-secondary">Upcoming</span>
                            </div>
                            <button class="btn btn-outline-secondary w-100" disabled>Not Started Yet</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-secondary bg-dark text-light border-secondary">No streams available at the moment.</div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
