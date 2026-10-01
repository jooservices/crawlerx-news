# Security Policy

## Supported versions

| Version line | Status |
| --- | --- |
| `0.x` (this repository) | Current supported — receives security fixes |

## Reporting a vulnerability

**Do not open public GitHub issues for suspected vulnerabilities.**

Preferred: GitHub [private vulnerability reporting](https://github.com/jooservices/crawlerx-news/security/advisories/new) (Security Advisories).

Alternatively, email [admin@jooservices.com](mailto:admin@jooservices.com) with:

- a clear summary of the issue
- affected package version(s) / commit
- impact and expected risk
- reproduction details or proof of concept when available

If you are unsure whether something is security-related, contact us privately first rather than opening a public issue.

## What happens next

1. We acknowledge the report as soon as possible.
2. We investigate and validate, keeping you informed of progress.
3. Fixes land in the supported line with a coordinated disclosure; you are credited unless you prefer otherwise.

No guaranteed SLA is offered; handling time depends on severity, exploitability, and release risk.

## Scope

This policy covers repository-managed behavior, including:

- URL detection and page-type detection
- article parsing (JSON-LD, HTML extraction)
- fetch strategy and error handling
- exception payloads and payload redaction
- dependency and CI configuration that affects package consumers or repository integrity

Automated scanning runs on every change and on schedule:

- Composer audit and OSV Scanner (dependency vulnerabilities)
- Gitleaks (secrets)
- Semgrep OSS (PHP SAST)
- CodeQL (GitHub Actions workflow analysis)
- Dependency Review (on pull requests)
- Trivy filesystem and container image scans (on release tags)
- OpenSSF Scorecard and zizmor (workflow supply-chain audit)
- SBOM generation on release tags

## Non-security issues

Bugs, feature requests, questions, and documentation improvements belong in [GitHub Issues](https://github.com/jooservices/crawlerx-news/issues).