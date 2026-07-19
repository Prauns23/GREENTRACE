<?php
require_once __DIR__ . '/../init_session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../log_activity.php';
require_once __DIR__ . '/../notifications_helper.php';
require_once __DIR__ . '/../error_logger.php';
require_once __DIR__ . '/../pagination_helper.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: ../index.php');
    exit;
}

// ---- SINGLE ACTION (approve / reject / restore) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'], $_POST['action']) && !isset($_POST['bulk_action'])) {
    $app_id = (int)$_POST['application_id'];
    $action = $_POST['action'];
    $currentSort = $_GET['sort'] ?? 'latest';
    $message = '';
    $type = 'success';

    // CSRF validation
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid CSRF Token. Please refresh and try again.';
        $type = 'error';
        header("Location: application_activity.php?sort=" . urlencode($currentSort) . "&toast=" . urlencode($message) . "&type=" . $type);
        exit;
    }

    $stmt = $conn->prepare("SELECT user_id, activity_id, status, archived FROM volunteer_applications WHERE id = ?");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();

    if (!$app) {
        $message = 'Application not found.';
        $type = 'error';
    } else {
        // RESTORE
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
                    $message = "Application restored.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = 'Error: ' . $e->getMessage();
                    $type = 'error';
                    logError($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()]);
                }
            } else {
                $message = 'Application is not archived.';
                $type = 'error';
            }
        }

        // APPROVE or REJECT
        elseif ($action === 'approve' || $action === 'reject') {
            if (($app['archived'] != 1) && $app['status'] === 'pending') {
                $actTitleStmt = $conn->prepare("SELECT title FROM activities WHERE id = ?");
                $actTitleStmt->bind_param("i", $app['activity_id']);
                $actTitleStmt->execute();
                $actTitle = $actTitleStmt->get_result()->fetch_assoc()['title'] ?? 'the activity';
                $actTitleStmt->close();

                if ($action === 'approve') {
                    // Capacity check
                    $capStmt = $conn->prepare("SELECT capacity, participants_count FROM activities WHERE id = ?");
                    $capStmt->bind_param("i", $app['activity_id']);
                    $capStmt->execute();
                    $activity = $capStmt->get_result()->fetch_assoc();
                    $capStmt->close();

                    if ($activity && $activity['participants_count'] >= $activity['capacity']) {
                        $message = 'Cannot approve: Activity is full.';
                        $type = 'error';
                    } else {
                        $conn->begin_transaction();
                        try {
                            $update = $conn->prepare("UPDATE volunteer_applications SET status = 'approved' WHERE id = ?");
                            $update->bind_param("i", $app_id);
                            $update->execute();

                            $inc = $conn->prepare("UPDATE activities SET participants_count = participants_count + 1 WHERE id = ?");
                            $inc->bind_param("i", $app['activity_id']);
                            $inc->execute();

                            $conn->commit();
                            $message = "Application approved.";

                            // Notify the user
                            logActivity($app['user_id'], 'application', $app_id, $actTitle, 'approved', "Your application for <strong>$actTitle</strong> has been <strong>approved</strong>.");
                            $userNotifTitle = "Application Approved";
                            $userNotifMessage = "Your application for <strong>$actTitle</strong> has been <strong>approved</strong>! Check the schedule often!";
                            $userLink = "activities.php?open_activity={$app['activity_id']}";
                            createNotification($app['user_id'], 'application', $userNotifTitle, $userNotifMessage, $userLink);

                            // Notify all admins
                            $adminStmt = $conn->prepare("SELECT id FROM users_tbl WHERE role IN ('admin', 'super_admin') AND archived = 0");
                            $adminStmt->execute();
                            $admins = $adminStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            $adminStmt->close();

                            $adminNotifTitle = "Application Approved";
                            $adminNotifMessage = "Application for <strong>$actTitle</strong> has been approved by <strong>" . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . "</strong>";
                            $adminLink = "admin/application_activity.php";
                            foreach ($admins as $admin) {
                                createNotification($admin['id'], 'application', $adminNotifTitle, $adminNotifMessage, $adminLink);
                            }
                        } catch (Exception $e) {
                            $conn->rollback();
                            $message = 'Error: ' . $e->getMessage();
                            $type = 'error';
                            logError($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()]);
                        }
                    }
                } else { // REJECT
                    $conn->begin_transaction();
                    try {
                        $update = $conn->prepare("UPDATE volunteer_applications SET status = 'rejected' WHERE id = ?");
                        $update->bind_param("i", $app_id);
                        $update->execute();
                        $conn->commit();
                        $message = "Application rejected.";

                        // Notify the user
                        logActivity($app['user_id'], 'application', $app_id, $actTitle, 'rejected', "Your application for <strong>$actTitle</strong> has been <strong>rejected</strong>.");
                        $userNotifTitle = "Application Rejected";
                        $userNotifMessage = "Your application for <strong>$actTitle</strong> was <strong>rejected</strong>. Please recheck your documents and resubmit.";
                        $userLink = "activities.php?open_activity={$app['activity_id']}";
                        createNotification($app['user_id'], 'application', $userNotifTitle, $userNotifMessage, $userLink);

                        // Notify all admins
                        $adminStmt = $conn->prepare("SELECT id FROM users_tbl WHERE role IN ('admin', 'super_admin') AND archived = 0");
                        $adminStmt->execute();
                        $admins = $adminStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $adminStmt->close();

                        $adminNotifTitle = "Application Rejected";
                        $adminNotifMessage = "Application for <strong>$actTitle</strong> has been rejected by <strong>" . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . "</strong>";
                        $adminLink = "admin/application_activity.php";
                        foreach ($admins as $admin) {
                            createNotification($admin['id'], 'application', $adminNotifTitle, $adminNotifMessage, $adminLink);
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = 'Error: ' . $e->getMessage();
                        $type = 'error';
                        logError($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()]);
                    }
                }
            } else {
                $message = 'Application not pending or already archived.';
                $type = 'error';
            }
        } else {
            $message = 'Invalid action.';
            $type = 'error';
        }
    }

    header("Location: application_activity.php?sort=" . urlencode($currentSort) . "&toast=" . urlencode($message) . "&type=" . $type);
    exit;
}

// ---- BULK ACTIONS (archive / restore) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['selected_ids'])) {
    $bulk_action = $_POST['bulk_action'];
    $selected_ids = json_decode($_POST['selected_ids'], true);
    $currentSort = $_GET['sort'] ?? 'latest';
    $message = '';
    $type = 'success';

    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid CSRF token. Please refresh and try again.';
        $type = 'error';
        header("Location: application_activity.php?sort=" . urlencode($currentSort) . "&toast=" . urlencode($message) . "&type=" . $type);
        exit;
    }

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
                $message = count($selected_ids) . ' row(s) archived.';
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
                $message = count($selected_ids) . ' row(s) restored.';
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $message = 'Error: ' . $e->getMessage();
            $type = 'error';
            logError($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()]);
        }
    } else {
        $message = 'No applications selected.';
        $type = 'error';
    }

    header("Location: application_activity.php?sort=" . urlencode($currentSort) . "&toast=" . urlencode($message) . "&type=" . $type);
    exit;
}

//  GET TOAST FROM URL 
$toastMessage = isset($_GET['toast']) ? $_GET['toast'] : '';
$toastType = isset($_GET['type']) && $_GET['type'] === 'error' ? 'error' : 'success';

//  SORTING & PAGINATION 
$sort = $_GET['sort'] ?? 'latest';
$showArchived = ($sort === 'archived');
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$archivedCondition = $showArchived ? "va.archived = 1" : "va.archived = 0";
$countSql = "SELECT COUNT(*) as total FROM volunteer_applications va JOIN users_tbl u ON va.user_id = u.id WHERE $archivedCondition";
$countStmt = $conn->prepare($countSql);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$totalPages = ceil($total / $limit);

switch ($sort) {
    case 'earliest':  $orderBy = "va.submitted_at ASC"; break;
    case 'status':    $orderBy = "FIELD(va.status, 'pending', 'approved', 'rejected', 'cancelled')"; break;
    case 'activity':  $orderBy = "a.title ASC"; break;
    case 'user':      $orderBy = "u.fname ASC, u.lname ASC"; break;
    case 'archived':  $orderBy = "va.archived_at DESC, va.submitted_at DESC"; break;
    default:          $orderBy = "va.submitted_at DESC";
}

$query = "
    SELECT
        va.*,
        u.fname, u.lname, u.email as user_email,
        b.name as current_barangay,
        a.title as activity_title,
        GROUP_CONCAT(ap.file_path SEPARATOR '|') as file_paths,
        GROUP_CONCAT(ap.original_name SEPARATOR '|') as file_names
    FROM volunteer_applications va
    JOIN users_tbl u ON va.user_id = u.id
    LEFT JOIN barangays b ON u.barangay_id = b.id
    JOIN activities a ON va.activity_id = a.id
    LEFT JOIN application_photos ap ON ap.application_id = va.id
    WHERE $archivedCondition
    GROUP BY va.id
    ORDER BY $orderBy
    LIMIT $offset, $limit
";
$applications = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

$totalActivities = $conn->query("SELECT COUNT(*) as count FROM activities")->fetch_assoc()['count'];
$totalJoined = $conn->query("SELECT COUNT(*) as count FROM volunteer_applications WHERE status = 'approved' AND archived = 0")->fetch_assoc()['count'];

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
                <?php csrf_field(); ?>
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
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>User</th>
                    <th>Activity</th>
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
                    <tr><td colspan="11" style="text-align: center;">No applications found.<?= $showArchived ? ' (archived)' : '' ?></td></tr>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <?php
                        $rowClass = '';
                        if ($app['archived']) $rowClass = 'archived-row';
                        elseif ($app['status'] === 'rejected' || $app['status'] === 'cancelled') $rowClass = 'status-negative-row';
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td><input type="checkbox" class="rowCheckbox" value="<?= $app['id'] ?>"></td>
                            <td><strong><?= htmlspecialchars($app['fname'] . ' ' . $app['lname']) ?></strong></td>
                            <td><?= htmlspecialchars($app['activity_title']) ?></td>
                            <td><?= date('M d, Y', strtotime($app['date_of_birth'])) ?></td>
                            <td><?= htmlspecialchars($app['mobile_number']) ?></td>
                            <td><?= htmlspecialchars($app['current_barangay'] ?? $app['barangay']) ?></td>
                            <td>
                                <?php if (!empty($app['file_paths'])): ?>
                                    <?php
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
                                else: echo '—';
                                endif; ?>
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
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                            <button type="submit" name="action" value="approve" class="action-btn approve-btn" title="Approve">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                            <button type="submit" name="action" value="reject" class="action-btn reject-btn" title="Reject">
                                                <i class="fas fa-times-circle"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php
    $queryParams = ['sort' => $sort];
    echo renderPagination($page, $totalPages, 'application_activity.php', $queryParams);
    ?>
</div>

<!-- Image Modal -->
<div id="imageModal" class="image-modal" onclick="closeImageModal()">
    <span class="image-modal-close">&times;</span>
    <img class="image-modal-content" id="modalImage">
</div>

<script src="application_activity.js"></script>

<script>
    const toastMsg = <?php echo json_encode($toastMessage); ?>;
    const toastType = <?php echo json_encode($toastType); ?>;
    if (toastMsg) {
        const cleanUrl = window.location.pathname + window.location.search.replace(/[&?]toast=[^&]*/g, '').replace(/[&?]type=[^&]*/g, '').replace(/[?&]$/, '');
        window.history.replaceState({}, document.title, cleanUrl);
        setTimeout(() => {
            if (typeof showToast === 'function') {
                showToast(toastMsg, 5000, toastType);
            } else {
                alert(toastMsg);
            }
        }, 500);
    }
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>