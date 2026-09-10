<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 6;
$limit = max(1, min($limit, 6));

// A controlled topic pool keeps each daily card relevant to Philippine forests
// and restoration. MediaWiki supplies the current introductory extract only.
$topics = [
    ['title' => 'Reforestation', 'tone' => 'accent', 'fallback' => 'Reforestation restores tree cover in areas where forests were removed or heavily degraded.'],
    ['title' => 'Forest restoration', 'tone' => 'neutral', 'fallback' => 'Forest restoration aims to recover ecological function, biodiversity, and the benefits people receive from forests.'],
    ['title' => 'Pterocarpus indicus', 'tone' => 'accent', 'fallback' => 'Narra, Pterocarpus indicus, is a native hardwood species valued across Southeast Asia.'],
    ['title' => 'Philippine eagle', 'tone' => 'neutral', 'fallback' => 'The Philippine eagle depends on large, connected forest habitats for feeding and breeding.'],
    ['title' => 'Bamboo', 'tone' => 'accent', 'fallback' => 'Bamboo is a grass with fast-growing species that can support restoration and livelihood projects.'],
    ['title' => 'Mangrove', 'tone' => 'neutral', 'fallback' => 'Mangroves protect shorelines, provide habitat, and store carbon in coastal ecosystems.'],
    ['title' => 'Deforestation in the Philippines', 'tone' => 'accent', 'fallback' => 'Forest protection and restoration are both necessary to address habitat loss in the Philippines.'],
    ['title' => 'National Greening Program', 'tone' => 'neutral', 'fallback' => 'The National Greening Program is a Philippine government effort focused on reforestation and greening.'],
    ['title' => 'Dipterocarpus grandiflorus', 'tone' => 'accent', 'fallback' => 'Apitong, Dipterocarpus grandiflorus, is a native tree found in Philippine lowland forests.'],
    ['title' => 'Shorea', 'tone' => 'neutral', 'fallback' => 'Shorea is a genus that includes many important dipterocarp trees in Southeast Asian forests.'],
];

$today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
usort($topics, static function (array $left, array $right) use ($today): int {
    return strcmp(hash('sha256', $today . $left['title']), hash('sha256', $today . $right['title']));
});
$selectedTopics = array_slice($topics, 0, $limit);

$cacheDirectory = __DIR__ . '/../tmp/field-notes-cache';
$cacheFile = $cacheDirectory . '/field-notes-v4-' . $today . '-' . $limit . '.json';
if (is_dir($cacheDirectory)) {
    $cacheLifetime = 14 * 24 * 60 * 60;
    foreach (glob($cacheDirectory . '/field-notes-v*.json') ?: [] as $existingCacheFile) {
        if (is_file($existingCacheFile) && filemtime($existingCacheFile) < time() - $cacheLifetime) {
            unlink($existingCacheFile);
        }
    }
}
if (is_file($cacheFile)) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached) && isset($cached['notes']) && is_array($cached['notes'])) {
        echo json_encode($cached, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$titles = implode('|', array_column($selectedTopics, 'title'));
$url = 'https://en.wikipedia.org/w/api.php?' . http_build_query([
    'action' => 'query',
    'format' => 'json',
    'formatversion' => '2',
    'prop' => 'extracts',
    'exintro' => '1',
    'explaintext' => '1',
    'exsentences' => '2',
    'redirects' => '1',
    'titles' => $titles,
]);

$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_USERAGENT => 'GreenTrace Field Notes/1.0',
]);
$response = curl_exec($curl);
$statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
curl_close($curl);

$pages = [];
if (is_string($response) && $statusCode >= 200 && $statusCode < 300) {
    $decoded = json_decode($response, true);
    if (is_array($decoded['query']['pages'] ?? null)) {
        $pages = $decoded['query']['pages'];
    }
}

$notes = [];
foreach ($selectedTopics as $index => $topic) {
    $extract = '';
    foreach ($pages as $page) {
        if (strcasecmp((string) ($page['title'] ?? ''), $topic['title']) === 0) {
            $extract = trim(preg_replace('/\s+/', ' ', (string) ($page['extract'] ?? '')) ?? '');
            break;
        }
    }

    $notes[] = [
        'fact' => $extract !== '' ? $extract : $topic['fallback'],
        'tone' => $topic['tone'],
        'sourceName' => 'Wikipedia',
        'sourceUrl' => 'https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $topic['title'])),
    ];
}

$payload = ['date' => $today, 'source' => 'MediaWiki', 'notes' => $notes];
if (!is_dir($cacheDirectory)) {
    mkdir($cacheDirectory, 0775, true);
}
file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);

echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
