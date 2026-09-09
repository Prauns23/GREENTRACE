<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../init_session.php';
require_once __DIR__ . '/../config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function speciesReply(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    speciesReply(405, ['error' => 'Method not allowed.']);
}
$userId = (int)($_SESSION['user_id'] ?? 0);
$auth = $conn->prepare('SELECT role FROM users_tbl WHERE id = ? AND archived = 0');
$auth->bind_param('i', $userId);
$auth->execute();
$role = $auth->get_result()->fetch_assoc()['role'] ?? '';
if (!in_array($role, ['admin', 'super_admin'], true)) {
    speciesReply(403, ['error' => 'Administrator access is required.']);
}
function speciesText(string $key, int $max, bool $required = true): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) speciesReply(422, ['error' => 'Invalid field: ' . $key]);
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value) > $max) {
        speciesReply(422, ['error' => 'Please check the ' . str_replace('_', ' ', $key) . ' field.']);
    }
    return $value;
}
$action = speciesText('action', 10);
if (!in_array($action, ['add', 'edit', 'archive', 'restore'], true)) speciesReply(422, ['error' => 'Invalid action.']);
$id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
if ($action !== 'add' && (!$id || $id < 1)) speciesReply(422, ['error' => 'Invalid species.']);
$newFile = null;
try {
    $imageUrl = null;
    if (in_array($action, ['add', 'edit'], true)) {
        $name = speciesText('name', 100);
        $scientific = speciesText('scientific_name', 150);
        $category = speciesText('category', 20);
        $description = speciesText('description', 10000);
        $importance = preg_replace('/^\s*•\s?/m', '', speciesText('importance', 10000));
        $fact = speciesText('fun_fact', 2000, false);
        if (!in_array($category, ['native', 'introduced'], true)) speciesReply(422, ['error' => 'Choose Native or Introduced.']);
        if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['image'];
            if (!is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                speciesReply(422, ['error' => 'The upload failed. Please select a PNG or JPG within the server upload limit.']);
            }
            if ($file['size'] > 10 * 1024 * 1024) speciesReply(422, ['error' => 'Images must be 10 MB or smaller.']);
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $dimensions = @getimagesize($file['tmp_name']);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true) || !$dimensions || $dimensions['mime'] !== $mime) {
                speciesReply(422, ['error' => 'Upload a valid PNG or JPG image.']);
            }
            $directory = __DIR__ . '/../uploads/species';
            if (!is_dir($directory) && !mkdir($directory, 0755, true)) throw new RuntimeException('Cannot create image directory.');
            $filename = bin2hex(random_bytes(16)) . ($mime === 'image/png' ? '.png' : '.jpg');
            $newFile = $directory . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $newFile)) throw new RuntimeException('Cannot save image.');
            $imageUrl = 'uploads/species/' . $filename;
        }
    }
    $conn->begin_transaction();
    if ($action !== 'add') {
        $lookup = $conn->prepare('SELECT id, archived FROM tree_species WHERE id = ? FOR UPDATE');
        $lookup->bind_param('i', $id); $lookup->execute();
        $current = $lookup->get_result()->fetch_assoc();
        if (!$current) throw new DomainException('Species not found.', 404);
        if ($action === 'edit' && $current['archived']) throw new DomainException('Restore this species before editing it.', 409);
    }
    if ($action === 'add') {
        $stmt = $conn->prepare('INSERT INTO tree_species (name, scientific_name, category, description, importance, fun_fact, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssssss', $name, $scientific, $category, $description, $importance, $fact, $imageUrl);
    } elseif ($action === 'edit') {
        // Update only editorial fields. Preserve AR dimensions, colors and spacing.
        $stmt = $conn->prepare('UPDATE tree_species SET name=?, scientific_name=?, category=?, description=?, importance=?, fun_fact=?, image_url=COALESCE(?, image_url) WHERE id=?');
        $stmt->bind_param('sssssssi', $name, $scientific, $category, $description, $importance, $fact, $imageUrl, $id);
    } else {
        $sql = $action === 'archive' ? 'UPDATE tree_species SET archived=1, archived_at=COALESCE(archived_at, NOW()) WHERE id=?' : 'UPDATE tree_species SET archived=0, archived_at=NULL WHERE id=?';
        $stmt = $conn->prepare($sql); $stmt->bind_param('i', $id);
    }
    $stmt->execute();
    if ($action === 'add') $id = $conn->insert_id;
    $conn->commit();
    speciesReply(200, ['success' => true, 'id' => $id, 'message' => 'Species ' . ['add'=>'added', 'edit'=>'updated', 'archive'=>'archived', 'restore'=>'restored'][$action] . '.']);
} catch (Throwable $e) {
    $conn->rollback();
    if ($newFile && is_file($newFile)) unlink($newFile);
    if ($e instanceof DomainException) speciesReply($e->getCode(), ['error' => $e->getMessage()]);
    error_log('Species update failed: ' . $e->getMessage());
    speciesReply(500, ['error' => 'Could not save the species. Please try again.']);
}
