<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../init_session.php';
require_once __DIR__ . '/../../config.php';

// Keep AJAX responses valid JSON even when storage or database work fails.
set_exception_handler(static function (Throwable $exception): void {
    error_log('Map V2 photo upload failed: ' . $exception->getMessage());
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'The photo could not be stored.']);
});
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'], true)) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Administrator access is required.']); exit; }
$id = filter_input(INPUT_POST, 'compartment_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$category = $_POST['category'] ?? 'other';
$allowed = ['planned','planted','monitored','low_survival','completed','other'];
if (!$id || !in_array($category, $allowed, true) || empty($_FILES['photos']['name'])) { http_response_code(422); echo json_encode(['success' => false, 'error' => 'Choose a compartment, category, and at least one valid image.']); exit; }
$exists = $conn->prepare('SELECT id FROM reforestation_compartments WHERE id = ? AND archived = 0'); $exists->bind_param('i',$id); $exists->execute(); if (!$exists->get_result()->fetch_assoc()) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'Compartment not found.']); exit; }
$root = dirname(__DIR__, 2) . '/uploads/compartment_photos/' . date('Y/m');
if (!is_dir($root) && !mkdir($root, 0755, true) && !is_dir($root)) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'Photo storage is unavailable.']); exit; }
$finfo = new finfo(FILEINFO_MIME_TYPE); $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null; $saved=[];
foreach ((array) $_FILES['photos']['name'] as $key => $original) {
  $tmp = $_FILES['photos']['tmp_name'][$key] ?? ''; $size=(int)($_FILES['photos']['size'][$key]??0); $error=(int)($_FILES['photos']['error'][$key]??UPLOAD_ERR_NO_FILE);
  if ($error !== UPLOAD_ERR_OK || $size < 1 || $size > 10*1024*1024 || !is_uploaded_file($tmp)) continue;
  $mime=$finfo->file($tmp); $ext=['image/jpeg'=>'jpg','image/png'=>'png'][$mime]??null; if (!$ext) continue;
  $safeName=trim(basename((string)$original)); $stored=bin2hex(random_bytes(16)).'.'.$ext; $relative='uploads/compartment_photos/'.date('Y/m').'/'.$stored;
  if (!move_uploaded_file($tmp, $root.'/'.$stored)) continue;
  $insert=$conn->prepare('INSERT INTO compartment_photos (compartment_id, uploaded_by, category, storage_path, original_filename, display_name, mime_type, file_size_bytes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
  $insert->bind_param('iisssssi',$id,$userId,$category,$relative,$safeName,$safeName,$mime,$size); $insert->execute(); $photoId=$insert->insert_id; $insert->close();
  $saved[]=['id'=>(string)$photoId,'name'=>$safeName,'uploadedBy'=>trim(($_SESSION['first_name']??'').' '.($_SESSION['last_name']??'')) ?: 'Administrator','category'=>ucwords(str_replace('_',' ',$category)),'size'=>round($size/1048576,1).' MB','uploadedAt'=>date('Y-m-d'),'path'=>$relative];
}
if (!$saved) { http_response_code(422); echo json_encode(['success'=>false,'error'=>'Only PNG or JPG images up to 10 MB can be uploaded.']); exit; }
echo json_encode(['success'=>true,'photos'=>$saved]);
