<?php
// Only session check – no processing
require_once '../init_session.php';
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <script src="../security.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Channel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="create_channel.css">
</head>

<body>
    <div class="channel-modal">
        <div class="modal-header">
            <h2>Create a channel</h2>
        </div>
    
        <div class="modal-body">
            <!-- Channel Name -->
            <div class="form-group">
                <label for="channelName">Channel Name</label>
                <input type="text" id="channelName" class="channel-name-input" placeholder="Channel name (e.g., general)">
            </div>

            <!-- Channel Type -->
            <div class="form-group">
                <label for="channelType">Channel Type</label>
                <select id="channelType" class="channel-type-select">
                    <option value="activities">Activities</option>
                    <option value="reporting">Reporting</option>
                    <option value="others">Others</option>
                </select>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label for="channelDescription">Description</label>
                <textarea id="channelDescription" class="channel-description-input" placeholder="Write your channel description here"></textarea>
            </div>

            <!-- Guidance text -->
            <div class="channel-guide">
                <p>Channels are the places where you chat about different topics. Pick a name that’s easy to find and get what it’s about!</p>
            </div>

            <!-- Visibility -->
            <div class="visibility-section">
                <span class="visibility-label">Visibility</span>
                <div class="visibility-options">
                    <label class="visibility-card public-card">
                        <div class="card-content">
                            <span class="card-title">Public</span>
                            <p class="card-desc">Anyone in <strong>concerns</strong></p>
                        </div>
                        <input type="radio" name="visibility" value="public" checked>
                    </label>
                    <label class="visibility-card private-card">
                        <div class="card-content">
                            <span class="card-title">Private</span>
                            <p class="card-desc">Only specific users</p>
                        </div>
                        <input type="radio" name="visibility" value="private">
                    </label>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn-cancel" onclick="parent.hideFloating()">Cancel</button>
            <button class="btn-create" id="createChannelBtn">Create</button>
        </div>
    </div>

    <script src="create_channel.js"></script>
</body>

</html>
