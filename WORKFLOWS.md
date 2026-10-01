# Workflows

This repository does not yet have GitHub Actions workflows configured. CI
baseline and branch protection are added when the repository is published to
GitHub under `jooservices/crawlerx-news`.

Until then, the local gate is the authority:

```bash
make build
make install
make lint
make test
make ci
```

`make ci` runs lint (Pint, PHPCS, PHPStan, PHPMD, PHP-CS-Fixer), the Unit and
Feature suites, and the 85% per-suite coverage enforcement.