# Security Policy

SysRevAI handles research data and integrates with third-party APIs, so we take
security seriously. Thank you for helping keep the project and its users safe.

## Supported versions

SysRevAI is in active early development. Until a stable `1.0.0` release, only
the latest `main` branch is supported with security fixes.

| Version | Supported |
|---------|:---------:|
| `main` (latest) | ✅ |
| older commits   | ❌ |

## Reporting a vulnerability

**Please do not open a public issue for security vulnerabilities.**

Instead, report privately via one of:

- **GitHub**: use *Security → Report a vulnerability* (private advisory) on the
  repository, if enabled.
- **Email**: octavirodriguezblanco@gmail.com — include "SysRevAI security" in
  the subject.

Please include, as far as possible:

- a description of the issue and its impact,
- steps to reproduce or a proof of concept,
- affected files/endpoints and any suggested remediation.

## What to expect

- **Acknowledgement** within 5 business days.
- A coordinated timeline for a fix and disclosure. We aim to patch confirmed
  high-severity issues promptly.
- Credit in the release notes if you'd like it (and you may remain anonymous).

## Scope and hardening notes

When self-hosting, please follow the security guidance baked into the platform:

- Keep `.env` out of the document root and never commit it.
- Store uploaded PDFs outside the document root (or serve them via PHP with
  permission checks) and validate real MIME types.
- API keys and SMTP passwords are stored **encrypted (AES-256-GCM)** using
  `APP_KEY`; protect that key.
- Enable HTTPS and the provided security headers (CSP, HSTS, X-Frame-Options,
  X-Content-Type-Options).
- Delete or block `public/install/` after installation (the installer locks
  itself, but removing it is best practice).

Thank you for practicing responsible disclosure. ❤️

## Legal and ethical use of the full-text retrieval module

SysRevAI's full-text retrieval module **only accesses content that is legally
available through official APIs** (Unpaywall, Europe PMC, PMC/NCBI, OpenAlex
and similar services). It does **not** bypass paywalls, scrape publisher
HTML pages, or use Sci-Hub-style services.

For articles that are not Open Access, users must rely on **their own
institutional licences** to obtain the PDF and upload it manually. SysRevAI is
not responsible for misuse by end users.
