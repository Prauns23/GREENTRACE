<?php
require_once __DIR__ . '/../init_session.php';
require_once __DIR__ . '/../config.php';

// Only admin can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Handle approve/reject via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'], $_POST['action'])) {
    $app_id = (int)$_POST['application_id'];
    $action = $_POST['action'];

    $stmt = $conn->prepare("SELECT user_id, activity_id, status FROM volunteer_applications WHERE id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();

    if ($app && $app['status'] === 'pending') {
        $conn->begin_transaction();
        try {
            $new_status = ($action === 'approve') ? 'approved' : 'rejected';
            $update = $conn->prepare("UPDATE volunteer_applications SET status = ? WHERE id = ?");
            $update->bind_param("si", $new_status, $app_id);
            $update->execute();

            if ($action === 'approve') {
                $inc = $conn->prepare("UPDATE activities SET participants_count = participants_count + 1 WHERE id = ?");
                $inc->bind_param("i", $app['activity_id']);
                $inc->execute();
            }
            $conn->commit();
            $_SESSION['admin_message'] = "Application " . ($action === 'approve' ? 'approved' : 'rejected');
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['admin_message'] = 'Error: ' . $e->getMessage();
        }
    } else {
        $_SESSION['admin_message'] = 'Application not found or already processed.';
    }
    header('Location: application_activity.php');
    exit;
}

// Sorting logic
$sort = $_GET['sort'] ?? 'latest';
switch ($sort) {
    case 'earliest':
        $orderBy = "va.submitted_at ASC";
        break;
    case 'status':
        $orderBy = "FIELD(va.status, 'pending', 'approved', 'rejected', 'cancelled')";
        break;
    case 'activity':
        $orderBy = "a.title ASC";
        break;
    case 'user':
        $orderBy = "u.fname ASC, u.lname ASC";
        break;
    default:
        $orderBy = "va.submitted_at DESC";
}

// Fetch statistics
$totalActivities = $conn->query("SELECT COUNT(*) as count FROM activities")->fetch_assoc()['count'];
$totalJoined = $conn->query("SELECT COUNT(*) as count FROM volunteer_applications WHERE status = 'approved'")->fetch_assoc()['count'];

// Fetch applications with sorting
$query = "
    SELECT 
        va.*, 
        u.fname, u.lname, u.email as user_email, 
        a.title as activity_title,
        GROUP_CONCAT(ap.file_path SEPARATOR '|') as file_paths,
        GROUP_CONCAT(ap.original_name SEPARATOR '|') as file_names
    FROM volunteer_applications va
    JOIN users_tbl u ON va.user_id = u.id
    JOIN activities a ON va.activity_id = a.id
    LEFT JOIN application_photos ap ON ap.application_id = va.id
    GROUP BY va.id
    ORDER BY $orderBy
";
$result = $conn->query($query);
$applications = $result->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../header.php';
?>

<link rel="stylesheet" href="application_activity.css">

<div class="application-container">
    <div class="app-header">
        <h2>Volunteer Applications</h2>
        <p>You can manage the activities and volunteer applications here</p>
    </div>

    <div class="search-filter">
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search">
        </div>
        <div class="sort-bar">
            <label>Sort By</label>
            <div class="custom-select">
                <select id="sortSelect">
                    <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
                    <option value="earliest" <?= $sort === 'earliest' ? 'selected' : '' ?>>Earliest</option>
                    <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status (Pending first)</option>
                    <option value="activity" <?= $sort === 'activity' ? 'selected' : '' ?>>Activity (A–Z)</option>
                    <option value="user" <?= $sort === 'user' ? 'selected' : '' ?>>User (A–Z)</option>
                </select>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
    </div>

    <!-- Original card design (no changes) -->
    <div class="card-app-grid">
        <div class="app-card">
            <div class="card-header">
                <h2>Total Activities</h2>
            </div>
            <div class="card-content">
                <h2><?php echo $totalActivities; ?></h2>
                <p>Number of activities volunteers can join</p>
            </div>
        </div>
        <div class="app-card">
            <div class="card-header">
                <h2>Total Joined</h2>
            </div>
            <div class="card-content">
                <h2><?php echo $totalJoined; ?></h2>
                <p>Number of volunteers joined</p>
            </div>
        </div>
    </div>

    <div class="app-table">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Activity</th>
                    <th>Full Name</th>
                    <th>Birthdate</th>
                    <th>Mobile</th>
                    <th>Barangay</th>
                    <th>Documents</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($applications)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center;">No applications found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($app['fname'] . ' ' . $app['lname']); ?></strong><br>
                                <small><?php echo htmlspecialchars($app['user_email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($app['activity_title']); ?></td>
                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($app['date_of_birth'])); ?></td>
                            <td><?php echo htmlspecialchars($app['mobile_number']); ?></td>
                            <td><?php echo htmlspecialchars($app['barangay']); ?></td>
                            <td>
                                <?php
                                if (!empty($app['file_paths'])) {
                                    $paths = explode('|', $app['file_paths']);
                                    $names = explode('|', $app['file_names']);
                                    echo '<div class="docs-gallery">';
                                    for ($i = 0; $i < count($paths); $i++) {
                                        $fullPath = '../' . $paths[$i];
                                        $ext = strtolower(pathinfo($paths[$i], PATHINFO_EXTENSION));
                                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                            echo '<div class="doc-thumb" onclick="openImageModal(\'' . htmlspecialchars($fullPath) . '\')">
                                                    <img src="' . htmlspecialchars($fullPath) . '" alt="' . htmlspecialchars($names[$i]) . '">
                                                  </div>';
                                        } else {
                                            echo '<a href="' . htmlspecialchars($fullPath) . '" target="_blank" class="view-file">' . htmlspecialchars($names[$i]) . '</a><br>';
                                        }
                                    }
                                    echo '</div>';
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $app['status']; ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y g:i A', strtotime($app['submitted_at'])); ?></td>
                            <td>
                                <?php if ($app['status'] === 'pending'): ?>
                                    <div class="action-buttons">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="action-btn approve-btn" title="Approve">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                            <button type="submit" name="action" value="reject" class="action-btn reject-btn" title="Reject">
                                                <i class="fas fa-times-circle"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="image-modal" onclick="closeImageModal()">
    <span class="image-modal-close">&times;</span>
    <img class="image-modal-content" id="modalImage">
</div>

<script>
    // Search filter
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('.app-table tbody tr');
    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        tableRows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });

    // Sorting redirect
    const sortSelect = document.getElementById('sortSelect');
    sortSelect.addEventListener('change', function() {
        window.location.href = 'application_activity.php?sort=' + this.value;
    });

    // Image modal functions
    function openImageModal(src) {
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        modal.style.display = 'flex';
        modalImg.src = src;
    }

    function closeImageModal() {
        const modal = document.getElementById('imageModal');
        modal.style.display = 'none';
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeImageModal();
    });
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>