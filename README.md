<h1 align="center">SysRevAI</h1>

<p align="center">
  <strong>Self-hosted systematic literature review platform, powered by AI.</strong><br>
  A Covidence-inspired alternative for researchers — open source, privacy-friendly, AI-assisted.
</p>

<p align="center">
  <a href="LICENSE"><img alt="License: AGPL-3.0" src="https://img.shields.io/badge/license-AGPL--3.0-blue.svg"></a>
  <img alt="PHP 8.2+" src="https://img.shields.io/badge/PHP-8.2%2B-777bb4.svg">
  <img alt="MySQL 8.0+" src="https://img.shields.io/badge/MySQL-8.0%2B-00758f.svg">
  <img alt="Status: in development" src="https://img.shields.io/badge/status-in%20development-orange.svg">
  <a href="https://donate.stripe.com/28EaEY6ML1FI7HH1El7wA02"><img alt="Sponsor" src="https://img.shields.io/badge/%E2%9D%A4-Sponsor-ff69b4"></a>
</p>

<p align="center">
  <a href="#english">English</a> ·
  <a href="#català">Català</a> ·
  <a href="#español">Español</a>
</p>

> ⚠️ **Early development.** SysRevAI is being built phase by phase. The web
> installer (welcome + system requirements) and the project foundations are in
> place; the application features are landing incrementally. See
> [CHANGELOG.md](CHANGELOG.md) and the roadmap below.

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

- 📥 **Import** RIS, EndNote XML, PubMed XML, CSV and BibTeX.
- 🧹 **Deduplication** — exact (DOI/PMID/normalized), fuzzy (Levenshtein/Jaro-Winkler) and AI-assisted semantic checks.
- 🔍 **Screening** title/abstract and full-text with **double-blind** reviewer support and conflict resolution.
- 👥 **Collaboration** — multiple reviewers, workload assignment, in-app notifications, comments, invitations.
- 📝 **Data extraction** with customizable templates and AI assistance.
- ⚖️ **Risk of bias** — RoB 2, ROBINS-I, Newcastle-Ottawa, JBI, with traffic-light plots.
- 🤖 **AI** summaries, screening suggestions and per-article chat (Claude).
- 🌐 **Translation** of abstracts, summaries and full PDFs (Google Translate), with caching.
- 📤 **Exports** — PRISMA 2020 flow diagram, CSV, Excel, Word, RevMan 5.
- 🛠️ **Admin panel** for all configuration (API keys stored encrypted), plus a guided **web installer**.
- 🌍 **Multilingual UI** (Catalan, Spanish, English to start) with community-editable strings.

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

### Roadmap (build phases)

| Phase | Scope | Status |
|------:|-------|:------:|
| 1 | Foundations + web installer | ✅ |
| 2 | Admin panel & settings (encrypted) | ✅ |
| 3 | Reviews & protocol (PICO, criteria) | ✅ |
| 4 | Multi-user collaboration & notifications | ✅ |
| 5 | Import & deduplication | ✅ |
| 6 | Title/abstract screening (blinded) | ✅ |
| 7 | Claude API integration | ✅ |
| 8 | Full-text, PDF viewer & article chat | ✅ |
| 9 | Data extraction | ✅ |
| 10 | Risk of bias | ✅ |
| 11 | AI summaries & translation | ✅ |
| 12 | Exports (PRISMA, Excel, Word, RevMan) | ✅ |
| 13 | Polish (global search, demo data, public About) | ✅ |

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
diagrames de flux PRISMA i exportacions. Incorpora **IA (API de Claude)** i
**traducció automàtica (Google Translate)**.

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
sesgo, diagramas de flujo PRISMA y exportaciones. Incorpora **IA (API de
Claude)** y **traducción automática (Google Translate)**.

- **Requisitos**: PHP 8.2+, MySQL 8.0+, Apache/Nginx con PHP-FPM. Funciona en un VPS modesto.
- **Instalación en 3 pasos**: sube los archivos → abre el navegador → sigue el asistente (`/install/`).
- Las claves de API se configuran **después** desde el panel de administración (se guardan **cifradas**).
- **Licencia**: AGPL-3.0. Consulta [CONTRIBUTING.md](CONTRIBUTING.md) para colaborar.

> SysRevAI es un proyecto libre mantenido por un investigador en su tiempo
> libre. Si te resulta útil, puedes [❤️ apoyarlo con una donación](https://donate.stripe.com/28EaEY6ML1FI7HH1El7wA02).
> Ninguna funcionalidad queda nunca bloqueada tras una donación.
