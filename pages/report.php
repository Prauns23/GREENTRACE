<?php
require_once '../init_session.php';

$is_logged_in = isset($_SESSION['first_name']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Issue</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="report.css">
</head>

<body>
    <div class="report-container">

            <!-- Logged in – show report form -->
            <div class="report-header">
                <h3>Report Environment Issue</h3>
                <span class="subtitle">Report illegal logging, forest damage, or other environmental concerns.</span>
            </div>

            <div class="report-content">
                <form action="" id="reportForm">
                    <!-- Issue Type -->
                    <div class="form-group">
                        <label>Issue Type <span class="required">*</span></label>
                        <select class="form-select" required>
                            <option value="" disabled selected>Select Issue type</option>
                            <option value="Illegal-logging">Illegal Logging</option>
                            <option value="Forest-damage">Forest Damage</option>
                            <option value="Wildfire Risk">Wildfire Risk</option>
                            <option value="Wildlife Poaching">Wildlife Poaching</option>
                            <option value="Pollution">Pollution</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <!-- Description -->
                    <div class="form-group">
                        <label for="">Description <span class="required">*</span></label>
                        <textarea name="" id="report-description" placeholder="Describe the issue in detail..." rows="4"></textarea>
                    </div>

                    <!-- Location -->
                    <div class="form-group">
                        <label>Location <span class="required">*</span></label>
                        <div class="location-input-group">
                            <input type="text" placeholder="e.g., Ibayo, Balanga City, Bataan or Coordinates" class="location-search">
                            <button type="button" class="gps-btn">
                                <i class="fas fa-location-dot"></i>
                            </button>
                            <input type="hidden" name="latitude" id="latitude" value="">
                            <input type="hidden" name="longitude" id="longitude" value="">
                        </div>
                        <div class="location-map" id="locationMap" style="display: none; height: 300px;"></div>
                    </div>
            </div>

            <!-- Coordinates hint -->
            <div class="location-hint" id="locationHint">
                <i class="fas fa-info-circle"></i>
                <span>Enter a descriptive location or use GPS for coordinates</span>
            </div>

            <!-- Photo evidence -->
            <div class="form-group">
                <label>Evidence Photos</label>
                <div class="upload-area" id="uploadArea">
                    <input type="file" id="fileUpload" multiple accept="image/*" hidden>
                    <div class="upload-content" onclick="document.getElementById('fileUpload').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Click to upload photos or drag and drop</span>
                        <span class="upload-hint">PNG, JPG up to 10MB</span>
                    </div>
                </div>
                <div class="photo-preview" id="photoPreview"></div>
                <div class="evidence-note">
                    <span>You can upload up to 5 photos as evidence</span>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="form-group">
                <label>Your Email</label>
                <input type="email" name="email" id="reportEmail" placeholder="JohnDoe@email.com">
            </div>

            <!-- Anonymous checkbox -->
            <div class="checkbox-group">
                <input type="checkbox" id="anonymous">
                <label for="anonymous">Report anonymously</label>
            </div>

            <div class="report-note">
                <i class="fas fa-clock"></i>
                <span>Your report will be processed and reviewed by MENRO officials.</span>
            </div>

            <!-- Buttons -->
            <div class="button-group">
                <button type="submit" class="submit-btn">Submit Report</button>
                <button type="button" class="cancel-btn" onclick="parent.hideFloating();">Cancel</button>
            </div>
            </form>
    </div>

    <!-- Image Modal (clickable) -->
    <div class="image-modal" id="imageModal">
        <div class="modal-content">
            <img src="" alt="Zoomed image" class="modal-image">
            <div class="modal-counter"></div>
        </div>
    </div>

    <?php if ($is_logged_in): ?>
        <script src="report.js"></script>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <?php endif; ?>

    <script>
        var userEmail = <?php echo json_encode($_SESSION['email'] ?? ''); ?>;
    </script>

</body>

</html>