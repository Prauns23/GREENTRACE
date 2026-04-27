<div class="activity-card <?= $activity['date'] < $today ? 'past-activity' : '' ?>" data-activity-id="<?= $activity['id'] ?>" onclick="<?= $activity['date'] >= $today ? 'showActivityDetails(' . $activity['id'] . ')' : '' ?>">
    <div class="activity-prev">
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <div class="activity-menu-trigger" onclick="event.stopPropagation(); toggleActivityMenu(this)">
                <i class="fa-solid fa-ellipsis-vertical"></i>
                <div class="activity-menu-dropdown" style="display: none;">
                    <button onclick="event.stopPropagation(); editActivity(<?= $activity['id'] ?>)">Edit</button>
                    <button onclick="event.stopPropagation(); archiveActivity(<?= $activity['id'] ?>)">Archive</button>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($activity['image_url'])): ?>
            <img src="<?= htmlspecialchars($activity['image_url']) ?>" alt="<?= htmlspecialchars($activity['title']) ?>" style="width:100%; height:100%; object-fit:cover;">
        <?php endif; ?>
    </div>
    <div class="card-content">
        <div class="badges-row">
            <?php if ($activity['date'] < $today): ?>
                <span class="activity-badge closed">Closed</span>
            <?php else: ?>
                <?php if (!empty($activity['badge_primary'])): ?>
                    <span class="activity-badge primary"><?= htmlspecialchars($activity['badge_primary']) ?></span>
                <?php endif; ?>
                <?php if (!empty($activity['badge_secondary'])): ?>
                    <span class="activity-badge secondary"><?= htmlspecialchars($activity['badge_secondary']) ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <h3><?= htmlspecialchars($activity['title']) ?></h3>
        <p><?= htmlspecialchars($activity['description']) ?></p>
        <div class="meta-row">
            <div class="bottom-content">
                <i class="fa-regular fa-calendar"></i>
                <span class="date"><?= date('F j, Y', strtotime($activity['date'])) ?></span>
            </div>
            <div class="bottom-content">
                <span class="material-symbols-rounded">group</span>
                <span class="slots"><?= $activity['participants_count'] ?> Participants</span>
            </div>
        </div>
    </div>
</div>