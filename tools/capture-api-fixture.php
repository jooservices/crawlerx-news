<?php

declare(strict_types=1);

/**
 * Capture a live JSON API response as a test fixture.
 *
 * Usage: php tools/capture-api-fixture.php <site-slug> <api-url>
 * Writes tests/Fixtures/<site>/detail-api.json
 */

$args = array_slice($argv, 1);
if (count($args) < 2 || in_array('--help', $args, true)) {
    fwrite(STDOUT, "Usage: php tools/capture-api-fixture.php <site-slug> <api-url>\n");
    exit(0);
}

[$slug, $apiUrl] = $args;

$headers = "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36\r\n"
    . "Accept: application/json\r\n"
    . "Accept-Encoding: identity\r\n";
$ctx = stream_context_create([
    'http' => [
        'timeout' => 25,
        'ignore_errors' => true,
        'header' => $headers,
        'follow_location' => 1,
    ],
]);

$body = file_get_contents($apiUrl, false, $ctx);
if ($body === false) {
    fwrite(STDERR, "Fetch failed: {$apiUrl}\n");
    exit(1);
}

$dir = dirname(__DIR__) . '/tests/Fixtures/' . $slug;
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

file_put_contents($dir . '/detail-api.json', $body);
echo 'saved ' . $dir . '/detail-api.json (' . strlen($body) . " bytes)\n";
