<?php
require_once __DIR__ . '/../init_session.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    http_response_code(403);
    exit('<p style="padding:24px;font-family:Inter,sans-serif">Administrator access is required.</p>');
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit reforestation photo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="edit_reforestation_photo.css?v=1">
</head>

<body>
    <main class="photo-edit-modal" aria-labelledby="editPhotoTitle">
        <header class="photo-edit-modal__header">
            <h1 id="editPhotoTitle">Edit details</h1>
        </header>
        <form class="photo-edit-modal__content" id="editPhotoForm">
            <label class="photo-edit-field"><span>Name</span><input id="editPhotoName" maxlength="255" required></label>
            <label class="photo-edit-field"><span>Category</span><span class="photo-edit-select"><select id="editPhotoCategory">
                        <option value="planned">Planned</option>
                        <option value="planted">Planted</option>
                        <option value="monitored">Monitored</option>
                        <option value="low_survival">Low survival</option>
                        <option value="other">Other</option>
                    </select><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span></label>
        </form>
        <footer class="photo-edit-modal__footer"><button type="button" class="photo-edit-button photo-edit-button--secondary" id="cancelEditPhoto">Cancel</button><button type="submit" form="editPhotoForm" class="photo-edit-button photo-edit-button--primary">Save</button></footer>
    </main>
    <script src="edit_reforestation_photo.js?v=1"></script>
</body>

</html>