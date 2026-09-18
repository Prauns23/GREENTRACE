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
    <title>Review Reforestation Compartment</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="review_reforestation_compartment.css?v=2">
</head>
<body>
    <main class="review-modal" aria-labelledby="reviewTitle">
<header class="review-modal__header">
  <div>
    <h1 id="reviewTitle">Review planned plot</h1>
  </div>
</header>

        <!-- The parent map fills this read-only summary before the user creates it. -->
        <section class="review-modal__content">
            <dl class="review-list">
                <div><dt>Plot name</dt><dd id="reviewName">—</dd></div>
                <div><dt>Boundary corners</dt><dd id="reviewCorners">—</dd></div>
                <div><dt>Gross area</dt><dd id="reviewArea">—</dd></div>
            </dl>
            <section class="review-location">
                <span>Location</span>
                <strong id="reviewLocation">Matching barangay boundary…</strong>
                <small>Automatically matched from the plotted compartment boundary.</small>
            </section>
            <section class="review-species">
                <h2>Species mix</h2>
                <div id="reviewSpecies"></div>
            </section>
        </section>

        <!-- Review actions either return to drawing, cancel it, or save the draft. -->
        <footer class="review-modal__footer">
            <button type="button" class="button button--secondary" id="cancelDrawing">Cancel drawing</button>
            <button type="button" class="button button--secondary" id="resumeDrawingFooter">Back to drawing</button>
            <button type="button" class="button button--primary" id="confirmCreate">Create compartment</button>
        </footer>
    </main>
    <script src="review_reforestation_compartment.js?v=2"></script>
</body>
</html>
