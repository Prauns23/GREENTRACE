<?php
require_once 'init_session.php';
require_once 'config.php';

// Fetch all tree species with the new AR columns
$stmt = $conn->prepare("SELECT id, name, scientific_name, mature_height, trunk_diameter, canopy_diameter, leaf_color, trunk_color, planting_spacing, image_url FROM tree_species ORDER BY name ASC");
$stmt->execute();
$trees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<?php include 'header.php' ?>
<link rel="stylesheet" href="ar-camera.css">

<div class="ar-page">
    <!-- Header -->
    <div class="ar-header">
        <h1>AR Tree Viewer (AR Camera)</h1>
        <p>Select a tree or scan a QR code to visualize its full-grown size. Learn the canopy width and mature size of your selected tree!</p>
    </div>

    <div class="ar-grid">

        <!-- Left grid/Selection -->
        <div class="ar-left">
            <!-- Scan QR button -->
            <div class="ar-buttons">
                <button class="scan-qr-btn active">
                    <img src="components\icons\scan-qr.svg" alt="">
                    Scan QR
                </button>
                <button class="start-ar-btn">
                    <img src="components\icons\start-ar.svg" alt="">
                    Start AR
                </button>
            </div>


            <div class="tree-list" id="treeList">
                <?php foreach ($trees as $tree): ?>
                    <div class="tree-card" data-tree="<?= $tree['id'] ?>">
                        <div class="tree-info">
                            <div class="top-info">
                                <h3><?= htmlspecialchars($tree['name']) ?></h3>
                                <button class="qr-btn" title="Generate QR Code"><i class="fas fa-qrcode"></i>
                                </button>
                            </div>
                            <div class="bottom-info">
                                <p><span class="label">Height:</span> <?= $tree['mature_height'] ?>m</p>
                                <p><span class="label">Trunk:</span> <?= $tree['trunk_diameter'] ?>m</p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Small Note -->
            <div class="qr-note">
                <i class="fas fa-info-circle"></i>
                <span>Tap the QR icon to generate a printable QR code for any tree.</span>
            </div>
        </div>


        <!-- Right grid/Simulation -->
        <div class="ar-right">
            <div class="simulation-box" id="simulationBox">
                <div id="threeContainer" style="width:100%;height:100%;"></div>
                <div class="placeholder-content" id="placeholderContent">
                    <i class="fas fa-tree"></i>
                    <p>Select a tree to visualize</p>
                    <span class="hint">3D model will appear here</span>
                </div>
            </div>
            <div class="selected-info" id="selectedInfo">
                <p>Select a tree from the list to see its projected size.</p>
            </div>
        </div>
    </div>
</div>

<script>
    const treeData = <?= json_encode($trees) ?>;
</script>

<script src="ar-camera.js"></script>
<?php include 'footer.php'; ?>