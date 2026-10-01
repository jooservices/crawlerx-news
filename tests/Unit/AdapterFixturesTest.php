<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Feature;

use JOOservices\CrawlerXNews\Adapters\AbstractNewsCrawler;
use JOOservices\CrawlerXNews\Dto\CrawlRequestDto;
use JOOservices\CrawlerXNews\Enums\PageType;
use JOOservices\CrawlerXNews\Services\ClientFactory;
use JOOservices\CrawlerXNews\Tests\Support\FixtureResponder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Generic fixture-driven adapter tests. Each adapter must parse a live
 * captured article detail and expose listing items from the captured listing.
 */
final class AdapterFixturesTest extends TestCase
{
    /**
 * @return iterable<string, array{string, string, string, string, ?string}>
 */
    public static function adapterProvider(): iterable
    {
        $cases = [
            'guardian' => ['guardian', 'https://www.theguardian.com/politics/2026/oct/01/marie-france-van-heel-very-different-type-prime-minister-wife', 'https://www.theguardian.com/international', 'Marie-France van Heel', null],
            'npr' => ['npr', 'https://www.npr.org/2026/10/01/nx-s1-5983697/project-suncatcher-google-ai-data-center-space', 'https://www.npr.org/', 'Project Suncatcher', null],
            'vnexpress' => ['vnexpress', 'https://vnexpress.net/nhung-phut-cuoi-hon-don-o-tran-thang-cua-indonesia-5127375.html', 'https://vnexpress.net/', 'Indonesia', null],
            'dantri' => ['dantri', 'https://dantri.com.vn/cong-nghe/huawei-ra-mat-mate-90-pro-max-ho-tro-ong-kinh-roi-nhu-may-anh-chuyen-nghiep-20261001172513622.htm', 'https://dantri.com.vn/', 'Huawei', null],
            'vietnamnet' => ['vietnamnet', 'https://vietnamnet.vn/camera-roi-cua-mate-90-pro-max-gay-soc-zoom-quang-10x-smartphone-hoa-may-anh-2560600.html', 'https://vietnamnet.vn/', 'Camera rời', 'https://vietnamnet.vn/newsapi/NewsDetail/Get?id=2560600'],
            'ictnews' => ['ictnews', 'https://ictnews.vn/cuc-truong-le-quang-tu-do-noi-2-cach-xu-ly-video-ngan-nham-nhi-gay-nghien-2560717.html', 'https://ictnews.vn/', 'Lê Quang Tự Do', 'https://vietnamnet.vn/newsapi/NewsDetail/Get?id=2560717'],
            'thanhnien' => ['thanhnien', 'https://thanhnien.vn/tphcm-trang-bi-xe-chua-chay-mini-cho-5-phuong-nhieu-hem-nho-185261001212113182.htm', 'https://thanhnien.vn/', 'PCCC', null],
            'vietnamplus' => ['vietnamplus', 'https://en.vietnamplus.vn/227-defendants-stand-trial-in-flight-attendants-related-drug-trafficking-case-post350179.vnp', 'https://en.vietnamplus.vn/society.vnp', '227 defendants', null],
            'vtcnews' => ['vtcnews', 'https://vtcnews.vn/vietjet-mo-ban-3-1-trieu-ve-tet-dinh-mui-them-lua-chon-cho-hanh-trinh-doan-vien-ar1036231.html', 'https://vtcnews.vn/tin-moi-hom-nay.html', 'Vietjet', null],
            'znews' => ['znews', 'https://znews.vn/nvidia-ban-card-do-hoa-yeu-o-trung-quoc-post1523739.html', 'https://znews.vn/cong-nghe.html', 'Nvidia', null],
            'tinhte' => ['tinhte', 'https://tinhte.vn/thread/hcm-thu-7-3-10-moi-tham-du-offline-trai-nghiem-tai-nghe-jbl-live-beam-4-trung-qua-tai-nghe-jbl.4180069/', 'https://tinhte.vn/', 'JBL', null],
            'vnreview' => ['vnreview', 'https://vnreview.vn/threads/danh-gia-chi-tiet-joyoung-jscb-k7-pro-chiec-may-nau-sua-hat-cho-nguoi-ban-ron-yeu-suc-khoe-va-ghet-don-dep.70900/', 'https://vnreview.vn/', 'Joyoung', null],
            'trangcongnghe' => ['trangcongnghe', 'https://trangcongnghe.com.vn/kien-thuc/7142-gemini-4-argon-google-mo-rong-gioi-han-token-len-1-trieu.html', 'https://trangcongnghe.com.vn/', 'Gemini', null],
            'cafef' => ['cafef', 'https://cafef.vn/mot-du-lieu-cho-thay-su-kien-cuong-cua-nha-dau-tu-chung-khoan-trong-nuoc-188261001232001596.chn', 'https://cafef.vn/', 'nhà đầu tư', null],
            'cafebiz' => ['cafebiz', 'https://cafebiz.vn/hoa-phat-cua-ty-phu-tran-dinh-long-rot-gan-8000-ty-dong-vao-mot-doanh-nghiep-cua-dai-gia-khoa-khan-176261001170125382.chn', 'https://cafebiz.vn/', 'Hòa Phát', null],
            'ap' => ['ap', 'https://apnews.com/article/cornell-rape-allegations-investigation-7073e8da8b027fe9ee4530ea8a549f64', 'https://apnews.com/hub/world-news', 'Cornell', null],
        ];

        foreach ($cases as $name => $case) {
            yield $name => $case;
        }
    }

    #[DataProvider('adapterProvider')]
    public function testArticleDetail(string $slug, string $detailUrl, string $listingUrl, string $expectedFragment, ?string $apiUrl): void
    {
        $adapter = $this->adapterFor($slug);
        $fixture = $apiUrl !== null ? $slug . '/detail-api.json' : $slug . '/detail-1.html';
        $request = new CrawlRequestDto(
            url: $detailUrl,
            type: PageType::Article,
            site: $slug,
            options: ['prefetched_html' => [($apiUrl ?? $detailUrl) => FixtureResponder::for($fixture)]],
        );

        $adapter->prepareRequest($request);
        $result = $adapter->detail($request);

        self::assertNotSame('', $result->title, $slug . ' title should not be empty');
        self::assertStringContainsString($expectedFragment, $result->title, $slug . ' title should contain expected fragment');
        self::assertNotNull($result->retrievedAt);
    }

    #[DataProvider('adapterProvider')]
    public function testListingItems(string $slug, string $detailUrl, string $listingUrl, string $expectedFragment, ?string $apiUrl): void
    {
        $adapter = $this->adapterFor($slug);
        $request = new CrawlRequestDto(
            url: $listingUrl,
            type: PageType::Listing,
            site: $slug,
            options: ['prefetched_html' => [$listingUrl => FixtureResponder::for($slug . '/listing-1.html')]],
        );

        $adapter->prepareRequest($request);
        $result = $adapter->listing($request);

        self::assertIsArray($result->items, $slug . ' listing items');
        self::assertNotEmpty($result->items, $slug . ' listing should expose items');
    }

    private function adapterFor(string $slug): AbstractNewsCrawler
    {
        $class = sprintf('JOOservices\\CrawlerXNews\\Adapters\\%s\\%sCrawler', ucfirst($slug), ucfirst($slug));
        $adapter = new $class(new ClientFactory());

        self::assertInstanceOf(AbstractNewsCrawler::class, $adapter);

        return $adapter;
    }
}
