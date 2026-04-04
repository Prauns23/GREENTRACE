// Initialize map
let map;
let markers = [];
let currentFilter = 'all';

// Data from PHP
let forestAreas = [];
let reports = [];

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Get marker icon based on type and status
function getMarkerIcon(type, status, isArchived = false) {
    let className = 'custom-marker';
    let iconHtml = '';
    
    if (isArchived) {
        className += ' archived-marker';
        iconHtml = '<i class="fas fa-archive"></i>';
    } else if (type === 'forest') {
        className += ' forest-marker';
        iconHtml = '<i class="fas fa-tree"></i>';
    } else if (type === 'report') {
        const isActive = status === 'pending' || status === 'reviewed';
        if (isActive) {
            className += ' report-marker';
            iconHtml = '<i class="fas fa-exclamation-triangle"></i>';
        } else {
            className += ' archived-marker';
            iconHtml = '<i class="fas fa-check-circle"></i>';
        }
    }
    
    return L.divIcon({
        className: className,
        html: iconHtml,
        iconSize: [30, 30],
        popupAnchor: [0, -15]
    });
}

// Add markers to map
function addMarkers() {
    if (!map) {
        console.error('Map not initialized');
        return;
    }
    
    // Clear existing markers
    markers.forEach(marker => {
        if (map.hasLayer(marker)) {
            map.removeLayer(marker);
        }
    });
    markers = [];
    
    // Debug: Log data
    console.log('Forest Areas:', forestAreas);
    console.log('Reports:', reports);
    
    // Process forest areas
    if (forestAreas && forestAreas.length > 0) {
        forestAreas.forEach(area => {
            const isArchived = area.status === 'archived';
            
            let shouldShow = false;
            if (currentFilter === 'all') shouldShow = true;
            else if (currentFilter === 'forests') shouldShow = !isArchived;
            else if (currentFilter === 'archived') shouldShow = isArchived;
            else if (currentFilter === 'reports') shouldShow = false; // Forests don't show in reports filter
            
            // Validate coordinates
            const lat = parseFloat(area.latitude);
            const lng = parseFloat(area.longitude);
            
            if (shouldShow && !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                const icon = getMarkerIcon('forest', area.status, isArchived);
                const marker = L.marker([lat, lng], { icon }).addTo(map);
                
                marker.bindPopup(`
                    <div class="popup-content">
                        <h4>${escapeHtml(area.name)}</h4>
                        <p>${escapeHtml(area.location_name)}</p>
                        <p><strong>Status:</strong> ${area.status}</p>
                        <p><strong>Established:</strong> ${area.date_established || 'N/A'}</p>
                        <button onclick="showForestDetails(${area.id})">View Details</button>
                    </div>
                `);
                
                marker.itemData = { ...area, type: 'forest' };
                markers.push(marker);
                console.log(`Added forest marker: ${area.name} at [${lat}, ${lng}]`);
            }
        });
    } else {
        console.warn('No forest areas data available');
    }
    
    // Process reports
    if (reports && reports.length > 0) {
        reports.forEach(report => {
            const isResolved = report.status === 'resolved' || report.status === 'dismissed';
            
            let shouldShow = false;
            if (currentFilter === 'all') shouldShow = true;
            else if (currentFilter === 'reports') shouldShow = !isResolved;
            else if (currentFilter === 'archived') shouldShow = isResolved;
            else if (currentFilter === 'forests') shouldShow = false; // Reports don't show in forests filter
            
            // Validate coordinates
            const lat = parseFloat(report.latitude);
            const lng = parseFloat(report.longitude);
            
            if (shouldShow && !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                const icon = getMarkerIcon('report', report.status, isResolved);
                const marker = L.marker([lat, lng], { icon }).addTo(map);
                
                marker.bindPopup(`
                    <div class="popup-content">
                        <h4>${escapeHtml(report.issue_type)}</h4>
                        <p>${escapeHtml(report.location)}</p>
                        <p><strong>Status:</strong> ${report.status}</p>
                        <p><strong>Reported:</strong> ${report.created_at ? new Date(report.created_at).toLocaleDateString() : 'N/A'}</p>
                        <button onclick="showReportDetails(${report.id})">View Details</button>
                    </div>
                `);
                
                marker.itemData = { ...report, type: 'report' };
                markers.push(marker);
                console.log(`Added report marker: ${report.issue_type} at [${lat}, ${lng}]`);
            }
        });
    } else {
        console.warn('No reports data available');
    }
    
    console.log(`Total markers added: ${markers.length}`);
    updateStats();
    updateRecentActivity();
    
    // Auto-fit map bounds to show all markers
    if (markers.length > 0) {
        const group = L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.1));
    }
}

// Update statistics
function updateStats() {
    const activeForests = forestAreas ? forestAreas.filter(f => f.status !== 'archived').length : 0;
    const activeReports = reports ? reports.filter(r => r.status === 'pending' || r.status === 'reviewed').length : 0;
    const totalMarkers = activeForests + activeReports;
    const archivedCount = (forestAreas ? forestAreas.filter(f => f.status === 'archived').length : 0) + 
                         (reports ? reports.filter(r => r.status === 'resolved' || r.status === 'dismissed').length : 0);
    
    const totalMarkersEl = document.getElementById('totalMarkers');
    const activeForestsEl = document.getElementById('activeForests');
    const activeReportsEl = document.getElementById('activeReports');
    const archivedCountEl = document.getElementById('archivedCount');
    
    if (totalMarkersEl) totalMarkersEl.textContent = totalMarkers;
    if (activeForestsEl) activeForestsEl.textContent = activeForests;
    if (activeReportsEl) activeReportsEl.textContent = activeReports;
    if (archivedCountEl) archivedCountEl.textContent = archivedCount;
}

// Update recent activity list
function updateRecentActivity() {
    const forestItems = forestAreas ? forestAreas.map(f => ({
        id: f.id,
        name: f.name,
        locationName: f.location_name,
        date: f.date_established || f.created_at,
        status: f.status,
        type: 'forest',
        description: f.description
    })) : [];
    
    const reportItems = reports ? reports.map(r => ({
        id: r.id,
        name: r.issue_type,
        locationName: r.location,
        date: r.created_at,
        status: r.status,
        type: 'report',
        description: r.description
    })) : [];
    
    const allItems = [...forestItems, ...reportItems];
    const recentItems = allItems.sort((a, b) => new Date(b.date) - new Date(a.date)).slice(0, 5);
    
    const activityList = document.getElementById('activityList');
    if (activityList) {
        activityList.innerHTML = recentItems.map(item => `
            <div class="activity-item" onclick="showDetails(${item.id}, '${item.type}')">
                <div class="activity-icon ${item.type}">
                    <i class="fas ${item.type === 'forest' ? 'fa-tree' : 'fa-exclamation-triangle'}"></i>
                </div>
                <div class="activity-info">
                    <div class="activity-name">${escapeHtml(item.name)}</div>
                    <div class="activity-location">${escapeHtml(item.locationName)}</div>
                </div>
                <div class="activity-status ${item.status}">${item.status}</div>
            </div>
        `).join('');
    }
}

// Show details
window.showForestDetails = function(id) {
    const forest = forestAreas.find(f => f.id === id);
    if (forest) {
        alert(`🌳 Forest: ${forest.name}\n📍 Location: ${forest.location_name}\n📊 Status: ${forest.status}\n📝 Description: ${forest.description || 'No description'}`);
    }
};

window.showReportDetails = function(id) {
    const report = reports.find(r => r.id === id);
    if (report) {
        alert(`⚠️ Report: ${report.issue_type}\n📍 Location: ${report.location}\n📊 Status: ${report.status}\n👤 Reported by: ${report.reporter_name || 'Anonymous'}\n📝 Description: ${report.description || 'No description'}`);
    }
};

window.showDetails = function(id, type) {
    if (type === 'forest') {
        showForestDetails(id);
    } else {
        showReportDetails(id);
    }
};

// Filter markers
window.filterMarkers = function(filter) {
    currentFilter = filter;
    
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    const activeBtn = document.querySelector(`.filter-btn[data-filter="${filter}"]`);
    if (activeBtn) activeBtn.classList.add('active');
    
    addMarkers();
};

// Search functionality
function searchLocations() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    
    markers.forEach(marker => {
        const data = marker.itemData;
        let searchable = '';
        
        if (data.type === 'forest') {
            searchable = (data.name + ' ' + data.location_name).toLowerCase();
        } else {
            searchable = (data.issue_type + ' ' + data.location).toLowerCase();
        }
        
        if (searchTerm === '' || searchable.includes(searchTerm)) {
            if (!map.hasLayer(marker)) {
                marker.addTo(map);
            }
        } else {
            if (map.hasLayer(marker)) {
                map.removeLayer(marker);
            }
        }
    });
}

// Zoom controls
window.zoomIn = function() {
    if (map) map.zoomIn();
};

window.zoomOut = function() {
    if (map) map.zoomOut();
};


// Add new marker (opens modal)
window.addNewMarker = function() {
    closeAllFloating();
    const container = document.getElementById("floatingAddMarkerContainer");
    const iframe = document.getElementById("addMarkerFrame");
    if (iframe) {
        iframe.src = "modals/add_marker_modal.php";
    }
    if (container) {
        container.classList.add("active");
        if (typeof overlay !== 'undefined' && overlay) {
            overlay.classList.add("active");
        }
        if (typeof body !== 'undefined' && body) {
            body.classList.add("login-active");
        }
        if (typeof activeContainer !== 'undefined') {
            activeContainer = container;
        }
    }
};

// Close modal
window.closeModal = function() {
    const modal = document.getElementById('confirmModal');
    if (modal) modal.classList.remove('active');
};

// Initialize map
function initMap() {
    // Check if map container exists
    const mapContainer = document.getElementById('forestMap');
    if (!mapContainer) {
        console.error('Map container not found');
        return;
    }
    
    // Default center (Philippines)
    map = L.map('forestMap').setView([14.68, 120.35], 12);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);
    
    console.log('Map initialized');
    addMarkers();
}

// Wait for data and DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    // Get data from PHP
    if (typeof window.forestAreasData !== 'undefined') {
        forestAreas = window.forestAreasData;
        console.log('Forest areas loaded:', forestAreas.length);
    } else {
        console.warn('forestAreasData not found');
    }
    
    if (typeof window.reportsData !== 'undefined') {
        reports = window.reportsData;
        console.log('Reports loaded:', reports.length);
    } else {
        console.warn('reportsData not found');
    }
    
    // Initialize map
    initMap();
    
    // Add event listeners
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', searchLocations);
    }
    
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => filterMarkers(btn.dataset.filter));
    });
});