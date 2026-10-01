<?php

declare(strict_types=1);

/**
 * Capture live HTML fixtures from real news sites into tests/Fixtures.
 *
 * Usage:
 *   php tools/capture-fixtures.php <site-slug> <listing-url> [--article=<url>]
 *
 * Fetches the listing page, extracts the first article URL from <a href>
 * nodes (DOM-based, no CSS url() noise), fetches it, and writes
 * tests/Fixtures/<site>/listing-1.html and detail-1.html.
 */

use Symfony\Component\DomCrawler\Crawler;

$args = array_slice($argv, 1);
if (count($args) < 2 || in_array('--help', $args, true)) {
    fwrite(STDOUT, "Usage: php tools/capture-fixtures.php <site-slug> <listing-url> [--article=<url>]\n");
    exit(0);
}

$slug = $args[0];
$listingUrl = $args[1];
$articleUrl = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--article=')) {
        $articleUrl = substr($arg, strlen('--article='));
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

function fetch(string $url): string
{
    $headers = "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36\r\n"
        . "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n"
        . "Accept-Language: en-US,en;q=0.9,vi;q=0.8\r\n"
        . "Accept-Encoding: identity\r\n";
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 25,
            'ignore_errors' => true,
            'header' => $headers,
            'follow_location' => 1,
        ],
    ]);
    $html = file_get_contents($url, false, $ctx);

    if ($html === false) {
        throw new RuntimeException('Fetch failed: ' . $url);
    }

    return $html;
}

function extractArticleUrl(string $html, string $slug): ?string
{
    // Per-site article URL pattern (regex matched against <a href> only).
    $patterns = [
        'guardian' => '#^https://www\.theguardian\.com/(world|technology|business|science|uk-news|money|culture|media|environment|politics|sport|football)/\d{4}/[a-z0-9/-]+$#',
        'npr' => '#^https://www\.npr\.org/\d{4}/\d{2}/\d{2}/nx-s1-\d+/[a-z0-9-]+$#',
        'vnexpress' => '#^https://vnexpress\.net/[a-z0-9-]+-\d+\.html$#',
        'dantri' => '#^https://dantri\.com\.vn/[a-z0-9-]+-\d+\.htm$#',
        'vietnamnet' => '#^https://vietnamnet\.vn/[a-z0-9-]+\.html$#',
        'thanhnien' => '#^https://thanhnien\.vn/[a-z0-9-]+-\d+\.html$#',
        'vietnamplus' => '#^https://www\.vietnamplus\.vn/[a-z0-9-]+-\d+\.vnp$#',
        'vtcnews' => '#^https://vtcnews\.vn/[a-z0-9-]+-\d+\.html$#',
        'znews' => '#^https://znews\.vn/[a-z0-9-]+-\d+\.html$#',
        'tinhte' => '#^https://tinhte\.vn/threads/[a-z0-9-]+\.\d+/$#',
        'vnreview' => '#^https://vnreview\.vn/[a-z0-9-]+-\d+(\.html)?$#',
        'ictnews' => '#^https://ictnews\.vn/[a-z0-9/-]+\.html$#',
        'trangcongnghe' => '#^https://trangcongnghe\.com\.vn/[a-z0-9-]+-\d+\.html$#',
        'cafef' => '#^https://cafef\.vn/[a-z0-9-]+-\d+\.chn$#',
        'cafebiz' => '#^https://cafebiz\.vn/[a-z0-9-]+-\d+\.chn$#',
        'vietnamnet-cntt' => '#^https://vietnamnet\.vn/[a-z0-9-]+\.html$#',
    ];

    $pattern = $patterns[$slug] ?? null;
    $crawler = new Crawler($html);
    $hrefs = $crawler->filter('a[href]')->each(fn(Crawler $node): string => (string) $node->attr('href'));

    foreach ($hrefs as $href) {
        $href = trim($href);
        if ($pattern !== null && preg_match($pattern, $href) !== 1) {
            continue;
        }

        if (str_starts_with($href, 'http')) {
            return $href;
        }
    }

    return null;
}

$fixtureDir = dirname(__DIR__) . '/tests/Fixtures/' . $slug;
if (! is_dir($fixtureDir)) {
    mkdir($fixtureDir, 0777, true);
}

echo "Fetching listing: {$listingUrl}\n";
$listingHtml = fetch($listingUrl);
file_put_contents($fixtureDir . '/listing-1.html', $listingHtml);
echo '  saved listing-1.html (' . strlen($listingHtml) . " bytes)\n";

if ($articleUrl === null) {
    $articleUrl = extractArticleUrl($listingHtml, $slug);
}

if ($articleUrl === null) {
    echo "  Could not auto-extract article URL; pass --article=<url>.\n";
    exit(1);
}

echo "Fetching article: {$articleUrl}\n";
$articleHtml = fetch($articleUrl);
file_put_contents($fixtureDir . '/detail-1.html', $articleHtml);
echo '  saved detail-1.html (' . strlen($articleHtml) . " bytes)\n";
