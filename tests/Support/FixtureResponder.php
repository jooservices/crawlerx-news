<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Support;

final class FixtureResponder
{
    public static function for(string $fixturePath): string
    {
        $path = __DIR__ . '/../Fixtures/' . $fixturePath;
        $html = file_get_contents($path);

        if ($html === false) {
            throw new \RuntimeException('Fixture not found: ' . $fixturePath);
        }

        return $html;
    }
}
