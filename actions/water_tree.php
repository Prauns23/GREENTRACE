<?php
declare(strict_types=1);

require_once __DIR__ . '/../init_session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../components/tree_growth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
if ($userId < 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sign in to tend a tree.']);
    exit;
}

$today = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
$todayString = $today->format('Y-m-d');
$advanced = false;
$maturedNow = false;

$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        "SELECT p.id AS progress_id, p.tree_species_id, p.growth_duration_days,
                p.started_on, p.matures_on, p.last_watered_on, p.status, ts.name
         FROM user_tree_progress p
         INNER JOIN tree_species ts ON ts.id = p.tree_species_id
         WHERE p.user_id = ? AND p.status = 'growing'
         ORDER BY p.id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $progress = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$progress) {
        $latestProgress = treeGrowthFetchProgress($conn, $userId, false);
        if ($latestProgress && ($latestProgress['status'] ?? '') === 'matured') {
            $conn->rollback();
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'Choose a new tree before watering again.',
                'state' => treeGrowthState($conn, $userId),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $species = treeGrowthDefaultSpecies($conn);
        if (!$species) {
            throw new RuntimeException('No tree species is available for growing.');
        }

        $speciesId = (int) $species['tree_species_id'];
        $duration = max(1, min(7, (int) $species['growth_duration_days']));
        $maturesOn = $today->modify('+' . ($duration - 1) . ' days')->format('Y-m-d');

        $stmt = $conn->prepare(
            "INSERT INTO user_tree_progress
                (user_id, tree_species_id, growth_duration_days, started_on, matures_on, last_watered_on)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('iiisss', $userId, $speciesId, $duration, $todayString, $maturesOn, $todayString);
        $stmt->execute();
        $progressId = (int) $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare(
            'INSERT INTO user_tree_watering_logs (user_tree_progress_id, watered_on) VALUES (?, ?)'
        );
        $stmt->bind_param('is', $progressId, $todayString);
        $stmt->execute();
        $stmt->close();
        $advanced = true;
        $message = $species['name'] . ' has started growing.';
    } else {
        $progressId = (int) $progress['progress_id'];
        $speciesId = (int) $progress['tree_species_id'];
        $duration = (int) $progress['growth_duration_days'];

        if ($todayString >= $progress['matures_on']) {
            $stmt = $conn->prepare(
                "UPDATE user_tree_progress
                 SET status = 'matured', matured_at = COALESCE(matured_at, NOW())
                 WHERE id = ?"
            );
            $stmt->bind_param('i', $progressId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare(
                "INSERT IGNORE INTO user_tree_badges
                    (user_id, tree_species_id, user_tree_progress_id, badge_code)
                 VALUES (?, ?, ?, 'tree_maturity')"
            );
            $stmt->bind_param('iii', $userId, $speciesId, $progressId);
            $stmt->execute();
            $stmt->close();
            $advanced = true;
            $maturedNow = true;
            $message = $progress['name'] . ' is fully mature. You earned its badge!';
        } elseif (($progress['last_watered_on'] ?? null) === $todayString) {
            $conn->rollback();
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'This tree has already been watered today.',
                'state' => treeGrowthState($conn, $userId),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO user_tree_watering_logs (user_tree_progress_id, watered_on) VALUES (?, ?)'
            );
            $stmt->bind_param('is', $progressId, $todayString);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('UPDATE user_tree_progress SET last_watered_on = ? WHERE id = ?');
            $stmt->bind_param('si', $todayString, $progressId);
            $stmt->execute();
            $stmt->close();
            $advanced = true;
            $message = $progress['name'] . ' advanced to its next growth phase.';
        }
    }

    $conn->commit();
    $state = treeGrowthState($conn, $userId);
    echo json_encode([
        'success' => true,
        'advanced' => $advanced,
        'maturedNow' => $maturedNow,
        'message' => $message,
        'state' => $state,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'The tree could not be watered. Please try again.',
    ]);
}

