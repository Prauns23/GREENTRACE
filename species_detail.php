<?php
require_once 'init_session.php';
require_once 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM tree_species WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$species = $result->fetch_assoc();

if (!$species) {
    echo '<div class="error">Species not found.</div>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($species['name']); ?> - Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="species_detail.css" rel="stylesheet">
</head>

<body>
    <span class="close-btn" onclick="parent.hideFloating()">×</span>
    <div>
        <?php if (!empty($species['image_url'])): ?>
            <div class="image-container">
                <img src="<?php echo htmlspecialchars($species['image_url']); ?>" alt="<?php echo htmlspecialchars($species['name']); ?>">
            </div>
        <?php endif; ?>

        <div class="header">
            <h1><?php echo htmlspecialchars($species['name']); ?></h1>
            <span class="badge <?php echo $species['category']; ?>">
                <?php echo ucfirst($species['category']); ?>
            </span>
        </div>

        <p class="scientific"><?php echo htmlspecialchars($species['scientific_name']); ?></p>

        <div class="section">
            <h3>Description</h3>
            <p><?php echo nl2br(htmlspecialchars($species['description'])); ?></p>
        </div>

        <?php if (!empty($species['importance'])): ?>
            <div class="section">
                <h3>Importance</h3>
                <ul class="importance-list">
                    <?php
                    $points = preg_split('/\r\n|\n|\r/', $species['importance']);
                    foreach ($points as $point):
                        $point = trim($point);
                        if (!empty($point)):
                    ?>
                            <li><?php echo htmlspecialchars($point); ?></li>
                    <?php endif;
                    endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($species['fun_fact'])): ?>
            <div class="fun-fact">
                <strong>Fun Fact!</strong><br>
                <span><?php echo htmlspecialchars($species['fun_fact']); ?></span>
            </div>
        <?php endif; ?>

    </div>
</body>

</html>