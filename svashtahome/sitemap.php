<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/db.php';

header('Content-Type: application/xml; charset=utf-8');

$urls = [
    ['loc' => SITE_URL . '/', 'priority' => '1.0'],
    ['loc' => SITE_URL . '/product', 'priority' => '0.9'],
    ['loc' => SITE_URL . '/blog', 'priority' => '0.7'],
    ['loc' => SITE_URL . '/projects', 'priority' => '0.7'],
    ['loc' => SITE_URL . '/consultation', 'priority' => '0.6'],
];

try {
    $pdo = db();

    $products = $pdo->query('SELECT slug FROM products')->fetchAll();
    foreach ($products as $p) {
        $urls[] = ['loc' => SITE_URL . '/product/' . urlencode($p['slug']), 'priority' => '0.8'];
    }

    $posts = $pdo->query('SELECT slug FROM blog_posts')->fetchAll();
    foreach ($posts as $p) {
        $urls[] = ['loc' => SITE_URL . '/blog/' . urlencode($p['slug']), 'priority' => '0.6'];
    }

    $projects = $pdo->query('SELECT slug FROM projects')->fetchAll();
    foreach ($projects as $p) {
        $urls[] = ['loc' => SITE_URL . '/projects/' . urlencode($p['slug']), 'priority' => '0.6'];
    }
} catch (Throwable $e) {
    // DB belum siap — sitemap tetap terbit dengan URL statis di atas
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n    <loc>" . htmlspecialchars($u['loc']) . "</loc>\n    <priority>{$u['priority']}</priority>\n  </url>\n";
}
echo '</urlset>' . "\n";
