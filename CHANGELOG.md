# Changelog

All notable changes to this package are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- URL-driven crawl and parse library for news sites (`CrawlerXNews::url(...)->crawl()`)
- Automatic site and page-type detection (article detail, listing) from the URL
- Rich article DTO: title, intro, HTML + plain-text content, published/updated/retrieved dates, authors, categories, tags, image, gallery, language, canonical URL, source, raw JSON-LD dump
- Field-priority parsing chain: JSON-LD → Open Graph / meta → HTML selectors
- `jooservices/client` v4 HTTP fetching with prefetched-HTML offline mode for tests
- Manifest-driven adapter discovery under `src/Adapters/*/manifest.json`
- Adapters: BBC (`bbc`), GenK (`genk`), The Guardian (`guardian`), NPR (`npr`), VnExpress (`vnexpress`), Dân Trí (`dantri`), VietNamNet (`vietnamnet`), ICTNews (`ictnews`), Thanh Niên (`thanhnien`), VietnamPlus (`vietnamplus`), VTC News (`vtcnews`), Znews (`znews`), Tinhte (`tinhte`), VnReview (`vnreview`), Trang Công Nghệ (`trangcongnghe`), Cafef (`cafef`), CafeBiz (`cafebiz`), AP News (`ap`) — detail + listing
- Live-captured HTML fixtures under `tests/Fixtures/<site>/` for every adapter
- JSON-LD parsing extended for `DiscussionForumPosting` and `mainEntity` (forum adapters)
- JSON API article resolution (`AbstractApiCrawler`) for VietNamNet and ICTNews via the shared `vietnamnet.vn/newsapi`
- Browser-fetch fallback chain (`http → flaresolverr → playwright`) with Docker sidecars for Cloudflare-protected sites; AP News fetched through FlareSolverr
- Non-throwing `tryCrawl()` with structured error codes
- Docker tooling (`php:8.5-cli-bookworm`), CaptainHook hooks, 85% coverage gate
- Live-check CLI (`tools/live-check.php`) for one-off crawls against live URLs
- Fixture capture tools (`tools/capture-fixtures.php`, `tools/capture-api-fixture.php`)

## Not supported

- Reuters (`reuters.com`) — blocked by DataDome captcha, not resolvable through HTTP or FlareSolverr