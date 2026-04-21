<?php
require_once __DIR__ . '/../init_session.php';
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Handle BULK actions first (archive/restore multiple users)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulkAction = $_POST['bulk_action'];
    $selectedIds = json_decode($_POST['selected_ids'], true);
    $currentSort = $_GET['sort'] ?? 'name_asc';
    $message = '';
    $type = 'success';

    if (!empty($selectedIds) && is_array($selectedIds)) {
        if ($bulkAction === 'archive') {
            // Prevent archiving own account
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

// Handle AJAX actions (single role update, archive, restore, edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    // Single role update
    if ($action === 'update_role') {
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
        $userId = (int)($_POST['user_id'] ?? 0);
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

// Handle BULK actions (archive / restore)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulkAction = $_POST['bulk_action'];
    $selectedIds = json_decode($_POST['selected_ids'], true);
    $currentSort = $_GET['sort'] ?? 'name_asc';
    $message = '';
    $type = 'success';

    if (!empty($selectedIds) && is_array($selectedIds)) {
        if ($bulkAction === 'archive') {
            // Prevent archiving own account
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

// Get toast from URL
$toastMessage = isset($_GET['toast']) ? $_GET['toast'] : '';
$toastType = isset($_GET['type']) && $_GET['type'] === 'error' ? 'error' : 'success';

// Sorting and filter (show archived or active)
$sort = $_GET['sort'] ?? 'name_asc';
$showArchived = ($sort === 'archived'); // special sort value to show archived users

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

// Build WHERE clause based on showArchived
if ($showArchived) {
    $whereClause = "u.archived = 1";
} else {
    $whereClause = "u.archived = 0";
}

$search = trim($_GET['search'] ?? '');
if (!empty($search)) {
    $whereClause .= " AND (u.fname LIKE ? OR u.lname LIKE ? OR u.email LIKE ?)";
    $searchParam = "%$search%";
}

$sql = "SELECT u.id, u.fname, u.lname, u.email, u.role, u.created_at, u.archived_at 
        FROM users_tbl u 
        WHERE $whereClause
        ORDER BY $orderBy";

$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats (only for active users)
$totalActive = $conn->query("SELECT COUNT(*) as cnt FROM users_tbl WHERE archived = 0")->fetch_assoc()['cnt'];
$totalAdmins = $conn->query("SELECT COUNT(*) as cnt FROM users_tbl WHERE role = 'admin' AND archived = 0")->fetch_assoc()['cnt'];
$totalUsers = $conn->query("SELECT COUNT(*) as cnt FROM users_tbl WHERE role = 'user' AND archived = 0")->fetch_assoc()['cnt'];

include __DIR__ . '/../header.php';
?>

<link rel="stylesheet" href="user_management.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

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
            <!-- Bulk Action Button -->
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
            <div class="card-header">
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
                        <th><?= $showArchived ? 'Archived At' : 'Registered' ?></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No users found.<?= !empty($search) ? ' Try a different search.' : '' ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr data-user-id="<?= $user['id'] ?>">
                                <td><input type="checkbox" class="rowCheckbox" value="<?= $user['id'] ?>"></td>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <select class="role-select" data-user-id="<?= $user['id'] ?>">
                                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </td>
                                <td>
                                    <?php if ($showArchived): ?>
                                        <?= date('M d, Y g:i A', strtotime($user['archived_at'])) ?>
                                    <?php else: ?>
                                        <?= date('M d, Y', strtotime($user['created_at'])) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <button class="action-btn view-btn" title="View Details" data-user-id="<?= $user['id'] ?>"><i class="fas fa-eye"></i></button>
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
        </form>
    </div>
</div>

<!-- User Detail Modal -->
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