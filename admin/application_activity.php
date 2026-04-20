<?php
require_once __DIR__ . '/../init_session.php';
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// --- Single actions: approve, reject, restore ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'], $_POST['action']) && !isset($_POST['bulk_action'])) {
    $app_id = (int)$_POST['application_id'];
    $action = $_POST['action'];

    $stmt = $conn->prepare("SELECT user_id, activity_id, status, archived FROM volunteer_applications WHERE id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();

    if (!$app) {
        $_SESSION['admin_message'] = 'Application not found.';
        header('Location: application_activity.php?sort=' . ($_GET['sort'] ?? 'latest'));
        exit;
    }

    // Handle restore (works on archived records)
    if ($action === 'restore') {
        if ($app['archived'] == 1) {
            $conn->begin_transaction();
            try {
                $restore = $conn->prepare("UPDATE volunteer_applications SET archived = 0, archived_at = NULL WHERE id = ?");
                $restore->bind_param("i", $app_id);
                $restore->execute();
                if ($app['status'] === 'approved') {
                    $inc = $conn->prepare("UPDATE activities SET participants_count = participants_count + 1 WHERE id = ?");
                    $inc->bind_param("i", $app['activity_id']);
                    $inc->execute();
                }
                $conn->commit();
                $_SESSION['admin_message'] = "Application restored.";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['admin_message'] = 'Error: ' . $e->getMessage();
            }
        } else {
            $_SESSION['admin_message'] = 'Application is not archived.';
        }
    }
    // Approve / reject only for non-archived pending records
    elseif ($action === 'approve' || $action === 'reject') {
        if ($app['archived'] == 0 && $app['status'] === 'pending') {
            if ($action === 'approve') {
                $conn->begin_transaction();
                try {
                    $update = $conn->prepare("UPDATE volunteer_applications SET status = 'approved' WHERE id = ?");
                    $update->bind_param("i", $app_id);
                    $update->execute();
                    $inc = $conn->prepare("UPDATE activities SET participants_count = participants_count + 1 WHERE id = ?");
                    $inc->bind_param("i", $app['activity_id']);
                    $inc->execute();
                    $conn->commit();
                    $_SESSION['admin_message'] = "Application approved.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['admin_message'] = 'Error: ' . $e->getMessage();
                }
            } else { // reject
                $update = $conn->prepare("UPDATE volunteer_applications SET status = 'rejected' WHERE id = ?");
                $update->bind_param("i", $app_id);
                $update->execute();
                $_SESSION['admin_message'] = "Application rejected.";
            }
        } else {
            $_SESSION['admin_message'] = 'Application not pending or already archived.';
        }
    } else {
        $_SESSION['admin_message'] = 'Invalid action.';
    }

    $currentSort = $_GET['sort'] ?? 'latest';
    header('Location: application_activity.php?sort=' . $currentSort);
    exit;
}

// --- Bulk actions (archive / restore) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['selected_ids'])) {
    $bulk_action = $_POST['bulk_action'];
    $selected_ids = json_decode($_POST['selected_ids'], true);
    $currentSort = $_GET['sort'] ?? 'latest';
    if (!empty($selected_ids)) {
        $conn->begin_transaction();
        try {
            if ($bulk_action === 'archive') {
                foreach ($selected_ids as $id) {
                    $stmt = $conn->prepare("SELECT status, activity_id FROM volunteer_applications WHERE id = ? AND archived = 0");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $app = $stmt->get_result()->fetch_assoc();
                    if ($app && $app['status'] === 'approved') {
                        $dec = $conn->prepare("UPDATE activities SET participants_count = participants_count - 1 WHERE id = ? AND participants_count > 0");
                        $dec->bind_param("i", $app['activity_id']);
                        $dec->execute();
                    }
                }
                $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                $archiveStmt = $conn->prepare("UPDATE volunteer_applications SET archived = 1, archived_at = NOW() WHERE id IN ($placeholders)");
                $archiveStmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
                $archiveStmt->execute();
                $_SESSION['admin_message'] = count($selected_ids) . ' row(s) archived.';
            } elseif ($bulk_action === 'restore') {
                foreach ($selected_ids as $id) {
                    $stmt = $conn->prepare("SELECT status, activity_id FROM volunteer_applications WHERE id = ? AND archived = 1");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $app = $stmt->get_result()->fetch_assoc();
                    if ($app && $app['status'] === 'approved') {
                        $inc = $conn->prepare("UPDATE activities SET participants_count = participants_count + 1 WHERE id = ?");
                        $inc->bind_param("i", $app['activity_id']);
                        $inc->execute();
                    }
                }
                $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                $restoreStmt = $conn->prepare("UPDATE volunteer_applications SET archived = 0, archived_at = NULL WHERE id IN ($placeholders)");
                $restoreStmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
                $restoreStmt->execute();
                $_SESSION['admin_message'] = count($selected_ids) . ' row(s) restored.';
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['admin_message'] = 'Error: ' . $e->getMessage();
        }
    } else {
        $_SESSION['admin_message'] = 'No applications selected.';
    }
    header('Location: application_activity.php?sort=' . $currentSort);
    exit;
}

// Sorting logic – also determines whether to show archived
$sort = $_GET['sort'] ?? 'latest';
$showArchived = ($sort === 'archived'); // show archived only when sort is 'archived'

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
    case 'archived':
        $orderBy = "va.archived_at DESC, va.submitted_at DESC";
        break;
    default:
        $orderBy = "va.submitted_at DESC";
}

// Fetch statistics (only non‑archived for totals)
$totalActivities = $conn->query("SELECT COUNT(*) as count FROM activities")->fetch_assoc()['count'];
$totalJoined = $conn->query("SELECT COUNT(*) as count FROM volunteer_applications WHERE status = 'approved' AND archived = 0")->fetch_assoc()['count'];

// Fetch applications – condition based on $showArchived
$archivedCondition = $showArchived ? "va.archived = 1" : "va.archived = 0";
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
    WHERE $archivedCondition
    GROUP BY va.id
    ORDER BY $orderBy
";
$result = $conn->query($query);
$applications = $result->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../header.php';
?>

<link rel="stylesheet" href="application_activity.css">

<!-- Toast Notification -->
<div id="toast" class="toast hidden"></div>

<div class="application-container">
    <div class="app-header">
        <h2>Volunteer Applications</h2>
        <p>You can manage the activities and volunteer applications here</p>
    </div>

    <!-- Search + Filter -->
    <div class="search-filter">
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search">
        </div>
        <div class="filter-actions">
            <div class="sort-bar">
                <label>Sort By:</label>
                <div class="custom-select">
                    <select id="sortSelect">
                        <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
                        <option value="earliest" <?= $sort === 'earliest' ? 'selected' : '' ?>>Earliest</option>
                        <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status (Pending first)</option>
                        <option value="activity" <?= $sort === 'activity' ? 'selected' : '' ?>>Activity (A–Z)</option>
                        <option value="user" <?= $sort === 'user' ? 'selected' : '' ?>>User (A–Z)</option>
                        <option value="archived" <?= $sort === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
            <form method="POST" id="bulkActionForm" style="display: inline;">
                <input type="hidden" name="bulk_action" id="bulkActionType" value="">
                <input type="hidden" name="selected_ids" id="selectedIdsInput" value="">
                <?php if ($showArchived): ?>
                    <button type="submit" class="restore-btn" id="bulkRestoreBtn" disabled title="Restore Selected">
                        <i class="fas fa-undo-alt"></i>
                    </button>
                <?php else: ?>
                    <button type="submit" class="archive-btn" id="bulkArchiveBtn" disabled title="Archive Selected">
                        <i class="fa-solid fa-box-archive"></i>
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="card-app-grid">
        <div class="app-card">
            <div class="card-header">
                <h2>Total Activities</h2>
            </div>
            <div class="card-content">
                <h2><?= $totalActivities ?></h2>
                <p>Number of activities volunteers can join</p>
            </div>
        </div>
        <div class="app-card">
            <div class="card-header">
                <h2>Total Joined</h2>
            </div>
            <div class="card-content">
                <h2><?= $totalJoined ?></h2>
                <p>Number of active volunteers joined</p>
            </div>
        </div>
    </div>

    <div class="app-table">
        <form id="tableForm">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
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
                            <td colspan="11" style="text-align: center;">No applications found.<?= $showArchived ? ' (archived)' : '' ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><input type="checkbox" class="rowCheckbox" value="<?= $app['id'] ?>"></td>
                                <td>
                                    <strong><?= htmlspecialchars($app['fname'] . ' ' . $app['lname']) ?></strong><br>
                                    <small><?= htmlspecialchars($app['user_email']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($app['activity_title']) ?></td>
                                <td><?= htmlspecialchars($app['full_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($app['date_of_birth'])) ?></td>
                                <td><?= htmlspecialchars($app['mobile_number']) ?></td>
                                <td><?= htmlspecialchars($app['barangay']) ?></td>
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
                                    <?php if ($app['archived']): ?>
                                        <span class="status-badge status-archived">Archived</span>
                                    <?php else: ?>
                                        <span class="status-badge status-<?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y g:i A', strtotime($app['submitted_at'])) ?></td>
                                <td>
                                    <?php if ($app['archived']): ?>
                                        —
                                    <?php elseif ($app['status'] === 'pending'): ?>
                                        <div class="action-buttons">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                <button type="submit" name="action" value="approve" class="action-btn approve-btn" title="Approve">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
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
        </form>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="image-modal" onclick="closeImageModal()">
    <span class="image-modal-close">&times;</span>
    <img class="image-modal-content" id="modalImage">
</div>


<script src="application_activity.js"></script>

<script>
    <?php if (isset($_SESSION['admin_message'])): ?>
        showToast('<?= addslashes($_SESSION['admin_message']) ?>');
        <?php unset($_SESSION['admin_message']); ?>
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>