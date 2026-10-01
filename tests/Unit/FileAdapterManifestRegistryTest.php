<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Unit;

use JOOservices\CrawlerXNews\Registry\FileAdapterManifestRegistry;
use PHPUnit\Framework\TestCase;

final class FileAdapterManifestRegistryTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/cxn-manifest-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            if (is_dir($file)) {
                @rmdir($file);
            } else {
                @unlink($file);
            }
        }
        @rmdir($this->tmpDir);
    }

    public function testDiscoversAdaptersFromManifests(): void
    {
        mkdir($this->tmpDir . '/FakeSite');
        file_put_contents($this->tmpDir . '/FakeSite/manifest.json', json_encode([
            'slug' => 'fake',
            'hosts' => ['example.org'],
            'adapter' => ['class' => \JOOservices\CrawlerXNews\Tests\Support\FakeNewsCrawler::class],
            'page_rules' => ['article' => ['/story/'], 'listing' => ['/']],
        ], JSON_THROW_ON_ERROR));

        $registry = new FileAdapterManifestRegistry();
        $manifests = $registry->discover($this->tmpDir);

        self::assertCount(1, $manifests);
        self::assertSame('fake', $manifests[0]['slug']);
        self::assertSame(['example.org'], $manifests[0]['hosts']);
        self::assertSame(['/story/'], $manifests[0]['page_rules']['article']);
    }

    public function testSkipsManifestWithUnknownClass(): void
    {
        mkdir($this->tmpDir . '/Broken');
        file_put_contents($this->tmpDir . '/Broken/manifest.json', json_encode([
            'slug' => 'broken',
            'hosts' => ['broken.org'],
            'adapter' => ['class' => 'No\\Such\\Class'],
        ], JSON_THROW_ON_ERROR));

        $registry = new FileAdapterManifestRegistry();

        self::assertSame([], $registry->discover($this->tmpDir));
    }

    public function testSkipsInvalidJson(): void
    {
        mkdir($this->tmpDir . '/Bad');
        file_put_contents($this->tmpDir . '/Bad/manifest.json', 'not json{');

        $registry = new FileAdapterManifestRegistry();

        self::assertSame([], $registry->discover($this->tmpDir));
    }

    public function testReturnsEmptyForMissingDirectory(): void
    {
        $registry = new FileAdapterManifestRegistry();

        self::assertSame([], $registry->discover($this->tmpDir . '/does-not-exist'));
    }
}
