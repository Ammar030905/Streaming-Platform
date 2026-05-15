<?php
require_once 'config.php';

$galleryNotice = '';
$images = [];

// Fetch gallery images safely so a missing table does not crash the page.
try {
    $stmt = $pdo->query("SELECT * FROM gallery ORDER BY created_at DESC");
    $images = $stmt->fetchAll();
} catch (PDOException $e) {
    $isMissingGalleryTable = ($e->getCode() === '42P01');
    if ($isMissingGalleryTable) {
        $galleryNotice = 'Gallery is not initialized yet. Import database.sql to create the gallery table.';
    } else {
        throw $e;
    }
}

require_once 'includes/header.php';
?>

<div class="row mb-5 text-center mt-4">
    <div class="col-md-8 mx-auto">
        <h1 class="display-4 fw-bold mb-3">Gallery</h1>
        <p class="lead text-muted">Moments captured from our community. A glimpse of harmony and peace.</p>
    </div>
</div>

<div class="container mb-5">
    <?php if($galleryNotice): ?>
        <div class="alert alert-warning text-center">
            <?= e($galleryNotice) ?>
        </div>
    <?php endif; ?>

    <?php if(count($images) > 0): ?>
        <div class="gallery-grid">
            <?php foreach($images as $image): ?>
                <div class="gallery-item shadow-sm">
                    <img src="<?= BASE_URL ?>/assets/images/gallery/<?= e($image['image_path']) ?>" alt="<?= e($image['title']) ?>" loading="lazy">
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-light border text-center py-5">
                <p class="mb-0 text-muted">No images found in the gallery yet.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
