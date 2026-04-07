<?php
require_once '../init_session.php';
require_once '../config.php';

// Only admin can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die('Invalid forest area ID');
}

// Fetch forest area data
$stmt = $conn->prepare("SELECT * FROM forest_areas WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$forest = $stmt->get_result()->fetch_assoc();

if (!$forest) {
    die('Forest area not found');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Forest Area</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="edit_forest_modal.css" />
</head>
<body>
    <div class="modal-container">
        <div class="modal-header">
            <h2>Edit Forest Area</h2>
            <p>Update the forest area details below</p>
        </div>
        <div class="modal-body">
            <form id="editForestForm">
                <input type="hidden" name="id" value="<?php echo $forest['id']; ?>">

                <div class="form-group">
                    <label for="forestName">Forest Name<span class="required">*</span></label>
                    <input type="text" id="forestName" name="name" placeholder="e.g., Mount Natib Reforestation Site" value="<?php echo htmlspecialchars($forest['name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="locationName">Location Name<span class="required">*</span></label>
                    <input type="text" id="locationName" name="location_name" placeholder="e.g., Mount Natib, Bataan" value="<?php echo htmlspecialchars($forest['location_name']); ?>" required>
                </div>

                <!-- Map Picker -->
                <div class="map-container">
                    <label class="map-label">Select Location on Map <span class="required">*</span></label>
                    <div id="locationPickerMap"></div>
                    <div class="map-hint">
                        <i class="fas fa-info-circle"></i>
                        <span>Click anywhere on the map to set the coordinates</span>
                    </div>

                    <div class="coords-preview">
                        <span><i class="fas fa-map-marker-alt"></i>Selected Coordinates:</span>
                        <span id="coordDisplay"><?php echo $forest['latitude'] . ', ' . $forest['longitude']; ?></span>
                    </div>
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo $forest['latitude']; ?>" required>
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo $forest['longitude']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="dateEstablished">Date Established</label>
                    <input type="date" id="dateEstablished" name="date_established" value="<?php echo $forest['date_established']; ?>">
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?php echo $forest['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="ongoing" <?php echo $forest['status'] == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                        <option value="completed" <?php echo $forest['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="archived" <?php echo $forest['status'] == 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Describe the reforestation area, species planted, and etc..."><?php echo htmlspecialchars($forest['description']); ?></textarea>
                </div>

                <div class="button-group">
                    <button class="btn-cancel" type="button" onclick="parent.hideFloating()">Cancel</button>
                    <button class="btn-submit" type="submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="edit_forest_modal.js"></script>
</body>
</html>