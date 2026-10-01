<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Unit;

use JOOservices\CrawlerXNews\Registry\AdapterRegistry;
use JOOservices\CrawlerXNews\Services\ClientFactory;
use JOOservices\CrawlerXNews\Tests\Support\FakeNewsCrawler;
use PHPUnit\Framework\TestCase;

final class AdapterRegistryTest extends TestCase
{
    public function testRegistersAndResolvesAdapter(): void
    {
        $registry = new AdapterRegistry(new ClientFactory());
        $adapter = new FakeNewsCrawler(new ClientFactory());

        $registry->register($adapter);

        self::assertTrue($registry->has('fake'));
        self::assertSame($adapter, $registry->get('fake'));
        self::assertSame(['fake'], $registry->slugs());
    }

    public function testGetReturnsNullForUnknownSlug(): void
    {
        $registry = new AdapterRegistry(new ClientFactory());

        self::assertNull($registry->get('nope'));
        self::assertFalse($registry->has('nope'));
    }

    public function testMakeInstantiatesAdapterFromClass(): void
    {
        $registry = new AdapterRegistry(new ClientFactory());
        $adapter = $registry->make(FakeNewsCrawler::class);

        self::assertInstanceOf(FakeNewsCrawler::class, $adapter);
        self::assertSame('fake', $adapter->name());
    }

    public function testMakeRejectsNonAdapterClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new AdapterRegistry(new ClientFactory()))->make(\stdClass::class);
    }
}
