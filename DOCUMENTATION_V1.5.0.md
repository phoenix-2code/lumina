# Lumina - Technical Documentation (v1.5.0)

## Overview
Lumina is a professional-grade Bible study platform built with an **Electron** frontend and a **Laravel** backend. It features a modular SQLite database architecture, sub-millisecond FTS5 search, and integrated study tools (commentaries, lexicons, and cross-references).

## Architecture

The application follows a **Full-Stack Desktop** architecture.

### File Structure
```
/modern_app
├── main.js             # Electron Entry Point (Orchestrates UI & PHP)
├── src/                # Frontend Assets (HTML, CSS, JS)
│   ├── index.html          # Application UI
│   ├── css/style.css       # Lumina Orange Theme
│   └── js/                 # Client-side Logic (State, UI, API bridge)
├── backend/            # Laravel API (PHP 8.2+)
│   ├── app/                # Core Logic
│   │   ├── Http/Controllers    # Bible, Study, and Search Logic
│   │   ├── Models              # Eloquent Models (Book, Verse, etc.)
│   │   └── Services            # Shared logic (TextService)
│   ├── config/database.php # Modular DB Configuration
│   └── routes/api.php      # API Endpoint Definitions
├── assets/data/        # Modular SQLite Databases
│   ├── core.db             # Books & KJV (Primary Index)
│   ├── versions.db         # Other Bible Translations
│   ├── commentaries.db     # All Commentary Modules
│   └── extras.db           # Lexicon, Dictionaries, Cross-references
└── php/                # Bundled PHP 8.2 runtime
```

---

## 1. Data Integrity & Alignment

Lumina uses a **Sequential ID System (1-31102)** based on the KJV structure. This fixed index serves as the "anchor" for all study tools.
*   **Alignment**: Commentary and Cross-reference entries link directly to these sequential IDs.
*   **Cross-Version**: When viewing NIV or NKJV, the API automatically maps the current verse back to its canonical KJV ID to display the correct commentary.

---

## 2. Modular Database (Split Architecture)

To optimize for download speed and performance, the database is split into four specialized files:
*   **core.db**: The essential heart of the app (Books + KJV).
*   **versions.db**: Extensible library of translations.
*   **commentaries.db**: Unified repository for all commentary text.
*   **extras.db**: Bridge data for interlinear tags and dictionary definitions.

The **DatabaseManager** in Laravel uses the `ATTACH DATABASE` command to join these files on-the-fly, allowing for complex cross-database queries while keeping the individual file sizes small.

---

## 3. Search Performance (FTS5)

Search is powered by SQLite's **FTS5 (Full-Text Search)** extension.
*   **Speed**: Search queries across 600,000+ verses typically complete in <50ms.
*   **Highlighting**: Uses the `highlight()` function to return sanitized snippets with integrated `<mark>` tags.
*   **Pagination**: Supported via `LIMIT` and `OFFSET`, accessible through the "Load More" UI button.

---

## 4. API Reference

The backend provides a modern RESTful API under the `/api` prefix.

| Endpoint | Description |
| :--- | :--- |
| `GET /api/bible/chapter` | Fetches a full chapter with dynamic commentary availability links. |
| `GET /api/study/commentary` | Retrieves commentary text for a specific verse. |
| `GET /api/search` | Performs a paginated FTS5 search across any translation. |
| `GET /api/study/xrefs` | Returns a list of cross-references for a specific verse. |
| `GET /api/study/definition` | Looks up terms in Strong's Lexicon or Bible Dictionaries. |

---

## 5. Security

*   **CSRF/Origin**: The local PHP server is locked to Electron via `HTTP_ORIGIN` checks.
*   **Sanitization**: All text rendering flows through the `TextService` sanitizer, which enforces a strict HTML whitelist.
*   **Isolation**: The Laravel backend runs as a detached background process, communicating with the frontend over an encrypted local loopback.

---

## 6. Build & Deployment

Built using **Electron Builder**.
*   **Compression**: Uses `lzma` compression to minimize installer size.
*   **Auto-Updates**: Integrated with `electron-updater` via GitHub Releases.
*   **LFS**: Large database files are managed via Git LFS to maintain repository health.
