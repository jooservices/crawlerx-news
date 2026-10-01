<?php

declare(strict_types=1);

use JOOservices\CrawlerXNews\CrawlerXNews;
use JOOservices\CrawlerXNews\CrawlerXNewsFactory;
use JOOservices\CrawlerXNews\Dto\CrawlOptionsDto;
use JOOservices\CrawlerXNews\Enums\PageType;

require dirname(__DIR__) . '/vendor/autoload.php';

$arguments = array_slice($argv, 1);
if (in_array('--help', $arguments, true) || in_array('-h', $arguments, true)) {
    fwrite(STDOUT, <<<'HELP'
Usage:
  php tools/live-check.php URL [options]

Maps directly to CrawlerXNews::url(URL) and prints the result DTO as JSON.

Options:
  --site=SLUG       Override site detection
  --type=article|listing
  --timeout=SECONDS
  --try             Call tryCrawl() instead of crawl()

Example:
  php tools/live-check.php 'https://www.bbc.com/news/articles/c12345678'
HELP);
    exit(0);
}

$url = null;
$site = null;
$type = null;
$timeout = null;
$useTry = false;

foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--site=')) {
        $site = substr($argument, strlen('--site='));
    } elseif (str_starts_with($argument, '--type=')) {
        $value = substr($argument, strlen('--type='));
        $type = $value === 'listing' ? PageType::Listing : PageType::Article;
    } elseif (str_starts_with($argument, '--timeout=')) {
        $parsed = (int) substr($argument, strlen('--timeout='));
        $timeout = $parsed > 0 ? $parsed : null;
    } elseif ($argument === '--try') {
        $useTry = true;
    } elseif (! str_starts_with($argument, '-')) {
        if ($url !== null) {
            fwrite(STDERR, "Only one URL may be supplied.\n");
            exit(2);
        }

        $url = $argument;
    } else {
        fwrite(STDERR, "Unknown option: {$argument}\n");
        exit(2);
    }
}

if ($url === null || trim($url) === '') {
    fwrite(STDERR, "A URL is required.\n");
    exit(2);
}

CrawlerXNewsFactory::reset();

$builder = CrawlerXNews::url($url);

if ($site !== null) {
    $builder = $builder->site($site);
}

if ($type !== null) {
    $builder = $builder->type($type);
}

if ($timeout !== null) {
    $builder = $builder->options(new CrawlOptionsDto(timeout: $timeout));
}

try {
    if ($useTry) {
        $outcome = $builder->tryCrawl();

        echo json_encode($outcome->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        exit($outcome->failed() ? 1 : 0);
    }

    $result = $builder->crawl();

    echo json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . "\n");
    exit(1);
}
