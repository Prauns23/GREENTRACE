<?php
require_once 'init_session.php';
require_once 'config.php';

if (!isset($_SESSION['first_name'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit();
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    header('Location: index.php');
    exit();
}

include 'header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="forestmap.css?v=mapv2-photo-gallery-25">

<main class="forestmap-page mapv2-page">
    <header class="mapv2-heading">
        <h1>Forest Map</h1>
        <p>View reforestation areas, reported issues, and forest monitoring data.</p>
    </header>

    <div class="mapv2-layout">

        <aside class="mapv2-sidebar" aria-label="Map filters">
            <div class="mapv2-search-field">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="searchInput" placeholder="Search locations..." aria-label="Search">
            </div>

            <div class="mapv2-scope-row">
                <div class="mapv2-scope-select">
                    <select id="mapScopeSelect" aria-label="Map content">
                        <option value="forests">Reforestation</option>
                        <option value="reports">Reported</option>
                    </select>
                    <i class="fas fa-chevron-down mapv2-scope-chevron" aria-hidden="true"></i>
                </div>
                <div class="mapv2-menu-wrap">
                    <button type="button" class="mapv2-icon-button" id="mapFilterMenuBtn" aria-label="More map options" aria-expanded="false">
                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                    </button>
                    <div class="mapv2-popover" id="mapFilterMenu" hidden>
                        <button type="button" data-map-scope="archived">Archived</button>
                    </div>
                </div>
            </div>

            <div class="mapv2-sidebar-scroll">
                <div class="mapv2-filter-grid">
                    <label>
                        <span>Status</span>
                        <div class="mapv2-filter-select">
                            <select id="statusFilter">
                                <option value="all">All statuses</option>
                                <option value="planned">Planned</option>
                                <option value="planted">Planted</option>
                                <option value="monitored">Monitored</option>
                                <option value="low_survival">Low survival</option>
                                <option value="completed">Completed</option>
                            </select>
                            <i class="fas fa-chevron-down mapv2-scope-chevron" aria-hidden="true"></i>
                        </div>
                    </label>
                    <label>
                        <span id="filterYearLabel">Year Planted</span>
                        <div class="mapv2-filter-select">
                            <select id="yearFilter">
                                <option value="all">All years</option>
                                <option value="2026">2026</option>
                                <option value="2025">2025</option>
                                <option value="2024">2024</option>
                            </select>
                            <i class="fas fa-chevron-down mapv2-scope-chevron" aria-hidden="true"></i>
                        </div>
                    </label>
                </div>

                <section class="mapv2-control-section">
                    <h2>Basemap</h2>
                    <div class="mapv2-basemap-toggle" role="tablist" aria-label="Basemap" data-active="osm">
                        <button type="button" class="mapv2-basemap-button is-active" data-basemap="osm" role="tab" aria-selected="true">OSM</button>
                        <button type="button" class="mapv2-basemap-button" data-basemap="satellite" role="tab" aria-selected="false">Satellite</button>
                    </div>
                </section>

                <!-- Compartment metrics (visible in Reforestation scope) -->
                <div class="mapv2-metrics" id="metricsCompartments" aria-label="Map totals">
                    <div><span>Total Target Area</span><strong id="netArea">0</strong><small>ha</small></div>
                    <div><span>Forest plots</span><strong id="forestPlotCount">0</strong></div>
                </div>

                <!-- Report metrics (visible in Reported scope) -->
                <div class="mapv2-metrics" id="metricsReports" aria-label="Report totals" hidden>
                    <div><span>Active Reports</span><strong id="activeReports">0</strong></div>
                    <div><span>Total Reports</span><strong id="totalReports">0</strong></div>
                </div>

                <section class="mapv2-control-section mapv2-layers" id="layersSection">
                    <h2>Layers</h2>
                    <label class="mapv2-layer-toggle">
                        <span>Tree species</span>
                        <span class="mapv2-switch">
                            <input type="checkbox" id="treeSpeciesToggle" checked>
                            <span class="mapv2-switch-track" aria-hidden="true"></span>
                        </span>
                    </label>
                    <label class="mapv2-layer-toggle">
                        <span>Barangay boundaries</span>
                        <span class="mapv2-switch">
                            <input type="checkbox" id="barangayBoundariesToggle" checked>
                            <span class="mapv2-switch-track" aria-hidden="true"></span>
                        </span>
                    </label>
                    <label class="mapv2-layer-toggle">
                        <span>Vegetation index overlay</span>
                        <span class="mapv2-switch">
                            <input type="checkbox" id="vegetationOverlayToggle">
                            <span class="mapv2-switch-track" aria-hidden="true"></span>
                        </span>
                    </label>
                    <p class="mapv2-helper-text">A visual greenness proxy from satellite imagery, not measured NDVI.</p>
                </section>

                <!-- Compartment legend -->
                <section class="mapv2-control-section mapv2-legend" id="legendCompartments">
                    <h2>Legend</h2>
                    <span><i class="status-dot status-planned"></i> Planned</span>
                    <span><i class="status-dot status-planted"></i> Planted</span>
                    <span><i class="status-dot status-monitored"></i> Monitored</span>
                    <span><i class="status-dot status-low-survival"></i> Low survival</span>
                    <span><i class="status-dot status-completed"></i> Completed</span>
                </section>

                <!-- Report legend -->
                <section class="mapv2-control-section mapv2-legend" id="legendReports" hidden>
                    <h2>Legend</h2>
                    <span><i class="status-dot" style="background:#e53935;"></i> Pending</span>
                    <span><i class="status-dot" style="background:#fb8c00;"></i> Reviewed</span>
                    <span><i class="status-dot" style="background:#43a047;"></i> Resolved</span>
                    <span><i class="status-dot" style="background:#757575;"></i> Dismissed</span>
                </section>
            </div>
        </aside>

        <section class="mapv2-map-panel" aria-label="Forest map">
            <div id="forestMap"></div>
            <div class="mapv2-toast" id="mapToast" role="status" aria-live="polite" hidden></div>
        </section>

        <!-- Compartment panel -->
        <aside class="mapv2-compartments" id="compartmentPanel" aria-live="polite">
            <section id="compartmentListView">
                <header class="mapv2-panel-heading">
                    <h2 id="compartmentPanelTitle">Compartments</h2>
                    <p>Select a compartment on the map or create a compartment by clicking the plus card.</p>
                </header>
                <div class="mapv2-compartments-scroll">
                    <div id="compartmentList" class="mapv2-compartment-list"></div>
                </div>
            </section>
            <section id="compartmentDetailView" hidden></section>
        </aside>

        <!-- Report panel -->
        <aside class="mapv2-compartments" id="reportPanel" aria-live="polite" hidden>
            <section id="reportListView">
                <header class="mapv2-panel-heading">
                    <h2 id="reportPanelTitle">Reported Areas</h2>
                    <p>Select a report from the map or from the list to see its details.</p>
                </header>
                <div class="mapv2-compartments-scroll">
                    <div id="reportList" class="mapv2-report-list"></div>
                </div>
            </section>
            <section id="reportDetailView" hidden></section>
        </aside>
    </div>
</main>

<section class="mapv2-photo-gallery" id="mapv2PhotoGallery" role="dialog" aria-modal="true" aria-labelledby="mapv2GalleryName" aria-hidden="true" hidden>
    <div class="mapv2-photo-gallery__dialog">
        <header class="mapv2-photo-gallery__header">
            <p id="mapv2GalleryName">Compartment photo</p>
            <span id="mapv2GalleryCounter" aria-live="polite"></span>
            <button type="button" id="mapv2GalleryClose">Close</button>
        </header>
        <div class="mapv2-photo-gallery__content">
            <button type="button" class="mapv2-photo-gallery__nav" id="mapv2GalleryPrevious" aria-label="Previous photo"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
            <img id="mapv2GalleryImage" alt="">
            <button type="button" class="mapv2-photo-gallery__nav" id="mapv2GalleryNext" aria-label="Next photo"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
        </div>
    </div>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="reports-map.js?v=1"></script>
<script src="forestmap.js?v=mapv2-photo-gallery-26"></script>

<?php include 'footer.php'; ?>