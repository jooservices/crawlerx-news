# jooservices/crawlerx-news

[![CI](https://github.com/jooservices/crawlerx-news/actions/workflows/ci.yml/badge.svg?branch=develop)](https://github.com/jooservices/crawlerx-news/actions/workflows/ci.yml)
[![OpenSSF Scorecard](https://api.securityscorecards.dev/projects/github.com/jooservices/crawlerx-news/badge)](https://securityscorecards.dev/viewer/?uri=github.com/jooservices/crawlerx-news)
[![PHP Version](https://img.shields.io/badge/PHP-8.5%2B-blue.svg)](https://www.php.net/)
[![GitHub Release](https://img.shields.io/github/v/release/jooservices/crawlerx-news?display_name=tag)](https://github.com/jooservices/crawlerx-news/releases)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

> [!NOTE]
> Current release: **`0.1.0-beta.1`** — beta. See [CHANGELOG.md](CHANGELOG.md).

A PHP 8.5+ URL-driven crawl and parse library for news sites. Give CrawlerXNews
a supported URL and it detects the site and page type, fetches the page through
the appropriate HTTP strategy, and returns typed immutable DTOs with a rich
article model: title, intro, dates, full content, authors, categories, tags,
images, language, source metadata, and the raw JSON-LD.

```php
use JOOservices\CrawlerXNews\CrawlerXNews;

$article = CrawlerXNews::url('https://www.bbc.com/news/articles/c-12345')->crawl();

echo $article->title;           // Article headline
echo $article->intro;           // Lead / description
echo $article->publishedAt->format('Y-m-d');  // Publication date
echo $article->contentHtml;     // Full article body (HTML)
```

## Features

- One public builder API: `CrawlerXNews::url($url)->crawl()`
- Automatic site and page-type detection (article detail, listing / category)
- Rich article DTO: title, intro, published/updated dates, HTML + plain-text
  content, authors, categories, tags, cover image + gallery, language,
  canonical URL, source metadata, and the raw JSON-LD dump
- Parsing prioritizes structured data (JSON-LD, `og:` / `twitter:` meta) with
  HTML extraction as the fallback for the article body
- HTTP fetching through `jooservices/client` v4
- JSON API resolution for sites whose articles are AJAX-only (VietNamNet / ICTNews)
- Browser-fetch fallback chain (`http → flaresolverr → playwright`) for
  Cloudflare-protected sites (AP News), with Docker sidecars
- Typed immutable results built on `jooservices/dto` v3
- Manifest-driven capability declaration validated against adapter
  implementations
- Structured non-throwing errors
- Network-free CI tests backed by captured HTML fixtures

## Requirements

- PHP `^8.5`
- PHP extensions: `dom`, `libxml`
- Composer
- Docker with Docker Compose for the recommended development workflow

## Installation

### Current development checkout

Place this repository next to the consuming application, then register it as a
Composer path repository:

```bash
composer config repositories.crawlerx-news path ../crawlerx-news
composer require jooservices/crawlerx-news:@dev
```

The path installation uses this checkout's actual requirements, including
`jooservices/client` v4 and `jooservices/dto` v3.

For development inside this repository, build the PHP 8.5 tooling image and
install its dependencies:

```bash
make install
```

## Quick start

```php
use JOOservices\CrawlerXNews\CrawlerXNews;
use JOOservices\CrawlerXNews\Dto\ArticleDetailResultDto;

$article = CrawlerXNews::url('https://www.bbc.com/news/articles/c-12345')->crawl();

assert($article instanceof ArticleDetailResultDto);
echo $article->title;
echo $article->intro;
echo $article->publishedAt->format(DATE_ATOM);
echo $article->contentHtml;
```

### Non-throwing crawl

Use `tryCrawl()` when failures should be returned as data instead of thrown:

```php
$outcome = CrawlerXNews::url($url)->tryCrawl();

if ($outcome->failed()) {
    echo $outcome->error?->code->value;
    echo $outcome->error?->message;
}
```

Error codes include `unsupported_url`, `ambiguous_url`, `adapter_not_found`,
`blocked`, `parse_failed`, and `unknown`.

## Supported sites

CrawlerXNews currently registers 17 adapters:

| Site | Slug | Detail | Listing |
| --- | --- | :---: | :---: |
| BBC News | `bbc` | Yes | Yes |
| The Guardian | `guardian` | Yes | Yes |
| NPR | `npr` | Yes | Yes |
| AP News | `ap` | Yes | Yes |
| VnExpress | `vnexpress` | Yes | Yes |
| Dân Trí | `dantri` | Yes | Yes |
| VietNamNet | `vietnamnet` | Yes | Yes |
| ICTNews | `ictnews` | Yes | Yes |
| Thanh Niên | `thanhnien` | Yes | Yes |
| VietnamPlus | `vietnamplus` | Yes | Yes |
| VTC News | `vtcnews` | Yes | Yes |
| Znews | `znews` | Yes | Yes |
| Tinhte | `tinhte` | Yes | Yes |
| VnReview | `vnreview` | Yes | Yes |
| Trang Công Nghệ | `trangcongnghe` | Yes | Yes |
| Cafef | `cafef` | Yes | Yes |
| CafeBiz | `cafebiz` | Yes | Yes |
| GenK | `genk` | Yes | Yes |

**Fetch strategies:** most adapters use HTTP; `ap` uses a fallback chain
(`http → flaresolverr → playwright`). `vietnamnet` and `ictnews` resolve article
details through the shared VietNamNet JSON newsapi.

Live sites change independently of this package. A supported adapter means the
URL shape and parser are implemented; availability can still be affected by
site downtime, blocking, region restrictions, or DOM changes.

## Design notes

- `crawl()` returns an `ArticleDetailResultDto` (article detail) or an
  `ArticleListResultDto` (listing / category page with one entity per
  `items[]` entry).
- Article fields come from JSON-LD and `og:` / `twitter:` meta where present,
  falling back to HTML selectors defined per adapter.
- The raw JSON-LD block is preserved on the DTO so consumers never lose
  structured metadata the parser does not map.
- CrawlerXNews does not create a tracking id; correlation belongs to the
  consuming application.

## Documentation

- [Changelog](CHANGELOG.md)
- [Contributing](CONTRIBUTING.md)
- [Workflows](WORKFLOWS.md)

## Development

```bash
make build
make install
make lint
make test
make ci
```

`make lint` runs Pint, PHPCS, PHPStan, PHPMD, and PHP-CS-Fixer. `make test`
runs the deterministic PHPUnit suites. `make ci` runs lint, static analysis,
both coverage suites, and the 85% coverage checks.

### Live crawl from the host through Docker

For Cloudflare-protected sites (AP News) start the fetch sidecars first:

```bash
make fetch-up
```

Crawl any supported URL and print the complete result DTO as JSON:

```bash
docker compose run --rm php php tools/live-check.php 'https://www.bbc.com/news/articles/c0g0jy1g8jvo'
```

Stop the sidecars when finished:

```bash
make fetch-down
```

Pass builder options when explicit routing is required:

```bash
docker compose run --rm php php tools/live-check.php \
  'https://genk.vn/dien-thoai-ai-gia-re-sap-ra-mat-tai-viet-nam-a1234567.html' \
  --site=genk \
  --type=article
```

Show every CLI option:

```bash
docker compose run --rm php php tools/live-check.php --help
```

### Fixture-based tests

CI never performs live HTTP requests. Tests use `ClientBuilder::fake()` and
captured HTML under `tests/Fixtures/`. When a site's DOM changes, review and
refresh the captured fixtures before committing.

## Community

- [Contributing guide](CONTRIBUTING.md)
- [Security policy](SECURITY.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Support](SUPPORT.md)
- [Governance](GOVERNANCE.md)

## License

MIT — see [LICENSE](LICENSE).