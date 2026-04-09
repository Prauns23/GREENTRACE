<?php
require_once '../init_session.php';
require_once '../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized');
}

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$report_id) {
    die('Invalid report ID');
}

// Fetch report details with user info
$stmt = $conn->prepare("
    SELECT r.*, 
           CONCAT(u.fname, ' ', u.lname) as user_fullname,
           u.email as user_email,
           u.phone_no as user_phone
    FROM reports r
    LEFT JOIN users_tbl u ON r.user_id = u.id
    WHERE r.id = ?
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    die('Report not found');
}

// Fetch photos
$photoStmt = $conn->prepare("SELECT file_path, original_name FROM report_photos WHERE report_id = ?");
$photoStmt->bind_param("i", $report_id);
$photoStmt->execute();
$photos = $photoStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$photoStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="report_details.css">
</head>
<body>
    <div class="modal-container">
        <div class="modal-header">
            <h2><?php echo htmlspecialchars($report['issue_type']); ?></h2>
            <p>See the details of the report below</p>
        </div>
        <div class="modal-body">
            <!-- Status Dropdown (Editable) -->
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="pending" <?php echo $report['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="reviewed" <?php echo $report['status'] == 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                    <option value="resolved" <?php echo $report['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="dismissed" <?php echo $report['status'] == 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
                </select>
            </div>

            <!-- Reporter Full Name -->
            <div class="form-group">
                <label>Full Name</label>
                <div class="display-value">
                    <?php if ($report['anonymous']): ?>
                        <em><i class="fas fa-user-secret"></i> Anonymous</em>
                    <?php else: ?>
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($report['user_fullname'] ?: 'Unknown'); ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label>Email Address</label>
                <div class="display-value">
                    <?php if ($report['anonymous']): ?>
                        <em>Hidden</em>
                    <?php else: ?>
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($report['user_email'] ?: 'No email'); ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Phone Number -->
            <div class="form-group">
                <label>Phone Number</label>
                <div class="display-value">
                    <?php if ($report['anonymous']): ?>
                        <em>Hidden</em>
                    <?php else: ?>
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($report['user_phone'] ?: 'Not provided'); ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Coordinates -->
            <div class="form-row">
                <div class="form-group half">
                    <label>Latitude</label>
                    <div class="display-value"><?php echo htmlspecialchars($report['latitude']); ?></div>
                </div>
                <div class="form-group half">
                    <label>Longitude</label>
                    <div class="display-value"><?php echo htmlspecialchars($report['longitude']); ?></div>
                </div>
            </div>

            <!-- Date Reported -->
            <div class="form-group">
                <label>Date Reported</label>
                <div class="display-value"><?php echo date('F j, Y g:i A', strtotime($report['created_at'])); ?></div>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label>Description</label>
                <div class="display-value description-text"><?php echo nl2br(htmlspecialchars($report['description'])); ?></div>
            </div>

            <!-- Photos Section -->
            <?php if (!empty($photos)): ?>
            <div class="form-group">
                <label>Evidence Photos (<?php echo count($photos); ?>)</label>
                <div class="photos-gallery">
                    <?php foreach ($photos as $photo): ?>
                    <div class="photo-thumb" onclick="openImageModal('<?php echo '../' . htmlspecialchars($photo['file_path']); ?>')">
                        <img src="<?php echo '../' . htmlspecialchars($photo['file_path']); ?>" alt="<?php echo htmlspecialchars($photo['original_name']); ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer Buttons -->
        <div class="button-group">
            <button class="btn-cancel" type="button" onclick="parent.hideFloating()">Cancel</button>
            <button class="btn-submit" type="button" id="saveStatusBtn">Save Changes</button>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal" onclick="closeImageModal()">
        <span class="image-modal-close">&times;</span>
        <img class="image-modal-content" id="modalImage">
    </div>

    <script src="report_details.js"></script>
</body>
</html>