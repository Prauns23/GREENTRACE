<?php

/**
 * Get paginated data and total count
 * 
 * @param mysqli $conn Database connection
 * @param string $sql The base SELECT query (without LIMIT)
 * @param array $params Parameters for prepared statement (optional)
 * @param string $types Parameter types (optional)
 * @param int $limit Items per page
 * @param int $page Current page number
 * @return array ['data' => rows, 'total' => total_count, 'totalPages' => number_of_pages]
 */
function getPaginatedData($conn, $sql, $params = [], $types = '', $limit = 15, $page = 1)
{
    // Ensure page is at least 1
    $page = max(1, (int)$page);
    $offset = ($page - 1) * $limit;

    // Add LIMIT clause
    $sqlWithLimit = $sql . " LIMIT $offset, $limit";

    // Execute main query
    $stmt = $conn->prepare($sqlWithLimit);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get total count (remove ORDER BY and LIMIT for count)
    $countSql = preg_replace('/ORDER BY .*$/i', '', $sql);
    $countSql = preg_replace('/LIMIT .*$/i', '', $countSql);
    $countSql = "SELECT COUNT(*) as total FROM (" . $countSql . ") as count_subquery";

    $stmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $totalPages = ceil($total / $limit);

    return [
        'data' => $data,
        'total' => $total,
        'totalPages' => $totalPages,
        'currentPage' => $page,
        'limit' => $limit
    ];
}

/**
 * Render pagination HTML
 * 
 * @param int $currentPage
 * @param int $totalPages
 * @param string $baseUrl The base URL (without page parameter)
 * @param array $queryParams Additional query parameters (e.g., search, sort)
 * @param int $maxLinks Maximum number of page links to show (default 5)
 * @return string HTML for pagination bar
 */
function renderPagination($currentPage, $totalPages, $baseUrl, $queryParams = [], $maxLinks = 5)
{
    if ($totalPages <= 1) return '';

    $queryString = http_build_query(array_merge($queryParams, ['page' => 'PAGE_PLACEHOLDER']));
    $linkTemplate = $baseUrl . '?' . str_replace('PAGE_PLACEHOLDER', '%d', $queryString);

    $html = '<div class="pagination"><ul>';

    // Previous button
    if ($currentPage > 1) {
        $html .= sprintf('<li><a href="%s"><span class="material-symbols-rounded">arrow_back_ios</span></a></li>', sprintf($linkTemplate, $currentPage - 1));
    } else {
        $html .= '<li class="disabled"><span class="material-symbols-rounded">arrow_back_ios</span></li>';
    }

    // Page numbers
    $start = max(1, $currentPage - floor($maxLinks / 2));
    $end = min($totalPages, $start + $maxLinks - 1);
    if ($end - $start < $maxLinks - 1) {
        $start = max(1, $end - $maxLinks + 1);
    }

    if ($start > 1) {
        $html .= sprintf('<li><a href="%s">1</a></li>', sprintf($linkTemplate, 1));
        if ($start > 2) $html .= '<li class="dots"><span>…</span></li>';
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $html .= sprintf('<li class="active"><span>%d</span></li>', $i);
        } else {
            $html .= sprintf('<li><a href="%s">%d</a></li>', sprintf($linkTemplate, $i), $i);
        }
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<li class="dots"><span>…</span></li>';
        $html .= sprintf('<li><a href="%s">%d</a></li>', sprintf($linkTemplate, $totalPages), $totalPages);
    }

    // Next button
    if ($currentPage < $totalPages) {
        $html .= sprintf('<li><a href="%s"><span class="material-symbols-rounded">arrow_forward_ios</span></a></li>', sprintf($linkTemplate, $currentPage + 1));
    } else {
        $html .= '<li class="disabled"><span class="material-symbols-rounded">arrow_forward_ios</span></li>';
    }

    $html .= '</ul></div>';
    return $html;
}