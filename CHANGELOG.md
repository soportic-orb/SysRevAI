# Changelog

All notable changes to SysRevAI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Phase 11 — AI summaries & translation:**
  - `014_summaries_translations.sql` migration: `ai_summaries` (one row per
    `(reference, language)`, structured JSON with background/methods/results/
    conclusions/relevance) and `translations` (cache keyed by
    SHA-256(source) + langs).
  - `Models\AiSummary` and `Models\Translation`.
  - `Services\TranslateService` upgraded to a real Google Cloud Translation v3
    client: signs an RS256 JWT from the configured service-account JSON,
    exchanges it for an OAuth2 access token (cached on disk for ~50 minutes),
    splits long text into ≤4500-character chunks at paragraph boundaries, and
    persists every chunk via the cache so repeated passages never hit the API.
  - `SummariesController`: per-reference summary page in ca/es/en with
    **"Generate with AI"** (uses extracted PDF text, falling back to the
    abstract) and **regenerate**; cached results are shown instantly.
  - `TranslateController`: generic JSON endpoint scoped to a review used by
    the **"Translate" button** on the summary page (works for the abstract and
    each summary section).
- **Phase 10 — risk of bias:**
  - `013_risk_of_bias.sql` migration: per-(reference, reviewer, tool, domain)
    judgements + justifications.
  - `Services\RiskOfBiasService`: domains and judgement vocabularies for
    **RoB 2**, **ROBINS-I**, **Newcastle-Ottawa** and **JBI** with severity
    ordering and accessible Okabe-Ito colours for the traffic-light view.
  - `Models\RiskOfBias` (upsert per domain, per-assessment fetch, review-wide
    aggregate for plots) and `RiskOfBiasController` (overview with
    **traffic-light table** and **summary stacked-bar** chart via Chart.js,
    per-reference assessment form with per-domain **"Suggest with AI"** that
    calls `assessBiasDomain` and fills judgement+justification client-side for
    review before save).
- **Phase 9 — data extraction:**
  - `012_extraction.sql` migration: `extraction_templates` (per-review,
    JSON-defined fields) and `extraction_data` (per-(reference, reviewer,
    template) submission with draft/submitted/approved status).
  - `Models\ExtractionTemplate` (typical predefined fields seeded the first
    time the page is opened) and `Models\ExtractionData` (upsert, approve,
    side-by-side data for the compare view).
  - `Services\ExtractionService`: per-field-type sanitization (text/textarea/
    number/date/select/multi-select) and a compact AI payload shape.
  - `ExtractionController`: template editor (owner) with add/remove fields,
    per-reference extraction form rendered from the template, draft/submit
    actions, **"Fill with AI"** (Claude reads the PDF text — or abstract as
    fallback — and proposes values for the form, persisted as draft for
    review), owner approval that marks the reference as `extracted`, and an
    embedded **side-by-side compare** panel showing other reviewers'
    submissions.
- **Phase 8 — full text, PDF viewer & article chat:**
  - `011_full_text.sql` migration: `reference_full_text` (FULLTEXT on
    extracted_text) and `ai_chat_history` tables.
  - `Services\FileStorage`: secure PDF upload — **real MIME via `finfo`** (never
    trust the extension), size limit from the admin Files settings, UUID-style
    filenames, stored **outside the document root** with 0600 permissions and a
    path-traversal-safe serve check.
  - `Services\PdfService`: text extraction via `smalot/pdfparser` when present,
    graceful no-op otherwise.
  - `PdfController` (upload + auth-gated streaming serve) and `ChatController`
    (per-user/per-article chat with Claude, persisted history).
  - `FullTextScreeningController extends ScreeningController` — full-text
    screening reuses every mechanic of the T/A flow (blinding, conflict queue,
    coordinator view) with `stage = 'ft'`. ScreeningController refactored to
    parametrize the stage; TA routes moved under `/reviews/{id}/screen/`
    consistently with `/full-text/`.
  - `views/full_text/screen.php`: embedded PDF iframe viewer, upload form
    when the PDF is missing, decision form with shortcuts, and a chat panel
    that streams replies via fetch into the conversation history.
- **Phase 7 — Claude API integration:**
  - Full `Services\ClaudeService`: `summarize`, `suggestScreeningDecision`,
    `extractStructuredData`, `assessBiasDomain`, `chatWithArticle` and
    `checkSemanticDuplicate`, all reading the encrypted admin settings (model,
    temperature, max tokens), forcing JSON for structured tasks, retrying with
    exponential backoff (3×), and gated by per-feature toggles and the monthly
    cost limit.
  - `010_ai_usage.sql` + `Models\AiUsage`: per-review token/cost accounting; the
    admin Claude section shows this month's estimated cost vs. the limit.
  - Wired into existing phases: an advisory **"Suggest with AI"** button on the
    screening card (returns recommendation/confidence/reason as JSON, shown in a
    panel) and a **level-3 semantic duplicate check** on fuzzy candidate pairs.
- **Phase 6 — title/abstract screening with blinding:**
  - `009_screening_decisions.sql` migration (per-reviewer decisions, resolutions,
    time spent).
  - `Services\ScreeningService`: promote imported references into screening,
    serve the next reference for a reviewer (blinded — never showing others'
    decisions), detect agreement (auto include/exclude) vs. conflict, and
    resolve conflicts; required-reviewer count derives from the screening mode.
  - `ScreeningController` + `Models\ScreeningDecision`: Tinder-style one-card
    screening (Include/Maybe/Exclude) with **keyboard shortcuts (I/E/M)**,
    exclusion reasons, notes, time tracking, and a per-reviewer progress bar.
  - Conflict queue for resolvers (owner / `can_resolve_conflicts`) with
    side-by-side decisions and a final adjudication; resolvers are notified when
    a conflict arises.
  - **Coordinator view**: an un-blinded overview of every reviewer's decisions,
    gated to owner/admin, with a warning banner, audit logging, and screening
    disabled while active.
- **Phase 5 — import & deduplication:**
  - `008_references.sql` migration: `references` (with FULLTEXT on title+abstract),
    `import_logs` and `duplicates` tables — this activates the real dashboard /
    review metrics.
  - `Services\ImportService`: robust parsers for **RIS, BibTeX, CSV, PubMed XML
    and EndNote XML** with format auto-detection and UTF-8 normalization.
  - `Services\DeduplicationService`: **level 1 (exact)** by DOI / PMID /
    normalized key (title + first author + year) — auto-marks duplicates — and
    **level 2 (fuzzy)** Jaro-Winkler on normalized titles (≥0.92, year-bucketed)
    recorded as pending candidates for manual/semantic resolution.
  - `ImportController` (file upload or paste, with import history),
    `ReferencesController` (filter by status + search + pagination) and
    `DuplicatesController` (confirm/reject candidate pairs).
  - `Models\Reference`, `Models\ImportLog`, `Models\Duplicate`; references and
    import links wired into the review workspace.
- **Phase 4 — multi-user collaboration:**
  - `007_collaboration.sql` migration: invitations, notifications, comments and
    screening_assignments (the assignments table is ready for Phase 6).
  - **Invitations**: invite collaborators by email (existing users are added and
    notified immediately; new emails get a single-use 7-day token link), with an
    accept flow at `/invite/{token}`.
  - **Team management** (`MembersController`): add/update (role, blinding,
    conflict-resolution flag)/remove members and revoke invitations, owner-only.
  - **In-app notifications** (`Models\Notification` + `NotificationsController`):
    bell with unread badge and a JS-polled dropdown (45s), full list page with
    all/unread filter and mark-read/mark-all, plus a JSON poll endpoint.
  - **Comments** (`Models\Comment` + `CommentsController`): per-review discussion
    board with `@mention` resolution against members, soft delete, and
    notifications to members (mention vs. comment).
  - **Profile → notification preferences** per type and channel (in-app/email).
- **Phase 3 — reviews & protocol:**
  - `Models\Review` (CRUD, access checks, PICO decode, status, reference
    metrics that degrade to zeros until the references table exists),
    `Models\ReviewUser` (membership), `Models\ExclusionReason`, and the
    `006_exclusion_reasons.sql` migration.
  - `ReviewsController`: list (active/archived), create, view (workspace
    dashboard), edit protocol, update, archive/unarchive — with owner/member
    access guards and audit logging.
  - Protocol editor: title, research question, **PICO/PICOS** fields,
    inclusion/exclusion criteria, screening mode (double-blind / double-blind+
    third / single / pilot), reviewers-required, pilot count, and per-review
    exclusion reasons (seeded from admin defaults).
  - Dashboard now lists the user's reviews as cards; review workspace shows
    protocol summary, metric cards, team and exclusion reasons.
- **Phase 2 — admin panel & encrypted settings:**
  - `Core\Crypto` (AES-256-GCM) for sensitive values, keyed by `APP_KEY`.
  - `Models\Setting` (typed serialize/deserialize incl. transparent encryption)
    and `Core\Config` singleton backing a real `setting()` helper (DB-cached,
    decrypts on read, degrades to defaults pre-install).
  - `Models\ActivityLog` + `005_activity_log.sql` migration; settings changes
    are audited by key name only (never the secret value).
  - Admin → Settings panel (owner/admin only) with sidebar layout and four
    complete sections: **General**, **Claude (AI)** (API key stored encrypted,
    models, temperature, max tokens, per-feature toggles, monthly cost limit,
    and a working "Verify connection" via `Services\ClaudeService`),
    **Security**, and **About** (version/license/repo + author support with a
    dashboard-mention toggle).
  - Routes guarded by `auth`+`admin` middleware; CSRF enforced on saves.
  - Built-in-server routing shim in the front controller so `php -S ... -t
    public public/index.php` serves assets, the installer and routes in dev.
  - **All 12 admin sections complete:** General, Claude, **Google Translate**
    (service-account JSON stored 0600 outside docroot, config verify),
    **Email/SMTP** (encrypted password, notification toggles, send-test via
    `Services\MailService`), **Other APIs** (PubMed/CrossRef/Unpaywall/OpenAlex/
    Semantic Scholar, keys encrypted), Security, **Users & permissions** (real
    CRUD with last-owner/self-delete guards + registration policy),
    **Reviews defaults**, **Files & storage**, **Languages** (active UI locales),
    **Maintenance** (maintenance mode, cache/log cleanup, PDO-based gzipped DB
    backups with download/delete, system info, recent activity log), and About.
  - `Services\TranslateService` (config check) and `Services\BackupService`.
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
