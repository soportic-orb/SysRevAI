<h1 align="center">SysRevAI</h1>

<p align="center">
  <strong>Self-hosted systematic literature review platform, powered by AI.</strong><br>
  A Covidence-inspired alternative for researchers — open source, privacy-friendly, AI-assisted.
</p>

<p align="center">
  <a href="LICENSE"><img alt="License: AGPL-3.0" src="https://img.shields.io/badge/license-AGPL--3.0-blue.svg"></a>
  <img alt="PHP 8.2+" src="https://img.shields.io/badge/PHP-8.2%2B-777bb4.svg">
  <img alt="MySQL 8.0+" src="https://img.shields.io/badge/MySQL-8.0%2B-00758f.svg">
  <img alt="Status: active" src="https://img.shields.io/badge/status-active-brightgreen.svg">
  <a href="https://donate.stripe.com/28EaEY6ML1FI7HH1El7wA02"><img alt="Sponsor" src="https://img.shields.io/badge/%E2%9D%A4-Sponsor-ff69b4"></a>
</p>

<p align="center">
  <a href="#english">English</a> ·
  <a href="#català">Català</a> ·
  <a href="#español">Español</a>
</p>

> ⚠️ **Active development.** SysRevAI has been built phase by phase and the
> end-to-end review workflow is functional. New features land continuously —
> see [CHANGELOG.md](CHANGELOG.md) and the roadmap below for what's already
> in and what's coming next.

---

## English

### What is SysRevAI?

SysRevAI is a web platform to manage **systematic reviews of scientific
literature** end to end — importing references, deduplication, title/abstract
and full-text screening with reviewer blinding, data extraction, risk-of-bias
assessment, PRISMA flow diagrams and exports. It augments the workflow with the
**Anthropic Claude API** (summaries, screening suggestions, structured data
extraction, article chat) and **Google Cloud Translation**.

It is designed to be **self-hosted on a modest VPS** (2 GB RAM, 2 vCPU) and
installed entirely from the browser, with no command-line steps required.

### Key features

#### Reviews & collaboration
- 📋 **Protocol editor** with PICO, inclusion / exclusion criteria, screening mode and exclusion-reason lists.
- 🤖 **AI-assisted protocol drafting** — upload a PDF / Word draft and Claude pre-fills the form. Multi-protocol documents (umbrella reviews, parent + sub-studies) propose extra reviews you can create with one click.
- 👥 **Multi-user collaboration** — reviewers, workload assignment, in-app notifications, comments, email invitations.
- 🔐 **Per-review token / cost badge** in the sub-nav links to a full **AI usage breakdown** (per-call cost in EUR, model used, feature).

#### References — in, out and around
- 📥 **Import** from RIS, EndNote XML, PubMed XML, CSV, BibTeX **or free-text** (Claude parses any bibliography style, chunked so 100+ references work).
- ✅ **Preview before import** — every parsed reference shows up in a checkbox table with identifier validation (DOI / PMID detected → green, missing → red flag) before any row touches the database.
- 🔍 **Global search across databases** — fan-out queries to **CrossRef, OpenAlex and Europe PMC**, with a per-row 5-dot **relevance** indicator and a "Send to citation converter" hand-off.
- 🧹 **Deduplication** — exact (DOI / PMID / normalised), fuzzy (Jaro-Winkler) and AI-assisted semantic checks.
- 📚 **Citation normaliser** — paste any bibliography (or feed it from search / a review's references) and Claude restyles every entry to **APA, Vancouver, NLM, Chicago, MLA, Harvard, AMA or IEEE**. Bulk-import selected citations into a review.

#### Screening, full text & extraction
- 🔍 **Title / abstract screening** with **double-blind** reviewer support, conflict resolution and AI screening suggestions (Claude).
- 📄 **Full-text screening** with side-by-side PDF viewer, collapsible **per-article AI chat** (Markdown-rendered), and a "no PDF" escape hatch that records the exclusion correctly in PRISMA.
- 🌐 **Full-text retrieval** from Unpaywall, Europe PMC, CrossRef, OpenAlex, DOAJ, arXiv, bioRxiv, Semantic Scholar and PMC — open-access only, no paywall bypass.
- 🔬 **Scientific Copilot** — a floating, per-review chatbot that knows your protocol and the article you're currently looking at.
- 📝 **Structured data extraction** with customisable templates and AI assistance.
- ⚖️ **Risk of bias** — RoB 2, ROBINS-I, Newcastle-Ottawa, JBI with traffic-light plots.

#### Outputs & translation
- 📤 **Exports** — PRISMA 2020 flow diagram (SVG), CSV, Excel, Word, RevMan 5.
- 🌐 **Translation** of abstracts, summaries and full PDFs (Google Translate), with caching.

#### Platform
- 🛠️ **Web installer** + **admin panel** with encrypted (AES-256-GCM) API keys, granular feature toggles, monthly AI budget caps.
- ⚖️ **Legal documents system** — built-in Privacy Policy and Terms of Use templates with placeholders ({{ADMIN}}, {{SITE}}…) and a per-language editor. Consent is recorded on every signup (incl. the installer).
- 🌍 **Multilingual UI** (Catalan, Spanish, English) with community-editable strings.
- 🌙 **Light / dark mode**, toast notifications, info modals, busy-state buttons and an "AI is working" overlay wired into every Claude call.

### Server requirements

- **PHP 8.2+** with extensions: `pdo_mysql`, `mbstring`, `openssl`, `json`, `curl`, `fileinfo`, `zip`, `intl`, and `gd` or `imagick`.
- **MySQL 8.0+** (InnoDB, `utf8mb4`).
- **Apache or Nginx** with PHP-FPM.
- Recommended PHP limits: `memory_limit` ≥ 128 MB, `upload_max_filesize` / `post_max_size` ≥ 50 MB, `max_execution_time` ≥ 60 s.
- Outbound HTTPS (for the Claude and Google Translate APIs — only needed once you enable those integrations).

### Installation in 3 steps

1. **Upload the files** to your web server, with the document root pointing at `public/`.
2. **Open the site in your browser.** You'll be redirected to the guided installer (`/install/`).
3. **Follow the wizard:** requirements check → dependencies → database → tables → general settings → admin account → done.

No API keys are requested during installation. You configure Claude, Google
Translate and SMTP afterwards from **Admin → Settings → Integrations**, where
sensitive credentials are stored **encrypted (AES-256-GCM)** in the database.

#### Try it with Docker

```bash
docker compose up -d
# then open http://localhost:8080 and follow the installer
```

This brings up PHP-FPM, Nginx and MySQL preconfigured. The web installer works
the same inside the container.

### Post-installation

Once installed, head to the admin panel to:

- add your **Claude API key** and choose models / cost limits,
- configure **Google Translate** (project ID + service-account JSON),
- set up **SMTP** for notifications,
- manage **users, roles and security** policies.

### Try the demo data

After the installer finishes you can populate the database with a sample
review and a handful of references:

```bash
sed 's/{prefix}/sra_/g' database/seeds/demo.sql | mysql -u <user> -p <database>
```

(Replace `sra_` with your configured table prefix if you changed it.) Log in
as the admin user you created during installation and you'll see the demo
review on your dashboard.

### Background worker (full-text retrieval)

The full-text retrieval module drains its queue from a small CLI worker. Add
a cron entry on the server so it runs once a minute:

```cron
* * * * * php /path/to/sysrevai/bin/worker.php >> /path/to/sysrevai/storage/logs/worker.log 2>&1
```

A `flock` ensures only one instance runs at a time and the worker exits on its
own after ~50 seconds (so it never blocks the next tick). The worker is a
no-op when the module is disabled in **Admin → Full-text (APIs)**.

For everything else (sources, configuration, legal scope, where files live),
see [`docs/full-text-retrieval.md`](docs/full-text-retrieval.md).

### Roadmap (build phases)

| Phase | Scope | Status |
|------:|-------|:------:|
| 1  | Foundations + web installer | ✅ |
| 2  | Admin panel & settings (encrypted) | ✅ |
| 3  | Reviews & protocol (PICO, criteria) | ✅ |
| 4  | Multi-user collaboration & notifications | ✅ |
| 5  | Import & deduplication | ✅ |
| 6  | Title/abstract screening (blinded) | ✅ |
| 7  | Claude API integration | ✅ |
| 8  | Full-text, PDF viewer & article chat | ✅ |
| 9  | Data extraction | ✅ |
| 10 | Risk of bias | ✅ |
| 11 | AI summaries & translation | ✅ |
| 12 | Exports (PRISMA, Excel, Word, RevMan) | ✅ |
| 13 | Polish (global search, demo data, public About) | ✅ |
| 14 | Scientific Copilot + per-article AI chat | ✅ |
| 15 | Legal docs system + consent audit | ✅ |
| 16 | External bibliographic search (CrossRef / OpenAlex / Europe PMC) + relevance ranking | ✅ |
| 17 | Citation normaliser (APA / Vancouver / NLM / Chicago / MLA / Harvard / AMA / IEEE) | ✅ |
| 18 | AI-assisted protocol drafting + sub-study detection | ✅ |
| 19 | AI usage page + per-review token / EUR cost badge | ✅ |
| 20 | Import preview with per-row checkboxes + identifier validation | ✅ |

### Contributing

Contributions are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md) and our
[CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md). Translations are especially welcome.

### License

SysRevAI is released under the **GNU AGPL-3.0** license — see [LICENSE](LICENSE).
This ensures that improvements deployed as a network service remain available to
the community.

### Support the project

SysRevAI is a free, open-source project maintained by a researcher in their
spare time. If it's useful in your work, you can support its development with a
voluntary donation:

➡️ **[❤️ Support SysRevAI](https://donate.stripe.com/28EaEY6ML1FI7HH1El7wA02)**

No feature is ever gated behind donations, and you'll never see a pop-up asking
for one.

---

## Català

**SysRevAI** és una plataforma web self-hosted per gestionar **revisions
sistemàtiques de literatura científica** de principi a fi: importació de
referències, deduplicació, cribratge de títol/resum i text complet amb
cegament entre revisors, extracció de dades, avaluació del risc de biaix,
diagrames de flux PRISMA i exportacions.

Incorpora **IA (API de Claude)** a totes les fases — esborrany del protocol
a partir d'un PDF (amb detecció de subestudis), suggeriments de cribratge,
resums, xat per article, Copilot científic per revisió i mètriques d'ús de
tokens i cost en €. La **cerca global** consulta CrossRef, OpenAlex i Europe
PMC amb un indicador de rellevància de 5 punts, i el **normalitzador de
citacions** reformata qualsevol bibliografia a APA, Vancouver, NLM, Chicago,
MLA, Harvard, AMA o IEEE. **Traducció automàtica** d'abstracts i PDFs
amb Google Translate.

- **Requisits**: PHP 8.2+, MySQL 8.0+, Apache/Nginx amb PHP-FPM. Funciona en un VPS modest.
- **Instal·lació en 3 passos**: puja els fitxers → obre el navegador → segueix l'assistent (`/install/`).
- Les claus d'API es configuren **després** des del panell d'administració (es desen **xifrades**).
- **Llicència**: AGPL-3.0. Vegeu [CONTRIBUTING.md](CONTRIBUTING.md) per col·laborar.

> SysRevAI és un projecte lliure mantingut per un investigador en el seu temps
> lliure. Si et resulta útil, pots [❤️ donar-li suport](https://donate.stripe.com/28EaEY6ML1FI7HH1El7wA02).
> Cap funcionalitat queda mai bloquejada darrere d'una donació.

---

## Español

**SysRevAI** es una plataforma web self-hosted para gestionar **revisiones
sistemáticas de literatura científica** de principio a fin: importación de
referencias, deduplicación, cribado de título/resumen y texto completo con
cegamiento entre revisores, extracción de datos, evaluación del riesgo de
sesgo, diagramas de flujo PRISMA y exportaciones.

Incorpora **IA (API de Claude)** en todas las fases — borrador del protocolo
a partir de un PDF (con detección de subestudios), sugerencias de cribado,
resúmenes, chat por artículo, Copilot científico por revisión y métricas de
uso de tokens y coste en €. La **búsqueda global** consulta CrossRef,
OpenAlex y Europe PMC con un indicador de relevancia de 5 puntos, y el
**normalizador de citas** reformatea cualquier bibliografía a APA,
Vancouver, NLM, Chicago, MLA, Harvard, AMA o IEEE. **Traducción automática**
de abstracts y PDFs con Google Translate.

- **Requisitos**: PHP 8.2+, MySQL 8.0+, Apache/Nginx con PHP-FPM. Funciona en un VPS modesto.
- **Instalación en 3 pasos**: sube los archivos → abre el navegador → sigue el asistente (`/install/`).
- Las claves de API se configuran **después** desde el panel de administración (se guardan **cifradas**).
- **Licencia**: AGPL-3.0. Consulta [CONTRIBUTING.md](CONTRIBUTING.md) para colaborar.

> SysRevAI es un proyecto libre mantenido por un investigador en su tiempo
> libre. Si te resulta útil, puedes [❤️ apoyarlo con una donación](https://donate.stripe.com/28EaEY6ML1FI7HH1El7wA02).
> Ninguna funcionalidad queda nunca bloqueada tras una donación.
