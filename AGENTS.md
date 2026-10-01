# jooservices/crawlerx-news

This file adds project-only rules.

- PHP `>= 8.5`, runtime Composer requires: `jooservices/client` v4, `jooservices/dto` v3, `jooservices/exceptions` v4
- All PHP tooling via Docker (`php:8.5-cli-bookworm`)
- CI on GitHub-hosted `ubuntu-latest` runners
- Lints at **max** with **no ignore**: PHPStan max, full PSR-12 PHPCS, full PHPMD rulesets, Pint `per`
- URL-driven crawl and parse library for news sites — one public builder API (`CrawlerXNews::url(...)->crawl()`), auto site/page-type detection, typed immutable results, JSON-LD + HTML parsing
- Tests use `ClientBuilder::fake()` with captured HTML fixtures under `tests/Fixtures/`. No live HTTP in CI.