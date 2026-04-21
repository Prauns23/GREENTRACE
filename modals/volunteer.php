<?php
require_once '../init_session.php';
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$user_id = $_SESSION['user_id'];
$activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
if (!$activity_id) {
    die('No activity specified.');
}

// Check if user already has a pending/approved application for this activity
$check = $conn->prepare("SELECT status FROM volunteer_applications WHERE user_id = ? AND activity_id = ? ORDER BY submitted_at DESC LIMIT 1");
$check->bind_param("ii", $user_id, $activity_id);
$check->execute();
$existing = $check->get_result()->fetch_assoc();

if ($existing) {
    if ($existing['status'] === 'pending') {
        echo "<script>parent.showToast('You have already submitted a request for this activity.'); parent.hideFloating();</script>";
        exit;
    } elseif ($existing['status'] === 'approved') {
        echo "<script>parent.showToast('You are already approved for this activity.'); parent.hideFloating();</script>";
        exit;
    }
    // If rejected or cancelled, allow new application (will insert new row)
}

$today = new DateTime();
// 65 years old limit (Oldest allowed)
$minDate = (clone $today)->sub(new DateInterval('P65Y'))->format('Y-m-d');
// 18 years old limit (youngest allowed)
$maxDate = (clone $today)->sub(new DateInterval('P18Y'))->format('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Volunteer Application</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../volunteer.css">
</head>
<body>
<div class="modal-container">
    <div class="modal-header">
        <div class="header-row">
            <div class="icon-column">
                <i class="fa-regular fa-heart"></i>
            </div>
            <div class="header-column">
                <h2>Volunteer Application</h2>
                <p>Please fill out the form to request joining this activity. Your application will be reviewed by an admin.</p>
            </div>
        </div>
    </div>
    <div class="modal-body">
        <form id="applicationForm" enctype="multipart/form-data">
            <input type="hidden" name="activity_id" value="<?php echo $activity_id; ?>">

            <!-- Date of Birth -->
            <div class="form-group">
                <label>Date of Birth <span class="required">*</span></label>
                <input type="date" name="date_of_birth" required>
            </div>

            <!-- Sex -->
            <div class="form-group">
                <label>Birth Sex</label>
                <select name="gender">
                    <option value="" disabled selected>Select here</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="prefer-not">Prefer not to say</option>
                </select>
            </div>

            <!-- Barangay -->
            <div class="form-group">
                <label>Barangay <span class="required">*</span></label>
                <select name="barangay" required>
                    <option value="" disabled selected>Select your Barangay</option>
                    <option value="Poblacion">Poblacion</option>
                    <option value="Nagbalayong">Nagbalayong</option>
                    <option value="Binaritan">Binaritan</option>
                    <option value="Sabang">Sabang</option>
                    <option value="Mabayo">Mabayo</option>
                    <option value="Panibatuhan">Panibatuhan</option>
                </select>
            </div>

            <!-- Upload Verification Files -->
            <div class="form-group">
                <label>Upload Valid ID / Certificate <span class="required">*</span></label>
                <div class="upload-area" id="uploadArea">
                    <input type="file" id="fileUpload" name="verification_files[]" multiple accept="image/*,application/pdf" hidden>
                    <div class="upload-content" onclick="document.getElementById('fileUpload').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Click to upload files or drag and drop</span>
                        <span class="upload-hint">PNG, JPG, PDF up to 5MB each (max 5 files)</span>
                    </div>
                </div>
                <div class="photo-preview" id="photoPreview"></div>
                <div class="evidence-note">
                    <span>You can upload up to 5 files as verification</span>
                </div>
            </div>

            <!-- Checkboxes -->
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="understand" required>
                    <span>I understand the importance of reforestation and will follow planting guidelines.</span>
                </label>
                <label>
                    <input type="checkbox" name="agree" required>
                    <span>I agree to follow DENR environmental and safety protocols.</span>
                </label>
            </div>

            <div class="bottom-button">
                <button type="submit" class="submit-btn">Submit Application</button>
                <button type="button" class="cancel-btn" onclick="parent.hideFloating()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Image Modal -->
<div class="image-modal" id="imageModal">
    <div class="modal-content">
        <img src="" alt="Zoomed image" class="modal-image">
        <div class="modal-counter"></div>
    </div>
</div>

<script src="volunteer.js"></script>
</body>
</html>