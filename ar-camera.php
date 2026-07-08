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
                <button class="scan-qr-btn active" id="scanQrBtn">
                    <img src="components\icons\scan-qr.svg" alt="">
                    Scan QR
                </button>
                <button class="start-ar-btn" id="startArBtn">
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
                <div id="arOverlay" style="display: none; position: fixed; inset: 0; z-index: 10000; pointer-events: none; background: rgba(0,0,0,0.3);">
                    <div id="arHint" style="position: absolute; bottom: 80px; left: 50%; transform: translateX(-50%); color: white; background: rgba(0,0,0,0.5); padding: 8px 16px; border-radius: 20px; font-size: 14px; pointer-events: none;">
                        Tap the ground to place the tree
                    </div>
                </div>

                <input type="hidden" id="selectedTreeId" value="">
                <div id="threeContainer" style="width:100%;height:100%;"></div>
                <div class="tree-info-overlay" id="treeInfoOverlay"></div>
                <div class="placeholder-content" id="placeholderContent"></div>
            </div>

            <!-- Exit AR button -->
            <div id="exitArBtnWrapper" style="display: none; position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 10001;">
                <button id="exitArBtn" onclick="exitAR()" style="padding: 12px 32px; background: rgba(0,0,0,0.8); color: white; border: none; border-radius: 40px; font-weight: 600; font-size: 16px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.3); pointer-events: auto;">
                    Exit AR
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const treeData = <?= json_encode($trees) ?>;
</script>

<script src="ar-camera.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<?php include_once __DIR__ . '/modals/ar-qr.php'; ?>
<?php include 'footer.php'; ?>