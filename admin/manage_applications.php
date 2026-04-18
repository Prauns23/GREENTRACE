<?php
require_once '../init_session.php';
require_once '../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Handle approve/reject via POST (unchanged)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'], $_POST['action'])) {
    $app_id = (int)$_POST['application_id'];
    $action = $_POST['action']; // 'approve' or 'reject'

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
    header('Location: manage_applications.php');
    exit;
}

// Fetch applications with photos (using GROUP_CONCAT to list all files)
$query = "
    SELECT 
        va.*, 
        u.fname, u.lname, u.email as user_email, 
        a.title as activity_title,
        GROUP_CONCAT(vap.file_path SEPARATOR '|') as file_paths,
        GROUP_CONCAT(vap.original_name SEPARATOR '|') as file_names
    FROM volunteer_applications va
    JOIN users_tbl u ON va.user_id = u.id
    JOIN activities a ON va.activity_id = a.id
    LEFT JOIN application_photos vap ON vap.application_id = va.id
    GROUP BY va.id
    ORDER BY FIELD(va.status, 'pending') DESC, va.submitted_at DESC
";
$result = $conn->query($query);
$applications = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Volunteer Applications</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 16px; padding: 24px; }
        h1 { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; }
        .pending { background: #fff3cd; }
        .approved { background: #d4edda; }
        .rejected { background: #f8d7da; }
        .cancelled { background: #e2e3e5; }
        .btn { padding: 5px 12px; margin: 2px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-approve { background: #28a745; color: white; }
        .btn-reject { background: #dc3545; color: white; }
        .file-link { text-decoration: underline; color: #007bff; display: inline-block; margin-right: 8px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Volunteer Applications</h1>
    <?php if (isset($_SESSION['admin_message'])): ?>
        <p style="color:green"><?= htmlspecialchars($_SESSION['admin_message']) ?></p>
        <?php unset($_SESSION['admin_message']); ?>
    <?php endif; ?>
    <table>
        <thead>
            <tr><th>ID</th><th>User</th><th>Activity</th><th>Full Name</th><th>DOB</th><th>Mobile</th><th>Email</th><th>Barangay</th><th>Files</th><th>Status</th><th>Submitted</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php foreach ($applications as $app): ?>
            <tr class="<?= $app['status'] ?>">
                <td><?= $app['id'] ?></td>
                <td><?= htmlspecialchars($app['fname'] . ' ' . $app['lname'] . ' (' . $app['user_email'] . ')') ?></td>
                <td><?= htmlspecialchars($app['activity_title']) ?></td>
                <td><?= htmlspecialchars($app['full_name']) ?></td>
                <td><?= $app['date_of_birth'] ?></td>
                <td><?= htmlspecialchars($app['mobile_number']) ?></td>
                <td><?= htmlspecialchars($app['email']) ?></td>
                <td><?= htmlspecialchars($app['barangay']) ?></td>
                <td>
                    <?php 
                    if (!empty($app['file_paths'])) {
                        $paths = explode('|', $app['file_paths']);
                        $names = explode('|', $app['file_names']);
                        for ($i = 0; $i < count($paths); $i++) {
                            $fileUrl = '../' . $paths[$i];
                            $fileName = $names[$i] ?? 'File ' . ($i+1);
                            echo '<a href="' . htmlspecialchars($fileUrl) . '" target="_blank" class="file-link">' . htmlspecialchars($fileName) . '</a>';
                        }
                    } else {
                        echo '—';
                    }
                    ?>
                </td>
                <td><?= ucfirst($app['status']) ?></td>
                <td><?= $app['submitted_at'] ?></td>
                <td>
                    <?php if ($app['status'] === 'pending'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                            <button type="submit" name="action" value="approve" class="btn btn-approve">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-reject">Reject</button>
                        </form>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>