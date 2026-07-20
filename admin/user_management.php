<?php
require_once __DIR__ . '/../init_session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../pagination_helper.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: ../index.php');
    exit;
}

// Get page, sort, and search
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Sorting and filter
$sort = $_GET['sort'] ?? 'name_asc';
$showArchived = ($sort === 'archived');
$search = trim($_GET['search'] ?? '');

// Build WHERE clause
$where = "1=1";
if ($showArchived) {
    $where .= " AND u.archived = 1";
} else {
    $where .= " AND u.archived = 0";
}
// Exclude super_admin from non-super admins
if ($_SESSION['role'] !== 'super_admin') {
    $where .= " AND u.role != 'super_admin'";
}
if (!empty($search)) {
    $where .= " AND (u.fname LIKE ? OR u.lname LIKE ? OR u.email LIKE ?)";
    $searchParam = "%$search%";
}

// Order by
switch ($sort) {
    case 'name_desc':
        $orderBy = "u.fname DESC, u.lname DESC";
        break;
    case 'email_asc':
        $orderBy = "u.email ASC";
        break;
    case 'email_desc':
        $orderBy = "u.email DESC";
        break;
    case 'role_asc':
        $orderBy = "u.role ASC";
        break;
    case 'role_desc':
        $orderBy = "u.role DESC";
        break;
    case 'date_asc':
        $orderBy = "u.created_at ASC";
        break;
    case 'date_desc':
        $orderBy = "u.created_at DESC";
        break;
    case 'archived':
        $orderBy = "u.archived_at DESC";
        break;
    default:
        $orderBy = "u.fname ASC, u.lname ASC";
}

// Count total (for pagination)
$countSql = "SELECT COUNT(*) as total FROM users_tbl u WHERE $where";
$countStmt = $conn->prepare($countSql);
if (!empty($search)) {
    $countStmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
}
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$totalPages = ceil($total / $limit);

// Main query with pagination
$sql = "SELECT u.id, u.fname, u.lname, u.email, u.role, u.created_at, u.archived_at, b.name as barangay_name
        FROM users_tbl u
        LEFT JOIN barangays b ON u.barangay_id = b.id
        WHERE $where
        ORDER BY $orderBy
        LIMIT $offset, $limit";
$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Stats (only for active users, excluding super_admin for non-super admins)
$statsWhere = "archived = 0";
if ($_SESSION['role'] !== 'super_admin') {
    $statsWhere .= " AND role != 'super_admin'";
}
$totalActive = $conn->query("SELECT COUNT(*) as cnt FROM users_tbl WHERE $statsWhere")->fetch_assoc()['cnt'];
$totalAdmins = $conn->query("SELECT COUNT(*) as cnt FROM users_tbl WHERE role = 'admin' AND archived = 0")->fetch_assoc()['cnt'];
$totalUsers = $conn->query("SELECT COUNT(*) as cnt FROM users_tbl WHERE role = 'user' AND archived = 0")->fetch_assoc()['cnt'];

//  BULK ACTIONS (archive / restore) 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulkAction = $_POST['bulk_action'];
    $selectedIds = json_decode($_POST['selected_ids'], true);
    $currentSort = $_GET['sort'] ?? 'name_asc';
    $message = '';
    $type = 'success';

    // Check if any selected user is super_admin (and current user is not super_admin)
    if ($_SESSION['role'] !== 'super_admin') {
        $checkStmt = $conn->prepare("SELECT role FROM users_tbl WHERE id = ?");
        foreach ($selectedIds as $id) {
            $checkStmt->bind_param("i", $id);
            $checkStmt->execute();
            $result = $checkStmt->get_result()->fetch_assoc();
            if ($result && $result['role'] === 'super_admin') {
                $message = 'Cannot archive/restore a super admin.';
                $type = 'error';
                header("Location: user_management.php?sort=" . urlencode($currentSort) . "&toast=" . urlencode($message) . "&type=" . $type);
                exit;
            }
        }
    }

    if (!empty($selectedIds) && is_array($selectedIds)) {
        if ($bulkAction === 'archive') {
            if (in_array($_SESSION['user_id'], $selectedIds)) {
                $message = 'You cannot archive your own account.';
                $type = 'error';
            } else {
                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $stmt = $conn->prepare("UPDATE users_tbl SET archived = 1, archived_at = NOW() WHERE id IN ($placeholders)");
                $stmt->bind_param(str_repeat('i', count($selectedIds)), ...$selectedIds);
                if ($stmt->execute()) {
                    $message = count($selectedIds) . ' user(s) archived.';
                } else {
                    $message = 'Database error.';
                    $type = 'error';
                }
            }
        } elseif ($bulkAction === 'restore') {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $stmt = $conn->prepare("UPDATE users_tbl SET archived = 0, archived_at = NULL WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($selectedIds)), ...$selectedIds);
            if ($stmt->execute()) {
                $message = count($selectedIds) . ' user(s) restored.';
            } else {
                $message = 'Database error.';
                $type = 'error';
            }
        } else {
            $message = 'Invalid bulk action.';
            $type = 'error';
        }
    } else {
        $message = 'No users selected.';
        $type = 'error';
    }

    header("Location: user_management.php?sort=" . urlencode($currentSort) . "&toast=" . urlencode($message) . "&type=" . $type);
    exit;
}

// AJAX ACTIONS (single role update, archive, restore, edit) 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    // Single role update
    if ($action === 'update_role') {
        // Only super_admin can change roles
        if ($_SESSION['role'] !== 'super_admin') {
            echo json_encode(['error' => 'Only super admins can change roles.']);
            exit;
        }
        $newRole = $_POST['role'] ?? '';
        if (!in_array($newRole, ['admin', 'user'])) {
            echo json_encode(['error' => 'Invalid role']);
            exit;
        }
        if ($userId == $_SESSION['user_id']) {
            echo json_encode(['error' => 'You cannot change your own role']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE users_tbl SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $newRole, $userId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Role updated']);
        } else {
            echo json_encode(['error' => 'Database error']);
        }
        exit;
    }

    // Single archive
    if ($action === 'archive_user') {
        // Check if user is super_admin and current user is not super_admin
        $checkStmt = $conn->prepare("SELECT role FROM users_tbl WHERE id = ?");
        $checkStmt->bind_param("i", $userId);
        $checkStmt->execute();
        $targetUser = $checkStmt->get_result()->fetch_assoc();
        if ($targetUser && $targetUser['role'] === 'super_admin' && $_SESSION['role'] !== 'super_admin') {
            echo json_encode(['error' => 'Cannot archive a super admin.']);
            exit;
        }
        if ($userId == $_SESSION['user_id']) {
            echo json_encode(['error' => 'You cannot archive your own account']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE users_tbl SET archived = 1, archived_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User archived']);
        } else {
            echo json_encode(['error' => 'Database error']);
        }
        exit;
    }

    // Single restore
    if ($action === 'restore_user') {
        $checkStmt = $conn->prepare("SELECT role FROM users_tbl WHERE id = ?");
        $checkStmt->bind_param("i", $userId);
        $checkStmt->execute();
        $targetUser = $checkStmt->get_result()->fetch_assoc();
        if ($targetUser && $targetUser['role'] === 'super_admin' && $_SESSION['role'] !== 'super_admin') {
            echo json_encode(['error' => 'Cannot restore a super admin.']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE users_tbl SET archived = 0, archived_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User restored']);
        } else {
            echo json_encode(['error' => 'Database error']);
        }
        exit;
    }

    // Edit user (full update)
    if ($action === 'update_user') {
        $fname = trim($_POST['fname'] ?? '');
        $lname = trim($_POST['lname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone_no'] ?? '');
        $role = $_POST['role'] ?? '';
        if (!in_array($role, ['admin', 'user'])) {
            echo json_encode(['error' => 'Invalid role']);
            exit;
        }
        if (empty($fname) || empty($lname) || empty($email)) {
            echo json_encode(['error' => 'Name and email are required']);
            exit;
        }
        $check = $conn->prepare("SELECT id FROM users_tbl WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $userId);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['error' => 'Email already taken']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE users_tbl SET fname = ?, lname = ?, email = ?, phone_no = ?, role = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $fname, $lname, $email, $phone, $role, $userId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Database error']);
        }
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
    exit;
}

// --- GET TOAST FROM URL ---
$toastMessage = isset($_GET['toast']) ? $_GET['toast'] : '';
$toastType = isset($_GET['type']) && $_GET['type'] === 'error' ? 'error' : 'success';

include __DIR__ . '/../header.php';
?>

<link rel="stylesheet" href="user_management.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="../pagination.css">

<div class="user-container">
    <div class="user-header">
        <h2>User Management</h2>
        <p>Manage system users, roles, and archive inactive accounts</p>
    </div>

    <!-- Search and Filter Bar -->
    <div class="search-filter">
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search by name or email" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-actions">
            <div class="sort-bar">
                <label>Sort By:</label>
                <div class="custom-select">
                    <select id="sortSelect">
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A–Z)</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z–A)</option>
                        <option value="email_asc" <?= $sort === 'email_asc' ? 'selected' : '' ?>>Email (A–Z)</option>
                        <option value="email_desc" <?= $sort === 'email_desc' ? 'selected' : '' ?>>Email (Z–A)</option>
                        <option value="role_asc" <?= $sort === 'role_asc' ? 'selected' : '' ?>>Role (User first)</option>
                        <option value="role_desc" <?= $sort === 'role_desc' ? 'selected' : '' ?>>Role (Admin first)</option>
                        <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Oldest first</option>
                        <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Newest first</option>
                        <option value="archived" <?= $sort === 'archived' ? 'selected' : '' ?>>Archived Users</option>
                    </select>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
            <form method="POST" id="bulkActionForm" class="bulk-action-form">
                <input type="hidden" name="bulk_action" id="bulkActionType" value="">
                <input type="hidden" name="selected_ids" id="selectedIdsInput" value="">
                <?php if ($showArchived): ?>
                    <button type="submit" class="bulk-restore-btn" id="bulkRestoreBtn" disabled title="Restore Selected">
                        <i class="fas fa-undo-alt"></i>
                    </button>
                <?php else: ?>
                    <button type="submit" class="bulk-archive-btn" id="bulkArchiveBtn" disabled title="Archive Selected">
                        <i class="fas fa-archive"></i>
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="card-app-grid">
        <div class="app-card">
            <div class="card-header" #active_users>
                <h2>Total Active</h2>
            </div>
            <div class="card-content">
                <h2><?= $totalActive ?></h2>
                <p>Active users</p>
            </div>
        </div>
        <div class="app-card">
            <div class="card-header">
                <h2>Admins</h2>
            </div>
            <div class="card-content">
                <h2><?= $totalAdmins ?></h2>
                <p>Administrators</p>
            </div>
        </div>
        <div class="app-card">
            <div class="card-header">
                <h2>Regular Users</h2>
            </div>
            <div class="card-content">
                <h2><?= $totalUsers ?></h2>
                <p>Standard accounts</p>
            </div>
        </div>
    </div>
    <!-- Users Table -->
    <div class="app-table">
        <form id="bulkSelectForm" style="margin:0;">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Barangay</th>
                        <th><?= $showArchived ? 'Archived At' : 'Registered' ?></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No users found.<?= !empty($search) ? ' Try a different search.' : '' ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr data-user-id="<?= $user['id'] ?>">
                                <td><input type="checkbox" class="rowCheckbox" value="<?= $user['id'] ?>"></td>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <?php if ($_SESSION['role'] === 'super_admin'): ?>
                                        <select class="role-select" data-user-id="<?= $user['id'] ?>">
                                            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                    <?php else: ?>
                                        <span><?= ucfirst($user['role']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($user['barangay_name'] ?? '—') ?></td>
                                <td>
                                    <?php if ($showArchived): ?>
                                        <?= date('M d, Y g:i A', strtotime($user['archived_at'])) ?>
                                    <?php else: ?>
                                        <?= date('M d, Y', strtotime($user['created_at'])) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <button class="action-btn edit-btn" title="Edit User" data-user-id="<?= $user['id'] ?>"><i class="fas fa-pen"></i></button>
                                    <?php if ($showArchived): ?>
                                        <button class="action-btn restore-single-btn" title="Restore User" data-user-id="<?= $user['id'] ?>"><i class="fas fa-undo-alt"></i></button>
                                    <?php else: ?>
                                        <button class="action-btn archive-single-btn" title="Archive User" data-user-id="<?= $user['id'] ?>"><i class="fas fa-archive"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="pagination">
                <?php
                $queryParams = ['sort' => $sort];
                if (!empty($search)) {
                    $queryParams['search'] = $search;
                }
                echo renderPagination($page, $totalPages, 'user_management.php', $queryParams);
                ?>
            </div>
        </form>
    </div>
</div>

<!-- User Detail Modal-->
<div id="userDetailModal" class="modal-overlay">
    <div class="modal-content user-detail-modal">
        <span class="modal-close">&times;</span>
        <h3>User Details</h3>
        <div id="userDetailContent"></div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal-overlay">
    <div class="modal-content edit-user-modal">
        <span class="modal-close">&times;</span>
        <h3>Edit User</h3>
        <form id="editUserForm">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="fname" id="edit_fname" required>
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="lname" id="edit_lname" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="edit_email" required>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone_no" id="edit_phone">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" id="edit_role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                    <?php if ($_SESSION['role'] === 'super_admin'): ?>
                        <option value="super_admin">Super Admin</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="bottom-button">
                <button type="submit" class="submit-btn">Save Changes</button>
                <button type="button" class="cancel-btn modal-cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="user_management.js"></script>

<script>
    // Show toast from URL parameter
    const toastMsg = <?php echo json_encode($toastMessage); ?>;
    const toastType = <?php echo json_encode($toastType); ?>;

    if (toastMsg) {
        const cleanUrl = window.location.pathname + window.location.search.replace(/[&?]toast=[^&]*/g, '').replace(/[&?]type=[^&]*/g, '').replace(/[?&]$/, '');
        window.history.replaceState({}, document.title, cleanUrl);
        setTimeout(() => {
            if (typeof parent !== 'undefined' && parent.showToast) {
                parent.showToast(toastMsg, 5000, toastType);
            } else if (typeof showToast === 'function') {
                showToast(toastMsg, 5000, toastType);
            } else {
                alert(toastMsg);
            }
        }, 500);
    }
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>