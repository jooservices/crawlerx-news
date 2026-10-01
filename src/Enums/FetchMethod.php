<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Enums;

enum FetchMethod: string
{
    case Http = 'http';
    case Playwright = 'playwright';
    case PlaywrightStealth = 'playwright_stealth';
    case ChromeStealth = 'chrome_stealth';
    case Flaresolverr = 'flaresolverr';

    /**
     * @return list<self>
     */
    public static function defaultChain(): array
    {
        return [
            self::Http,
            self::Playwright,
            self::PlaywrightStealth,
            self::ChromeStealth,
            self::Flaresolverr,
        ];
    }

    /**
     * @return list<self>
     */
    public static function browserChain(): array
    {
        return [
            self::Playwright,
            self::PlaywrightStealth,
            self::ChromeStealth,
            self::Flaresolverr,
        ];
    }

    /**
     * @return list<self>
     */
    public static function httpOnlyChain(): array
    {
        return [self::Http];
    }
}
