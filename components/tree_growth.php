<?php
declare(strict_types=1);

function treeGrowthDefaultSpecies(mysqli $conn): ?array
{
    $sql = "SELECT ts.id AS tree_species_id, ts.name, ts.scientific_name, ts.category,
                   ts.description, ts.fun_fact, gp.growth_duration_days, gp.growth_asset_key
            FROM tree_species ts
            INNER JOIN tree_species_growth_profiles gp ON gp.tree_species_id = ts.id
            WHERE ts.archived = 0
            ORDER BY CASE WHEN gp.growth_asset_key = 'narra-v1' THEN 0 ELSE 1 END, ts.name
            LIMIT 1";
    $result = $conn->query($sql);
    $species = $result?->fetch_assoc();
    return is_array($species) ? $species : null;
}

function treeGrowthFetchProgress(mysqli $conn, int $userId, bool $activeOnly): ?array
{
    $statusClause = $activeOnly ? "AND p.status = 'growing'" : '';
    $sql = "SELECT p.id AS progress_id, p.user_id, p.tree_species_id,
                   p.growth_duration_days, p.started_on, p.matures_on,
                   p.last_watered_on, p.status, p.matured_at,
                   ts.name, ts.scientific_name, ts.category, ts.description,
                   ts.fun_fact, gp.growth_asset_key
            FROM user_tree_progress p
            INNER JOIN tree_species ts ON ts.id = p.tree_species_id
            LEFT JOIN tree_species_growth_profiles gp ON gp.tree_species_id = p.tree_species_id
            WHERE p.user_id = ? {$statusClause}
            ORDER BY p.id DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $progress = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return is_array($progress) ? $progress : null;
}

function treeGrowthFinalizeIfDue(mysqli $conn, array $progress, DateTimeImmutable $today): array
{
    if (($progress['status'] ?? '') !== 'growing' || $today->format('Y-m-d') < $progress['matures_on']) {
        return $progress;
    }

    $progressId = (int) $progress['progress_id'];
    $userId = (int) $progress['user_id'];
    $speciesId = (int) $progress['tree_species_id'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "UPDATE user_tree_progress
             SET status = 'matured', matured_at = COALESCE(matured_at, NOW())
             WHERE id = ? AND status = 'growing'"
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
        $conn->commit();

        $progress['status'] = 'matured';
        $progress['matured_at'] = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }

    return $progress;
}

function treeGrowthWateringCount(mysqli $conn, int $progressId): int
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS watering_count FROM user_tree_watering_logs WHERE user_tree_progress_id = ?'
    );
    $stmt->bind_param('i', $progressId);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['watering_count'] ?? 0);
    $stmt->close();
    return $count;
}

function treeGrowthMaturedCount(mysqli $conn, int $userId): int
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS matured_count
         FROM user_tree_badges
         WHERE user_id = ? AND badge_code = 'tree_maturity'"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['matured_count'] ?? 0);
    $stmt->close();
    return $count;
}

function treeGrowthState(mysqli $conn, ?int $userId): array
{
    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
    $progress = $userId ? treeGrowthFetchProgress($conn, $userId, true) : null;

    if ($progress) {
        $progress = treeGrowthFinalizeIfDue($conn, $progress, $today);
    } elseif ($userId) {
        $progress = treeGrowthFetchProgress($conn, $userId, false);
    }

    $species = $progress ?: treeGrowthDefaultSpecies($conn);
    if (!$species) {
        throw new RuntimeException('No tree species has a growth profile.');
    }

    $duration = max(1, min(7, (int) $species['growth_duration_days']));
    $hasTree = isset($progress['progress_id']);
    $wateringCount = $hasTree ? treeGrowthWateringCount($conn, (int) $progress['progress_id']) : 0;
    $status = $hasTree ? (string) $progress['status'] : 'available';
    $wateredToday = $hasTree && ($progress['last_watered_on'] ?? null) === $today->format('Y-m-d');

    if ($status === 'matured') {
        $day = $duration;
    } else {
        $day = min($duration, $wateringCount);
    }

    $isAuthenticated = $userId !== null;
    $canWater = $isAuthenticated && $status !== 'matured' && !$wateredToday;
    $nextWaterAt = $wateredToday ? $today->modify('+1 day')->format(DateTimeInterface::ATOM) : null;

    if (!$isAuthenticated) {
        $message = 'Sign in to begin tending a tree.';
    } elseif ($status === 'matured') {
        $message = 'This tree is fully mature. Choose a new tree to continue.';
    } elseif ($wateredToday) {
        $message = 'Tree already watered today. Come back tomorrow.';
    } elseif (!$hasTree) {
        $message = 'Water this tree to begin growing.';
    } else {
        $message = 'Your tree is ready to be watered today.';
    }

    return [
        'authenticated' => $isAuthenticated,
        'hasTree' => $hasTree,
        'progressId' => $hasTree ? (int) $progress['progress_id'] : null,
        'speciesId' => (int) $species['tree_species_id'],
        'name' => (string) $species['name'],
        'scientificName' => (string) $species['scientific_name'],
        'category' => ucfirst((string) $species['category']),
        'description' => (string) ($species['description'] ?? ''),
        'funFact' => (string) (($species['fun_fact'] ?? '') ?: ($species['description'] ?? '')),
        'growthAssetKey' => (string) ($species['growth_asset_key'] ?? 'narra-v1'),
        'day' => $day,
        'duration' => $duration,
        'progressPercent' => round(($day / $duration) * 100, 4),
        'status' => $status,
        'wateredToday' => $wateredToday,
        'canWater' => $canWater,
        'nextWaterAt' => $nextWaterAt,
        'message' => $message,
        'maturedCount' => $userId ? treeGrowthMaturedCount($conn, $userId) : 0,
        'illustrationSvg' => treeGrowthIllustrationSvg($day),
    ];
}

function treeGrowthIllustrationSvg(int $day, string $className = 'tree-growth-illustration'): string
{
    $day = max(0, min(7, $day));
    $className = htmlspecialchars($className, ENT_QUOTES, 'UTF-8');
    ob_start();
    ?>
<svg class="<?= $className ?>" data-tree-stage="<?= $day ?>" viewBox="0 0 320 320" aria-hidden="true" focusable="false">
    <g class="tree-growth-illustration__soil">
        <ellipse cx="160" cy="276" rx="120" ry="10" fill="oklch(0.78 0.04 90)" />
        <ellipse cx="160" cy="272" rx="80" ry="6" fill="oklch(0.55 0.05 80)" opacity=".5" />
    </g>
    <?php if ($day === 0): ?>
        <g class="tree-growth-illustration__trunk">
            <ellipse cx="160" cy="265" rx="10" ry="6" fill="oklch(0.35 0.05 60)" />
            <path d="M160 260q2-7 7-5" fill="none" stroke="oklch(0.55 0.12 145)" stroke-linecap="round" stroke-width="2" />
        </g>
    <?php elseif ($day === 1): ?>
        <g class="tree-growth-illustration__trunk">
            <rect x="156" y="234" width="8" height="42" rx="4" fill="oklch(0.34 0.04 50)" />
            <path d="M160 239q-14-5-10-18M160 245q14-5 10-18" fill="none" stroke="oklch(0.55 0.12 145)" stroke-linecap="round" stroke-width="4" />
        </g>
    <?php else:
        $trunkHeights = [2 => 62, 3 => 82, 4 => 108, 5 => 130, 6 => 150, 7 => 170];
        $canopyY = [2 => 194, 3 => 169, 4 => 134, 5 => 110, 6 => 92, 7 => 78];
        $canopyRadius = [2 => 34, 3 => 45, 4 => 58, 5 => 70, 6 => 80, 7 => 90];
        $trunkHeight = $trunkHeights[$day];
        $trunkY = 276 - $trunkHeight;
        $centerY = $canopyY[$day];
        $radius = $canopyRadius[$day];
        $sideRadius = round($radius * .7, 1);
        $sideOffset = round($radius * .6, 1);
        $topRadius = round($radius * .55, 1);
        $trunkWidth = 10 + (($day - 2) * 2);
        $trunkX = 160 - ($trunkWidth / 2);
        ?>
        <g class="tree-growth-illustration__trunk">
            <rect x="<?= $trunkX ?>" y="<?= $trunkY ?>" width="<?= $trunkWidth ?>" height="<?= $trunkHeight ?>" rx="<?= $trunkWidth / 2 ?>" fill="oklch(0.34 0.04 50)" />
            <path d="M<?= 160 - $trunkWidth / 2 ?> 276q-10-2-16 2m<?= $trunkWidth ?>-2q10-2 16 2" fill="none" stroke="oklch(0.30 0.04 50)" stroke-linecap="round" stroke-width="3" />
            <?php if ($day >= 5): ?>
                <path d="M160 <?= $trunkY + 48 ?>q-24-16-28-38M160 <?= $trunkY + 56 ?>q26-18 30-42" fill="none" stroke="oklch(0.30 0.04 50)" stroke-linecap="round" stroke-width="6" />
            <?php endif; ?>
        </g>
        <g class="tree-growth-illustration__canopy">
            <circle cx="160" cy="<?= $centerY ?>" r="<?= $radius ?>" fill="oklch(0.45 0.09 150)" />
            <circle cx="<?= 160 - $sideOffset ?>" cy="<?= $centerY + $radius * .2 ?>" r="<?= $sideRadius ?>" fill="oklch(0.5 0.1 148)" />
            <circle cx="<?= 160 + $sideOffset ?>" cy="<?= $centerY + $radius * .2 ?>" r="<?= $sideRadius ?>" fill="oklch(0.55 0.1 145)" />
            <circle cx="160" cy="<?= $centerY - $radius * .5 ?>" r="<?= $topRadius ?>" fill="oklch(0.6 0.11 142)" />
        </g>
    <?php endif; ?>
</svg>
    <?php
    return trim((string) ob_get_clean());
}

