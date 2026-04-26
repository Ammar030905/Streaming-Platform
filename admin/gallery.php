<?php
require_once '../config.php';

if(!isset($_SESSION['admin_id'])){
    redirect('/admin/login.php');
}

$error = '';
$success = '';
$galleryTableExists = false;

try {
    $galleryTableExists = (bool) $pdo->query("SHOW TABLES LIKE 'gallery'")->fetchColumn();
    if (!$galleryTableExists) {
        $error = 'Gallery table is missing. Import database.sql to enable gallery management.';
    }
} catch (PDOException $e) {
    $error = 'Unable to verify gallery setup right now.';
}

// Handle Delete
if($galleryTableExists && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_image'])){
    verify_csrf_or_fail();

    $id = (int)($_POST['image_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT image_path FROM gallery WHERE id = ?");
    $stmt->execute([$id]);
    $image = $stmt->fetch();
    
    if($image){
        $file_path = '../assets/images/gallery/' . $image['image_path'];
        if(file_exists($file_path)){
            unlink($file_path);
        }
        $pdo->prepare("DELETE FROM gallery WHERE id = ?")->execute([$id]);
        $success = 'Image deleted successfully.';
        logAction('delete_gallery_image', "Deleted gallery image ID $id");
        redirect('/admin/gallery.php?success=' . urlencode($success));
    }
}

// Handle Upload
if($galleryTableExists && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_image'])){
    verify_csrf_or_fail();

    $title = trim($_POST['title'] ?? '');
    
    if(isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK){
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 4 * 1024 * 1024;
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = mime_content_type($_FILES['image']['tmp_name']);
        
        if(in_array($ext, $allowed, true) && in_array($mime, $allowedMime, true)){
            if ((int) $_FILES['image']['size'] > $maxFileSize) {
                $error = 'Image size must be 4MB or less.';
            } else {
                $new_name = bin2hex(random_bytes(12)) . '.' . $ext;
                if (!is_dir('../assets/images/gallery')) {
                    mkdir('../assets/images/gallery', 0755, true);
                }
            $destination = '../assets/images/gallery/' . $new_name;
            
            if(move_uploaded_file($_FILES['image']['tmp_name'], $destination)){
                $stmt = $pdo->prepare("INSERT INTO gallery (title, image_path) VALUES (?, ?)");
                $stmt->execute([$title, $new_name]);
                logAction('upload_gallery_image', 'Uploaded gallery image: ' . $new_name);
                $success = 'Image uploaded successfully!';
                redirect('/admin/gallery.php?success=' . urlencode($success));
            } else {
                $error = 'Failed to upload image.';
            }
            }
        } else {
            $error = 'Invalid file format. Allowed: jpg, jpeg, png, gif, webp.';
        }
    } else {
        $error = 'Please select an image to upload.';
    }
}

if(isset($_GET['success'])){
    $success = htmlspecialchars($_GET['success']);
}

// Fetch Gallery Images
$images = [];
if ($galleryTableExists) {
    $images = $pdo->query("SELECT * FROM gallery ORDER BY created_at DESC")->fetchAll();
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary">
    <h2>Manage Gallery</h2>
</div>

<?php if($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<?php if($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="row mb-5">
    <div class="col-md-5">
        <div class="card bg-dark text-white shadow">
            <div class="card-header border-bottom border-secondary">
                Upload New Image
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="upload_image" value="1">
                    <div class="mb-3">
                        <label class="form-label">Image Title/Caption (Optional)</label>
                        <input type="text" name="title" class="form-control bg-secondary text-white border-0" <?= $galleryTableExists ? '' : 'disabled' ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Image</label>
                        <input type="file" name="image" class="form-control bg-secondary text-white border-0" <?= $galleryTableExists ? 'required' : 'disabled' ?> accept="image/*">
                    </div>
                    <button type="submit" class="btn btn-primary w-100" <?= $galleryTableExists ? '' : 'disabled' ?>>Upload Image</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card bg-dark text-white shadow">
            <div class="card-header border-bottom border-secondary">
                Gallery Images
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Preview</th>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($images as $img): ?>
                            <tr>
                                <td>
                                    <img src="<?= BASE_URL ?>/assets/images/gallery/<?= e($img['image_path']) ?>" alt="img" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                                </td>
                                <td><?= e($img['title']) ?></td>
                                <td><?= date('M j, Y', strtotime($img['created_at'])) ?></td>
                                <td>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this image?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
                                        <input type="hidden" name="delete_image" value="1">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($images)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No images in gallery</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
