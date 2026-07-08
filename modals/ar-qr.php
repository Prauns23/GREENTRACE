<!-- modals/ar-qr.php -->
<link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/modals/') !== false) ? '../' : ''; ?>modals/ar-qr.css">

<div id="qrModal" style="display: none; position: fixed; inset: 0; z-index: 10001; background: rgba(0,0,0,0.6); backdrop-filter: blur(2px); justify-content: center; align-items: center;">
    <div class="modal-content">
        <div class="header-qr">
            <h3 id="qrModalTitle">QR Code</h3>
            <button id="closeQrModalBtn"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="qr-code-container" id="qrCodeContainer"></div>
        <p class="qr-description" id="qrDescription">Print or share this QR code. Scan it with the AR Camera to instantly view the tree at full scale.</p>
        <div class="qr-actions">
            <button class="download-btn" id="downloadQrBtn"><i class="fa-solid fa-arrow-down"></i>Download QR Code</button>
        </div>
    </div>
</div>