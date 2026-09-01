<?php
/**
 * robots.txt dinamis — nyesuain otomatis ke SITE_URL di shared/config.php.
 * Selama SITE_URL masih nunjuk ke staging.svashtahome.com, search engine
 * DILARANG nge-crawl sama sekali (biar gak ke-index & bikin konten duplikat
 * pas nanti pindah ke domain asli). Begitu SITE_URL diganti ke domain utama
 * (svashtahome.com), otomatis berubah jadi "Allow: /" + link sitemap yang bener.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

$isStaging = str_contains(SITE_URL, 'staging.');

if ($isStaging) {
    echo "User-agent: *\n";
    echo "Disallow: /\n";
} else {
    echo "User-agent: *\n";
    echo "Allow: /\n\n";
    echo "Sitemap: " . SITE_URL . "/sitemap.xml\n";
}
