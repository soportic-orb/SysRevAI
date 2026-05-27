# Changelog

All notable changes to SysRevAI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Phase 1 — application core (MVC):**
  - `bootstrap.php` with a resilient autoload path (Composer when present, plus a
    native PSR-4 fallback) and `.env` loading (phpdotenv or a native `Core\Env`).
  - `Core\Router` (regex routes, params, `guest`/`auth`/`admin` middleware,
    automatic CSRF enforcement on state-changing requests).
  - `Core\Database` (shared PDO, prefix-aware table helpers, prepared-statement
    query helpers), `Core\Session` (secure cookies, idle timeout, flash),
    `Core\Csrf`, `Core\Auth` (argon2id verification, rehash-on-login,
    session-based current user), `Core\I18n`, `Core\View`, `Core\App` kernel
    (locale resolution, security headers, optional HTTPS redirect).
  - `Models\User`, `AuthController` (login/logout) and `DashboardController`.
  - Views and layouts (app shell + minimal auth layout), login page, dashboard
    with metric cards, and 403/404 error pages; core i18n in ca/es/en.
  - The footer donation link is always present; the login screen never shows it.

### Added (foundations)
- Project foundations: directory structure, `composer.json`, documented
  `.env.example`, and `config/` bootstrap (`config.php`, `donate.php`).
- Global helpers (`env`, `e`, `config`, `setting` placeholder).
- Initial database migrations with a parametrizable table prefix:
  `001_users.sql`, `002_settings.sql`, `003_reviews.sql`, `004_review_users.sql`.
- **Web installer** (`public/install/`), fully isolated from the core and
  working without Composer — all 8 steps functional:
  - Step 0 — Welcome and installer language selection (ca/es/en).
  - Step 1 — System requirements check (PHP version, extensions, writable
    paths, PHP limits) with pass/warn/fail reporting.
  - Step 2 — Composer dependency detection, install attempt, and manual
    fallback instructions.
  - Step 3 — Database configuration with a "Test connection" action and
    optional "create database if missing".
  - Step 4 — Runs migrations in order (prefix-substituted) with a per-table
    log, plus minimal settings seed.
  - Step 5 — General settings (site name, base URL, language, timezone, HTTPS).
  - Step 6 — Administrator account with password policy (≥12 chars, mixed
    case, digit, symbol), argon2id hashing, and a strength meter.
  - Step 7 — Finalization: writes `.env` (0600), creates the owner account,
    seeds settings, and seals the installer with `config/installed.lock`.
  - CSRF protection, step-skip guard, per-step validation gates,
    `installed.lock` 403 sealing, and `storage/logs/install.log` logging.
  - Self-contained CSS (no CDN) using an Okabe-Ito accessible palette.
- Open-source project files: AGPL-3.0 `LICENSE`, `README.md` (EN/CA/ES),
  `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, GitHub issue/PR
  templates, and a CI workflow.

[Unreleased]: https://github.com/soportic-orb/sysrevai/commits/main
