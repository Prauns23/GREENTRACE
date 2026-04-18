<?php
require_once '../init_session.php';
require_once '../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die('Invalid forest area ID');
}

$stmt = $conn->prepare("SELECT * FROM forest_areas WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$forest = $stmt->get_result()->fetch_assoc();

if (!$forest) {
    die('Forest area not found');
}

// Status badge class
$statusClass = '';
switch ($forest['status']) {
    case 'active':
        $statusClass = 'status-active';
        break;
    case 'ongoing':
        $statusClass = 'status-ongoing';
        break;
    case 'completed':
        $statusClass = 'status-completed';
        break;
    default:
        $statusClass = 'status-active';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forest Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="forest_details.css">
</head>

<body>
    <div class="modal-container">
        <div class="modal-header">
            <h2>
                <?php echo htmlspecialchars($forest['name']); ?>
                <span class="status-badge <?php echo $statusClass; ?>">
                    <?php echo ucfirst($forest['status']); ?>
                </span>
            </h2>
            <p>Forest area details</p>
        </div>
        <div class="modal-body">
            <!-- Location Name (full width) -->
            <div class="form-group">
                <label>Location Name</label>
                <div class="display-value"><?php echo htmlspecialchars($forest['location_name']); ?></div>
            </div>

            <!-- Coordinates -->
            <div class="form-row">
                <div class="form-group half">
                    <label>Latitude</label>
                    <div class="display-value"><?php echo htmlspecialchars($forest['latitude']); ?></div>
                </div>
                <div class="form-group half">
                    <label>Longitude</label>
                    <div class="display-value"><?php echo htmlspecialchars($forest['longitude']); ?></div>
                </div>
            </div>

            <!-- Date Established -->
            <div class="form-group">
                <label>Date Established</label>
                <div class="display-value">
                    <?php echo $forest['date_started'] ? date('F j, Y', strtotime($forest['date_started'])) : 'Not specified'; ?>
                </div>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label>Description</label>
                <div class="display-value description-text">
                    <?php echo nl2br(htmlspecialchars($forest['description'] ?: 'No description provided.')); ?>
                </div>
            </div>
        </div>

        <!-- Footer Buttons -->
        <div class="button-group">
            <button class="btn-cancel" type="button" onclick="parent.hideFloating()">Close</button>
        </div>
    </div>
</body>

</html>