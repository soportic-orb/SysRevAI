# Contributing to SysRevAI

Thank you for considering a contribution! SysRevAI is built for the research
community, and improvements — code, documentation, translations, bug reports —
are all welcome.

## Code of Conduct

By participating you agree to uphold our
[Code of Conduct](CODE_OF_CONDUCT.md).

## Ways to contribute

- 🐛 **Report bugs** using the bug report issue template.
- 💡 **Suggest features** using the feature request template.
- 🌍 **Add or improve translations** (see below).
- 🧑‍💻 **Submit code** via pull requests.

## Development setup

Requirements: PHP 8.2+, Composer, MySQL 8.0+ (or use Docker).

```bash
git clone https://github.com/soportic-orb/sysrevai.git
cd sysrevai
composer install

# Option A — Docker (recommended)
docker compose up -d        # then open http://localhost:8080

# Option B — built-in PHP server (front-controller routing)
php -S 127.0.0.1:8080 -t public public/index.php
```

On first access you'll be redirected to the web installer at `/install/`.

## Coding standards

- **PHP**: PSR-12, `declare(strict_types=1)` in every file, typed properties
  and signatures. Public methods carry docblocks.
- **Naming**: code identifiers in **English**. User-facing strings live in the
  i18n files (`*/lang/*.php`), never hard-coded.
- **Security first**: PDO prepared statements everywhere, CSRF tokens on every
  form, escape all output, validate at boundaries. Never commit secrets.
- **Keep it lean**: no heavy frameworks or ORMs. Justify every new dependency.

Run the linter before pushing:

```bash
composer lint        # check
composer lint:fix    # auto-fix
composer test        # PHPUnit
```

## Pull requests

1. Fork and create a branch: `git checkout -b feature/short-description`.
2. Keep PRs focused; reference the related issue.
3. Update `CHANGELOG.md` under the "Unreleased" section.
4. Ensure lint and tests pass.
5. Fill in the PR template.

## Adding translations

UI strings are plain PHP files returning arrays, one per locale (e.g.
`public/install/lang/es.php` for the installer; core i18n lands in a later
phase). To add a language:

1. Copy the English file to your locale code (e.g. `fr.php`).
2. Translate the values (keep the keys and `%`-placeholders intact).
3. Open a PR. Native-speaker review is appreciated.

Once the admin panel ships, translations can also be edited from the UI and
exported as JSON/PO to collaborate via tools like Weblate or Crowdin.

## Commit messages

Use clear, present-tense messages (e.g. `add RIS parser`, `fix CSRF check in
installer`). Conventional Commits are welcome but not required.

## Questions

Open a "Question" issue or start a discussion. Thanks for helping! ❤️
