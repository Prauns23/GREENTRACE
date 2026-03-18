<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="logout.css">
</head>
<body>
    <div class="logout-container">
        <span class="close-btn" onclick="parent.hideFloating()">×</span>
        <div class="logout-content">
            <h1>Do you want to logout?</h1>
            <p>Together, we continue restoring tomorrow</p>
            <img src="logout-pic.svg" alt="" class="logoutPic">
            <div class="button-group">
                <a href="../logout_action.php" target="_parent" class="confirm-btn">Confirm</a>
                <button class="cancel-btn" onclick="parent.hideFloating()">Cancel</button>
            </div>
        </div>
    </div>
</body>
</html>