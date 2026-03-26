<?php
require_once 'init_session.php';
include 'header.php';
require_once 'config.php';

// Get filters from URL
$category = $_GET['category'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Build query
$sql = "SELECT * FROM tree_species WHERE 1=1";
$params = [];
$types = "";

if ($category !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}
if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR scientific_name LIKE ? OR description LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

$sql .= " ORDER BY name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$species = $result->fetch_all(MYSQLI_ASSOC);
?>

<link rel="stylesheet" href="information.css">

<body>
    <div class="information-page">
        <div class="species-header">
            <h1>Tree Species</h1>
            <p>Explore the tree species used in our reforestation efforts, including their characteristics, ecological benefits, and planting requirements.</p>
        </div>

        <div class="filters">
            <div class="search-bar">
                <form method="get" action="" id="searchForm">
                    <input type="text" name="search" id="searchInput" placeholder="Search species" value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                </form>
            </div>
            <div class="category-buttons">
                <a href="?category=all<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="category-btn <?php echo $category === 'all' ? 'active' : ''; ?>">All</a>
                <a href="?category=native<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="category-btn <?php echo $category === 'native' ? 'active' : ''; ?>">Native</a>
                <a href="?category=introduced<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="category-btn <?php echo $category === 'introduced' ? 'active' : ''; ?>">Introduced</a>
            </div>
        </div>

        <div class="species-grid">
            <?php if (count($species) === 0): ?>
                <div class="no-results">
                    <img src="pages\no-results.svg" alt="" class="no-result-img">
                    <h3>No species found</h3>
                    <p>Your search "<?php echo htmlspecialchars($search); ?>" did not match any tree species.</p>
                </div>
            <?php else: ?>
                <?php foreach ($species as $item): ?>
                    <div class="species-card">
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                        <?php else: ?>
                            <div class="card-image-placeholder"></div>
                        <?php endif; ?>
                        <div class="card-content">
                            <div class="card-header">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <span class="category-badge <?php echo $item['category']; ?>">
                                    <?php echo ucfirst($item['category']); ?>
                                </span>
                            </div>
                            <p class="scientific"><?php echo htmlspecialchars($item['scientific_name']); ?></p>
                            <p class="description"><?php echo htmlspecialchars($item['description']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchForm = document.getElementById('searchForm');
            let debounceTimer;

            if (searchInput && searchForm) {
                searchInput.addEventListener('keyup', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        searchForm.submit();
                    }, 400);
                });
            }
        });
    </script>

    <?php include 'footer.php'; ?>
</body>