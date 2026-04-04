<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Forest Area</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="add_marker_modal.css" />
</head>

<body>
    <div class="modal-container">
        <div class="modal-header">
            <h2>Edit Forest Area</h2>
            <p>Customize your marker details below</p>
        </div>
        <div class="modal-body">
            <form action="" id="addForestForm">
                <div class="form-group">
                    <label for="Forest Name">Forest Name<span class="required">*</span></label>
                    <input type="text" id="forestName" placeholder="e.g., Mount Natib Reforestation Site" required>
                </div>

                <div class="form-group">
                    <label for="">Location Name <span class="required">*</span></label>
                    <input type="text" id="locationName" placeholder="e.g., Mount Natib, Bataan">
                </div>

                <!-- Map Picker -->
                <div class="map-container">
                    <label for="" class="map-label">Select Location on Map <span class="required">*</span></label>
                    <div id="locationPickerMap"></div>
                    <div class="map-hint">
                        <i class="fas fa-info-circle"></i>
                        <span>Click anywhere on the map to set the coordinates</span>
                    </div>

                    <div class="coords-preview">
                        <span><i class="fas fa-map-marker-alt"></i>Selected Coordinates:</span>
                        <span id="coordDisplay">Not selected</span>
                    </div>
                    <input type="hidden" id="latitude" name="latitude" required>
                    <input type="hidden" id="longitude" name="longitude" required>
                </div>

                <div class="form-group">
                    <label for="">Date Established</label>
                    <input type="date" id="dateEstablished" value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label for="">Status</label>
                    <select name="" id="status">
                        <option value="active">Active</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="">Description</label>
                    <textarea name="" id="description" placeholder="Describe the reforestaion area, species planted, and etc..."></textarea>
                </div>

                <div class="button-group">
                    <button class="btn-cancel" type="button" onclick="parent.hideFloating()">Cancel</button>
                    <button class="btn-submit" type="submit">Add Forest Area</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="add_marker_modal.js"></script>
</body>

</html>