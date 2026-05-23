<?php
require_once '../config.php';

if(!isset($_SESSION['user_id'])){
    http_response_code(401);
    exit;
}

if (!table_exists('watch_sessions')) {
    http_response_code(204);
    exit;
}

$watchSessionId = (int)(($_POST['watch_session_id'] ?? $_GET['watch_session_id'] ?? 0));
if($watchSessionId <= 0){
    http_response_code(400);
    exit;
}

// Verify this watch session belongs to this user
$stmt = $pdo->prepare("SELECT id FROM watch_sessions WHERE id = ? AND user_id = ? AND ended_at IS NULL");
$stmt->execute([$watchSessionId, $_SESSION['user_id']]);
if(!$stmt->fetch()){
    http_response_code(403);
    exit;
}

if(isset($_GET['end'])){
    $pdo->prepare("UPDATE watch_sessions SET ended_at = NOW() WHERE id = ?")
        ->execute([$watchSessionId]);
} else {
    $pdo->prepare("UPDATE watch_sessions SET last_ping = NOW() WHERE id = ?")
        ->execute([$watchSessionId]);
}

http_response_code(204);
