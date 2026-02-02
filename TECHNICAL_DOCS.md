# Bible Study Pro - Technical Documentation

## Overview
Bible Study Pro is a modern, high-performance Bible study platform designed to run as a local web application or packaged desktop executable. It features a responsive "Studio" interface, instant full-text search, interlinear capabilities, and a comprehensive library of commentaries and dictionaries.

## Architecture (v2.0 - Modular)

The application follows a **Service-Oriented Architecture** on the backend and a **Component-Based** structure on the frontend.

### File Structure
```
/modern_app
├── index.html          # Application Skeleton (Entry Point)
├── api.php             # API Router & Security Gate
├── bible_app.db        # SQLite Database (The Data Lake)
├── api/                # Backend Logic
│   ├── Database.php        # Singleton DB Connection (PDO)
│   ├── Helper.php          # Static Utilities (Verse Math, Bible Structure)
│   └── TextService.php     # HTML Sanitizer & Legacy Text Formatter
├── css/                # Styles
│   └── style.css           # Premium Theme & Layout
└── js/                 # Frontend Logic
    ├── app.js              # Initialization & Startup
    ├── state.js            # Configuration & Global State
    ├── ui.js               # DOM Manipulation & Event Handlers
    ├── api.js              # Data Fetching & Rendering
    └── cache.js            # LRU Client-Side Cache
```

---

## 1. Security & Hardening

The application is built with a "Defense in Depth" strategy to allow safe execution on local machines.

*   **Input Sanitization:** All HTML rendering flows through `TextService::sanitizeHTML()`. It strictly whitelists safe tags (`<b>`, `<i>`, `<mark>`) and strips dangerous attributes (`onmouseover`, `<script>`) to prevent XSS.
*   **Origin Locking:** `api.php` enforces an Origin Check (`HTTP_ORIGIN`) to ensure it only accepts requests from `localhost`. This prevents malicious websites from scanning the user's local API.
*   **Prepared Statements:** All SQL queries use `PDO::prepare()` to eliminate SQL Injection risks.

---

## 2. Performance & Caching

### Client-Side Caching (`js/cache.js`)
To simulate a native desktop experience, the frontend implements an **LRU (Least Recently Used) Cache**.
*   **Capacity:** Stores the last 50 loaded items per category (Text, Commentary, Dictionary).
*   **Behavior:**
    1.  User clicks "Next Chapter".
    2.  `api.js` checks `Cache.get('text', 'Exodus_1_KJV')`.
    3.  **Hit:** Returns data instantly (0ms latency).
    4.  **Miss:** Fetches from API -> Renders -> Stores in Cache.

---

## 3. Data Formats

The core data is stored in `bible_app.db` (SQLite 3).

| Table | Description |
| :--- | :--- |
| `verses` | The core text. Columns: `book_id`, `chapter`, `verse`, `text`, `strongs` (interlinear). |
| `verses_fts` | FTS5 Virtual Table for sub-millisecond full-text search. |
| `commentary_*` | One table per module (e.g., `commentary_mhc`). Links via Global Verse ID. |
| `dictionaries` | Unified table for Easton, Smith, ATSD. Columns: `topic`, `definition`, `module`. |
| `lexicons` | Strong's Hebrew/Greek. Columns: `id` (H1), `transliteration`, `definition`. |

---

## 4. API Reference (`api.php`)

All endpoints return JSON and require `GET`.

1.  **`action=text`**
    *   Returns Bible text. Handles Interlinear parsing if `interlinear=true`.
2.  **`action=commentary`**
    *   Returns formatted commentary HTML. Uses `TextService::formatCommentary` to convert legacy hex links into clickable spans.
3.  **`action=search`**
    *   Performs FTS5 search. Returns sanitized results with `<mark>` highlighting.
4.  **`action=topics`**
    *   Returns a list of topics for the Sidebar. For Lexicons, returns `{id: "H1", label: "H1 - ab"}`.

---

## 5. Frontend State

The UI is driven by a single reactive state object in `js/state.js`:
```javascript
const state = {
    book: "Genesis",
    chapter: 1,
    verse: 1,
    version: "KJV",
    commModule: "mhc", // Active Commentary
    dictModule: "EASTON", // Active Dictionary
    fontSize: 18, // User Preference
    interlinear: false
};
```
Changing this state and calling `reloadPanes()` triggers a UI refresh.

---

## 6. Build Instructions

### To Run Locally
1.  Ensure PHP is installed.
2.  Run: `php -S localhost:8000` inside `/modern_app`.
3.  Open browser to `http://localhost:8000/index.html`.

### To Package (Future)
Use **Electron** or **NativePHP**.
1.  Wrap `index.html` in an Electron BrowserWindow.
2.  Spawn the PHP server process in the background.
3.  Use `electron-builder` to create `.exe`.