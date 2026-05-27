# Changelog

All notable changes to SysRevAI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Project foundations: directory structure, `composer.json`, documented
  `.env.example`, and `config/` bootstrap (`config.php`, `donate.php`).
- Global helpers (`env`, `e`, `config`, `setting` placeholder).
- Initial database migrations with a parametrizable table prefix:
  `001_users.sql`, `002_settings.sql`, `003_reviews.sql`, `004_review_users.sql`.
- **Web installer** (`public/install/`), fully isolated from the core and
  working without Composer:
  - Step 0 — Welcome and installer language selection (ca/es/en).
  - Step 1 — System requirements check (PHP version, extensions, writable
    paths, PHP limits) with pass/warn/fail reporting.
  - Steps 2–7 scaffolded as placeholders within the wizard.
  - CSRF protection, step-skip guard, `installed.lock` 403 sealing, and
    `storage/logs/install.log` logging.
  - Self-contained CSS (no CDN) using an Okabe-Ito accessible palette.
- Open-source project files: AGPL-3.0 `LICENSE`, `README.md` (EN/CA/ES),
  `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, GitHub issue/PR
  templates, and a CI workflow.

[Unreleased]: https://github.com/soportic-orb/sysrevai/commits/main
