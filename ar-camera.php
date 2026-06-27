<?php
require_once 'init_session.php';
require_once 'config.php';
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


            <div class="tree-list">
                <div class="tree-card" data-tree="">
                    <div class="tree-info">
                        <div class="top-info">
                            <h3>Narra</h3>
                            <button class="qr-btn" title="Generate QR Code"><i class="fas fa-qrcode"></i>
                            </button>
                        </div>
                        <div class="bottom-info">
                            <p><span class="label">Height:</span> 33m</p>
                            <p><span class="label">Trunk:</span> 1m</p>
                        </div>
                    </div>
                </div>
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
                <div class="tree-details">
                    <i class="fa-solid fa-expand"></i>
                    <p><span>Narra: </span>33m</p>
                </div>
                <!-- <div class="placeholder-content">
                    <i class="fas fa-tree"></i>
                    <p>Select a tree to visualize</p>
                    <span class="hint">The Model will appear here</span>
                </div> -->
            </div>
        </div>
    </div>
</div>

<script src="ar-camera.js"></script>

<?php include 'footer.php'; ?>