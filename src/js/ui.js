// --- DOM Manipulation & Event Handlers ---

// Theme & Font
function updateThemeUI(theme) {
    const btn = document.querySelector('#tab-settings button');
    if (btn) btn.innerText = (theme === 'dark') ? 'Switch to Light Mode' : 'Switch to Dark Mode';
}

function toggleTheme() {
    const body = document.body;
    const current = body.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    body.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
    updateThemeUI(next);
}

function changeFontSize(delta) {
    state.fontSize += delta;
    if(state.fontSize < 12) state.fontSize = 12;
    if(state.fontSize > 32) state.fontSize = 32;
    
    applyFontSize();
    localStorage.setItem('fontSize', state.fontSize);
}

function applyFontSize() {
    document.querySelectorAll('.pane-content').forEach(el => {
        el.style.fontSize = `${state.fontSize}px`;
    });
    const lbl = document.getElementById('lbl-font-size');
    if(lbl) lbl.innerText = `${state.fontSize}px`;
}

// Ribbon & Layout
function switchTab(tabId) {
    const ribbon = document.getElementById('ribbon');
    const clickedTab = event.currentTarget;
    const currentActive = document.querySelector('.ribbon-tab.active');
    
    if (clickedTab === currentActive) {
        ribbon.classList.toggle('collapsed');
        return;
    }
    
    ribbon.classList.remove('collapsed');
    document.querySelectorAll('.ribbon-content').forEach(el => el.style.display = 'none');
    document.getElementById(tabId).style.display = 'flex';
    
    document.querySelectorAll('.ribbon-tab').forEach(el => el.classList.remove('active'));
    clickedTab.classList.add('active');
    
    // Auto-switch Pane 2
    if (tabId === 'tab-comm') changePaneContent('pane-2', 'commentary');
    if (tabId === 'tab-dict') changePaneContent('pane-2', 'dictionary');
    if (tabId === 'tab-lex') changePaneContent('pane-2', 'lex_browse');
    if (tabId === 'tab-search') {
        changePaneContent('pane-2', 'search');
        setTimeout(() => {
            const input = document.getElementById('inp-bible-search');
            if(input) input.focus();
        }, 50);
    }
}

function toggleRibbon() {
    document.getElementById('ribbon').classList.toggle('collapsed');
}

function setLayout(type) {
    const ws = document.getElementById('workspace');
    ws.className = (type === 'vertical') ? 'layout-vertical' : 'layout-horizontal';
    state.layout.orientation = type;
    saveLayoutState();
}

function toggleMin(id) { 
    document.getElementById(id).classList.toggle('minimized'); 
    state.layout.panes[id].minimized = document.getElementById(id).classList.contains('minimized');
    saveLayoutState();
}
function toggleMax(id) { 
    document.getElementById(id).classList.toggle('maximized'); 
    state.layout.panes[id].maximized = document.getElementById(id).classList.contains('maximized');
    saveLayoutState();
}
function closePane(id) { 
    document.getElementById(id).classList.add('closed'); 
    state.layout.panes[id].closed = true;
    saveLayoutState();
}
function resetLayout() { 
    document.querySelectorAll('.pane').forEach(el => {
        el.classList.remove('closed', 'minimized', 'maximized');
        state.layout.panes[el.id] = { type: el.dataset.type, minimized: false, maximized: false, closed: false };
    });
    saveLayoutState();
}

function saveLayoutState() {
    localStorage.setItem('lumina_layout', JSON.stringify(state.layout));
    localStorage.setItem('lumina_nav', JSON.stringify({
        book: state.book,
        chapter: state.chapter,
        verse: state.verse,
        version: state.version
    }));
}

// History
function goBack() {
    if (state.historyIndex > 0) {
        state.historyIndex--;
        const h = state.history[state.historyIndex];
        jumpTo(h.book, h.chapter, h.verse, false); // isNew = false
    }
}

function goForward() {
    if (state.historyIndex < state.history.length - 1) {
        state.historyIndex++;
        const h = state.history[state.historyIndex];
        jumpTo(h.book, h.chapter, h.verse, false);
    }
}

function updateHistoryButtons() {
    const btnBack = document.getElementById('btn-nav-back');
    const btnFwd = document.getElementById('btn-nav-fwd');
    if (btnBack) {
        btnBack.disabled = (state.historyIndex <= 0);
        btnBack.style.opacity = (state.historyIndex <= 0) ? 0.3 : 1;
    }
    if (btnFwd) {
        btnFwd.disabled = (state.historyIndex >= state.history.length - 1);
        btnFwd.style.opacity = (state.historyIndex >= state.history.length - 1) ? 0.3 : 1;
    }
}

// Navigation
function updateVerseSelector() {
    const sel = document.getElementById('sel-verse');
    if(!sel) return;
    sel.innerHTML = '';
    
    let count = 0;
    if (BIBLE_STRUCTURE[state.book]) {
        const chapterIdx = parseInt(state.chapter) - 1;
        if (BIBLE_STRUCTURE[state.book][chapterIdx]) {
            count = BIBLE_STRUCTURE[state.book][chapterIdx];
        }
    }
    
    if (count > 0) {
        for(let i=1; i<=count; i++) {
            let opt = document.createElement('option');
            opt.value = i; opt.text = i;
            if(i == state.verse) opt.selected = true;
            sel.appendChild(opt);
        }
    } else {
        let opt = document.createElement('option');
        opt.text = "-"; sel.appendChild(opt);
    }
}

function navigate() {
    const newBook = document.getElementById('sel-book').value;
    const newChapter = document.getElementById('inp-chapter').value;
    
    if (newBook !== state.book) {
        jumpTo(newBook, 1, 1);
    } else {
        jumpTo(state.book, newChapter, 1);
    }
}

function navStep(direction) {
    let current = parseInt(state.chapter) || 1;
    let next = current + direction;
    if(next < 1) next = 1;
    const maxCh = (BIBLE_STRUCTURE[state.book]) ? BIBLE_STRUCTURE[state.book].length : 1;
    if(next > maxCh) next = maxCh;
    jumpTo(state.book, next, 1);
}

function jumpTo(b, c, v, isNew = true) {
    // 1. Update State
    state.book = b;
    state.chapter = c;
    state.verse = v;
    
    // 2. History Management
    if (isNew) {
        if (!state.history) state.history = [];
        // If we are in middle of history, slice off the future
        if (state.historyIndex < state.history.length - 1) {
            state.history = state.history.slice(0, state.historyIndex + 1);
        }
        state.history.push({ book: b, chapter: c, verse: v });
        state.historyIndex = state.history.length - 1;
    }
    updateHistoryButtons();

    // 3. UI Updates
    const selBook = document.getElementById('sel-book');
    const inpChapter = document.getElementById('inp-chapter');
    if (selBook) selBook.value = b;
    if (inpChapter) inpChapter.value = c;
    
    updateRefDisplay();
    updateVerseSelector(); // Requires BIBLE_STRUCTURE from state.js

    const pane1 = document.getElementById('pane-1');
    if (pane1 && pane1.dataset.type !== 'bible') {
        changePaneContent('pane-1', 'bible');
    } else {
        reloadPanes();
    }
    
    // Scroll to top on chapter change
    if (v === 1) {
        const content = document.getElementById('pane-1-content');
        if (content) content.scrollTop = 0;
    }

    saveLayoutState();
}

function setVerse(v) {
    state.verse = v;
    const sel = document.getElementById('sel-verse');
    if(sel) sel.value = v;

    updateRefDisplay();

    document.querySelectorAll('.verse-row').forEach(el => el.classList.remove('active'));
    const activeEl = document.getElementById(`v-${v}`);
    if(activeEl) {
        activeEl.classList.add('active');
        activeEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    saveLayoutState();
    updateContextPanes();
}

function scrollVerse(v) {
    const target = document.getElementById(`v-${v}`);
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Module Switching
function setCommModule(mod) {
    state.commModule = mod.toLowerCase();
    changePaneContent('pane-2', 'commentary');
    
    const titleSpan = document.getElementById('pane-2-title');
    const fullName = moduleNames[mod.toUpperCase()] || mod.toUpperCase();
    if (titleSpan) titleSpan.innerText = `— ${fullName}`;
    
    document.querySelectorAll('.module-card').forEach(btn => {
        if(btn.innerText === fullName || btn.innerText === mod.toUpperCase()) {
            document.querySelectorAll('.module-card').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }
    });
}

function switchRefModule(mod) {
    if (mod === 'HEBREW' || mod === 'GREEK') {
        state.lexModule = mod;
        changePaneContent('pane-2', 'lex_browse');
    } else {
        state.dictModule = mod;
        changePaneContent('pane-2', 'dictionary');
    }
}

function toggleInterlinear(forcedVal) {
    if (typeof forcedVal === 'boolean') {
        state.interlinear = forcedVal;
    } else {
        state.interlinear = !state.interlinear;
    }

    const chk = document.getElementById('chk-interlinear');
    if (chk) chk.checked = !!state.interlinear;

    if (state.interlinear) {
        document.body.classList.add('interlinear-active');
    } else {
        document.body.classList.remove('interlinear-active');
    }

    reloadPanes();
}

// Search & Lookup
function searchDict() {
    const term = document.getElementById('inp-dict-search').value;
    if(term) {
        state.lookupTerm = term;
        state.lookupType = 'dictionary';
        changePaneContent('pane-2', 'dictionary');
    }
}

function searchLex() {
    const term = document.getElementById('inp-lex-search').value;
    if(term) {
        state.lookupTerm = term;
        state.lookupType = (state.lexModule === 'HEBREW') ? 'strong_hebrew' : 'strong_greek';
        state.dictModule = state.lexModule; 
        changePaneContent('pane-2', 'lex_browse');
    }
}

function searchBible() {
    state.searchQuery = document.getElementById('inp-bible-search').value;
    state.searchOffset = 0;
    
    // Secret Debug Trigger
    if (state.searchQuery === 'DEBUG') {
        window.open('debug.php', '_blank', 'width=800,height=600');
        return;
    }

    const scopeVal = document.getElementById('sel-search-scope').value;
    state.searchScope = (scopeVal === 'CURRENT') ? state.book : scopeVal;
    
    if(state.searchQuery) {
        changePaneContent('pane-2', 'search');
    }
}

function showDef(term, type) {
    state.lookupTerm = term;
    state.lookupType = type;
    
    if (type.includes('strong')) {
        // It's a lexicon lookup
        state.lexModule = (type === 'strong_hebrew' || term.startsWith('H')) ? 'HEBREW' : 'GREEK';
        changePaneContent('pane-2', 'lex_browse');
    } else {
        // It's a dictionary lookup
        changePaneContent('pane-2', 'dictionary');
    }
}

function filterSidebar(query) {
    const items = document.querySelectorAll('.sidebar-item');
    const q = query.toLowerCase();
    items.forEach(el => {
        el.style.display = el.innerText.toLowerCase().includes(q) ? 'block' : 'none';
    });
}

function highlightItem(el) {
    document.querySelectorAll('.sidebar-item').forEach(d => d.classList.remove('active'));
    el.classList.add('active');
}

function reloadPanes() {
    loadPane('pane-1');
    updateContextPanes();
}

function changePaneContent(paneId, type) {
    const pane = document.getElementById(paneId);
    pane.dataset.type = type;
    const titleSpan = document.getElementById(`${paneId}-title`);
    
    const select = pane.querySelector('select');
    if(select) select.value = type;
    
    let commTitle = `— ${state.commModule.toUpperCase()}`;
    if (typeof moduleNames !== 'undefined' && moduleNames[state.commModule.toUpperCase()]) {
        commTitle = `— ${moduleNames[state.commModule.toUpperCase()]}`;
    }

    const titles = {
        'bible': '',
        'commentary': commTitle,
        'dictionary': `— ${state.dictModule}`,
        'lex_browse': `— ${state.lexModule}`,
        'xrefs': '— Cross References',
        'search': `— Search Results`
    };
    if (titleSpan) titleSpan.innerText = titles[type] || '';
    
    if (type === 'dict_browse') state.lookupTerm = "";
    
    if (state.layout.panes[paneId]) {
        state.layout.panes[paneId].type = type;
    }
    saveLayoutState();
    
    loadPane(paneId);
}

function updateContextPanes() {
    document.querySelectorAll('.pane').forEach(pane => {
        if (pane.dataset.type !== 'bible') {
            updatePaneContent(pane.id);
        }
    });
}

function openComm(mod, v) {
    state.verse = v;
    // Highlight logic
    document.querySelectorAll('.verse-row').forEach(el => el.classList.remove('active'));
    const activeEl = document.getElementById(`v-${v}`);
    if(activeEl) activeEl.classList.add('active');
    
    setCommModule(mod.toLowerCase());
}

// --- Verse Preview Logic ---
let hidePreviewTimeout = null;
let currentPreviewUrl = null;

document.addEventListener('mouseover', e => {
    const link = e.target.closest('.ref-link');
    if (link) {
        if (hidePreviewTimeout) clearTimeout(hidePreviewTimeout);
        
        // Remove previous highlights
        document.querySelectorAll('.ref-link').forEach(el => el.classList.remove('hover-highlight'));
        link.classList.add('hover-highlight');

        const book = link.dataset.book;
        const chapter = link.dataset.chapter;
        const verse = link.dataset.verse;
        
        if (book && chapter && verse) {
            showVersePreview(e, book, chapter, verse);
        }
    }
});

document.addEventListener('mouseout', e => {
    const link = e.target.closest('.ref-link');
    if (link) {
        link.classList.remove('hover-highlight');
        startHidePreview();
    }
});

// Allow the user to hover over the preview itself without it hiding
const previewEl = document.getElementById('verse-preview');
if (previewEl) {
    previewEl.addEventListener('mouseenter', () => {
        if (hidePreviewTimeout) clearTimeout(hidePreviewTimeout);
    });
    previewEl.addEventListener('mouseleave', () => {
        startHidePreview();
    });
}

function startHidePreview() {
    if (hidePreviewTimeout) clearTimeout(hidePreviewTimeout);
    hidePreviewTimeout = setTimeout(() => {
        hideVersePreview();
    }, 300); // 300ms grace period to move mouse to tooltip
}

function showVersePreview(e, b, c, v) {
    const preview = document.getElementById('verse-preview');
    const header = document.getElementById('preview-ref');
    const textDiv = document.getElementById('preview-text');
    const versionTag = document.getElementById('preview-version-tag');
    const endVerse = e.target.closest('.ref-link').dataset.endVerse;
    
    if (!preview || !header || !textDiv) return;

    const refLabel = endVerse ? `${b} ${c}:${v}-${endVerse}` : `${b} ${c}:${v}`;
    header.innerText = refLabel;
    
    preview.style.display = 'block';
    // Small timeout to allow display:block before adding .visible for transition
    setTimeout(() => preview.classList.add('visible'), 10);

    let x = e.clientX + 20;
    let y = e.clientY + 20;
    
    if (x + 450 > window.innerWidth) x = e.clientX - 470;
    if (y + 300 > window.innerHeight) y = e.clientY - 320;
    
    preview.style.left = `${x}px`;
    preview.style.top = `${y}px`;
    
    const cacheKey = `PREVIEW_${b}_${c}_${v}_${endVerse || ''}_${state.version}`;
    const cached = Cache.get('text', cacheKey);
    
    if (cached) {
        textDiv.innerHTML = cached;
        currentPreviewUrl = null;
    } else {
        let url = `${API_BASE}/api/bible/verse?book=${encodeURIComponent(b)}&chapter=${c}&verse=${v}&version=${state.version}`;
        if (endVerse) url += `&end_verse=${endVerse}`;

        if (currentPreviewUrl === url) return; // Already fetching this one
        
        textDiv.innerHTML = '<span style="color:var(--text-muted); font-style:italic;">Loading...</span>';
        if (versionTag) versionTag.innerText = state.version;
        
        currentPreviewUrl = url;

        // Use AbortController for fetch timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000); // 5s timeout

        fetch(url, { signal: controller.signal })
            .then(r => r.json())
            .then(data => {
                clearTimeout(timeoutId);
                if (currentPreviewUrl !== url) return; // Stale request
                
                const text = data.text || "Verse not found.";
                Cache.set('text', cacheKey, text);
                textDiv.innerHTML = text;
                currentPreviewUrl = null;
            })
            .catch(err => {
                clearTimeout(timeoutId);
                if (currentPreviewUrl !== url) return;
                
                const msg = (err.name === 'AbortError') ? 'Request timed out.' : 'Error loading preview.';
                textDiv.innerHTML = `<span style="color:red;">${msg}</span>`;
                currentPreviewUrl = null;
            });
    }
}

function hideVersePreview() {
    const preview = document.getElementById('verse-preview');
    if (preview) {
        preview.classList.remove('visible');
        // Wait for transition before hiding
        setTimeout(() => {
            if (!preview.classList.contains('visible')) {
                preview.style.display = 'none';
            }
        }, 200);
    }
}

// Context Menu Logic
let contextWord = "";

document.addEventListener('contextmenu', e => {
    const verseRow = e.target.closest('.verse-row');
    if (verseRow) {
        e.preventDefault();
        
        // Get the selected word or the word under cursor
        const selection = window.getSelection().toString().trim();
        if (selection) {
            contextWord = selection;
        } else {
            // Very simple word detection if no selection
            const range = document.caretRangeFromPoint(e.clientX, e.clientY);
            if (range) {
                range.expand('word');
                contextWord = range.toString().trim().replace(/[.,\/#!$%\^&\*;:{}=\-_`~()]/g,"");
            }
        }

        if (contextWord && contextWord.length > 1) {
            const menu = document.getElementById('context-menu');
            document.getElementById('ctx-word-search').innerText = contextWord;
            document.getElementById('ctx-word-define').innerText = contextWord;
            
            menu.style.display = 'block';
            menu.style.left = `${e.clientX}px`;
            menu.style.top = `${e.clientY}px`;
        }
    }
});

document.addEventListener('click', () => {
    document.getElementById('context-menu').style.display = 'none';
});

function contextSearch() {
    if (!contextWord) return;
    document.getElementById('inp-bible-search').value = contextWord;
    searchBible();
}

function contextDefine() {
    if (!contextWord) return;
    state.lookupTerm = contextWord;
    state.lookupType = 'dictionary';
    changePaneContent('pane-2', 'dictionary');
}

// ==========================================================================
// Unified Reference Navigator (Go to & 3-Step Grid Controller)
// ==========================================================================

function updateRefDisplay() {
    const lbl = document.getElementById('lbl-current-ref');
    if (lbl) {
        lbl.innerText = `${state.book} ${state.chapter}:${state.verse}`;
    }
}

// Fast Client-Side Bible Reference Parser
function parseBibleReference(text) {
    if (!text || typeof text !== 'string') return null;
    let s = text.trim().toLowerCase();
    if (!s) return null;

    s = s.replace(/\s+/g, ' ');
    s = s.replace(/^first\s+|^1st\s+|^i\s+/, '1 ')
         .replace(/^second\s+|^2nd\s+|^ii\s+/, '2 ')
         .replace(/^third\s+|^3rd\s+|^iii\s+/, '3 ');

    let bookKey = '', chStr = '', vStr = '';

    // Matches references like "jn 3:16", "1 cor 13 4", "ps 23", "gen 1"
    const numMatch = s.match(/(.+?)\s*(\d+)(?:[:\.\s](\d+))?$/);
    if (numMatch) {
        bookKey = numMatch[1].trim();
        chStr = numMatch[2];
        vStr = numMatch[3] || '';
    } else {
        bookKey = s.trim();
    }

    let book = (typeof BOOK_ABBREVIATIONS !== 'undefined') ? (BOOK_ABBREVIATIONS[bookKey] || BOOK_ABBREVIATIONS[bookKey.replace(/\s+/g, '')]) : null;

    if (!book && chStr && !vStr) {
        const fullKey = s.replace(/\s+/g, '');
        const found = (typeof BOOK_ABBREVIATIONS !== 'undefined') ? (BOOK_ABBREVIATIONS[s] || BOOK_ABBREVIATIONS[fullKey]) : null;
        if (found) {
            book = found;
            chStr = '1';
            vStr = '1';
        }
    }

    if (!book || !BIBLE_STRUCTURE[book]) return null;

    const maxChapters = BIBLE_STRUCTURE[book].length;
    let chapter = chStr ? parseInt(chStr, 10) : 1;
    if (isNaN(chapter) || chapter < 1) chapter = 1;
    if (chapter > maxChapters) chapter = maxChapters;

    const maxVerses = (BIBLE_STRUCTURE[book] && BIBLE_STRUCTURE[book][chapter - 1]) ? BIBLE_STRUCTURE[book][chapter - 1] : 1;
    let verse = vStr ? parseInt(vStr, 10) : 1;
    if (isNaN(verse) || verse < 1) verse = 1;
    if (verse > maxVerses) verse = maxVerses;

    return {
        book: book,
        chapter: chapter,
        verse: verse,
        formatted: `${book} ${chapter}:${verse}`
    };
}

let pickerState = {
    book: "Genesis",
    chapter: 1,
    verse: 1,
    step: "book"
};

function toggleRefPicker() {
    const overlay = document.getElementById('ref-picker-overlay');
    if (!overlay) return;
    if (overlay.style.display === 'none' || !overlay.style.display) {
        openRefPicker();
    } else {
        closeRefPicker();
    }
}

function openRefPicker() {
    const overlay = document.getElementById('ref-picker-overlay');
    if (!overlay) return;

    pickerState.book = state.book || "Genesis";
    pickerState.chapter = state.chapter || 1;
    pickerState.verse = state.verse || 1;

    overlay.style.display = 'flex';
    setPickerStep('book');

    const input = document.getElementById('inp-ref-goto');
    const badge = document.getElementById('ref-parsed-badge');
    if (input) {
        input.value = '';
        setTimeout(() => input.focus(), 60);
    }
    if (badge) badge.style.display = 'none';
}

function closeRefPicker() {
    const overlay = document.getElementById('ref-picker-overlay');
    if (overlay) overlay.style.display = 'none';
}

function setPickerStep(step) {
    pickerState.step = step;

    // Update Step Tabs
    ['book', 'chapter', 'verse'].forEach(s => {
        const btn = document.getElementById(`step-btn-${s}`);
        if (btn) btn.classList.toggle('active', s === step);
    });

    const stepLabelBook = document.getElementById('step-label-book');
    const stepLabelChapter = document.getElementById('step-label-chapter');
    const stepLabelVerse = document.getElementById('step-label-verse');
    if (stepLabelBook) stepLabelBook.innerText = pickerState.book;
    if (stepLabelChapter) stepLabelChapter.innerText = `Ch ${pickerState.chapter}`;
    if (stepLabelVerse) stepLabelVerse.innerText = `V ${pickerState.verse}`;

    // Toggle Views
    const vBooks = document.getElementById('grid-view-books');
    const vChapters = document.getElementById('grid-view-chapters');
    const vVerses = document.getElementById('grid-view-verses');

    if (vBooks) vBooks.style.display = (step === 'book') ? 'flex' : 'none';
    if (vChapters) vChapters.style.display = (step === 'chapter') ? 'flex' : 'none';
    if (vVerses) vVerses.style.display = (step === 'verse') ? 'flex' : 'none';

    if (step === 'book') renderPickerBooks();
    if (step === 'chapter') renderPickerChapters(pickerState.book);
    if (step === 'verse') renderPickerVerses(pickerState.book, pickerState.chapter);
}

function renderPickerBooks() {
    const otGrid = document.getElementById('ot-books-grid');
    const ntGrid = document.getElementById('nt-books-grid');

    if (otGrid && typeof otBooks !== 'undefined') {
        otGrid.innerHTML = '';
        otBooks.forEach(b => {
            const btn = document.createElement('div');
            btn.className = 'book-chip' + (b === pickerState.book ? ' current' : '');
            btn.innerText = b;
            btn.title = b;
            btn.onclick = () => selectPickerBook(b);
            otGrid.appendChild(btn);
        });
    }

    if (ntGrid && typeof ntBooks !== 'undefined') {
        ntGrid.innerHTML = '';
        ntBooks.forEach(b => {
            const btn = document.createElement('div');
            btn.className = 'book-chip' + (b === pickerState.book ? ' current' : '');
            btn.innerText = b;
            btn.title = b;
            btn.onclick = () => selectPickerBook(b);
            ntGrid.appendChild(btn);
        });
    }
}

function selectPickerBook(book) {
    pickerState.book = book;
    pickerState.chapter = 1;
    pickerState.verse = 1;
    setPickerStep('chapter');
}

function renderPickerChapters(book) {
    const title = document.getElementById('chapter-view-title');
    if (title) title.innerText = `${book} - Select Chapter`;

    const grid = document.getElementById('chapters-grid');
    if (!grid) return;
    grid.innerHTML = '';

    const chapterCount = (BIBLE_STRUCTURE[book] || []).length;
    for (let c = 1; c <= chapterCount; c++) {
        const cell = document.createElement('div');
        cell.className = 'number-cell' + (c === pickerState.chapter && book === state.book ? ' current' : '');
        cell.innerText = c;
        cell.onclick = () => selectPickerChapter(c);
        grid.appendChild(cell);
    }
}

function selectPickerChapter(ch) {
    pickerState.chapter = ch;
    pickerState.verse = 1;
    setPickerStep('verse');
}

function renderPickerVerses(book, chapter) {
    const title = document.getElementById('verse-view-title');
    if (title) title.innerText = `${book} ${chapter} - Select Verse`;

    const grid = document.getElementById('verses-grid');
    if (!grid) return;
    grid.innerHTML = '';

    const verseCount = (BIBLE_STRUCTURE[book] && BIBLE_STRUCTURE[book][chapter - 1]) ? BIBLE_STRUCTURE[book][chapter - 1] : 1;
    for (let v = 1; v <= verseCount; v++) {
        const cell = document.createElement('div');
        cell.className = 'number-cell' + (v === pickerState.verse && chapter === state.chapter && book === state.book ? ' current' : '');
        cell.innerText = v;
        cell.onclick = () => selectPickerVerse(v);
        grid.appendChild(cell);
    }
}

function selectPickerVerse(v) {
    pickerState.verse = v;
    closeRefPicker();
    jumpTo(pickerState.book, pickerState.chapter, v);
    if (v > 1) {
        setTimeout(() => scrollVerse(v), 250);
    }
}

function onRefGotoInput(val) {
    const badge = document.getElementById('ref-parsed-badge');
    if (!badge) return;

    if (!val || !val.trim()) {
        badge.style.display = 'none';
        return;
    }

    const parsed = parseBibleReference(val);
    if (parsed) {
        badge.innerText = `✓ ${parsed.formatted} (Enter to Jump)`;
        badge.style.display = 'inline-block';
    } else {
        badge.style.display = 'none';
    }
}

function onRefGotoKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const input = document.getElementById('inp-ref-goto');
        if (!input) return;
        const parsed = parseBibleReference(input.value);
        if (parsed) {
            closeRefPicker();
            jumpTo(parsed.book, parsed.chapter, parsed.verse);
            if (parsed.verse > 1) {
                setTimeout(() => scrollVerse(parsed.verse), 250);
            }
        } else {
            // Shake / feedback on unmatched input
            input.style.boxShadow = '0 0 0 2px #ef4444';
            setTimeout(() => input.style.boxShadow = 'none', 600);
        }
    } else if (e.key === 'Escape') {
        closeRefPicker();
    }
}

// Global Keyboard Shortcuts (Ctrl+G, /, Escape)
document.addEventListener('keydown', e => {
    const overlay = document.getElementById('ref-picker-overlay');
    const isPickerOpen = overlay && overlay.style.display !== 'none';

    if (isPickerOpen) {
        if (e.key === 'Escape') {
            closeRefPicker();
            return;
        }
    }

    // Ctrl+G or Cmd+G
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'g') {
        e.preventDefault();
        openRefPicker();
        return;
    }

    // '/' when not in an input or textarea
    if (e.key === '/' && !isPickerOpen) {
        const tag = (document.activeElement && document.activeElement.tagName) ? document.activeElement.tagName.toUpperCase() : '';
        if (tag !== 'INPUT' && tag !== 'TEXTAREA') {
            e.preventDefault();
            openRefPicker();
            return;
        }
    }
});

// ==========================================================================
// Lumina Universal Custom Dropdown Engine
// Replaces OS-native select popups with modern, fully-styled UI dropdowns
// ==========================================================================

class CustomDropdown {
    constructor(selectEl) {
        this.select = selectEl;
        this.wrapper = null;
        this.trigger = null;
        this.labelSpan = null;
        this.menu = null;
        this.isOpen = false;
        
        this.init();
    }
    
    init() {
        if (!this.select || this.select._customDropdown) return;
        this.select._customDropdown = this;
        
        // Hide native select from layout while keeping form semantics
        this.select.style.position = 'absolute';
        this.select.style.opacity = '0';
        this.select.style.pointerEvents = 'none';
        this.select.style.width = '0';
        this.select.style.height = '0';
        this.select.style.margin = '0';
        this.select.style.padding = '0';
        this.select.style.border = 'none';
        this.select.tabIndex = -1;

        // Container wrapper
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'custom-dropdown-container';
        if (this.select.id) this.wrapper.dataset.for = this.select.id;
        
        // Inherit styling hooks
        if (this.select.classList.contains('verse-select')) {
            this.wrapper.classList.add('custom-dropdown-verse');
        }
        if (this.select.classList.contains('comm-module-select')) {
            this.wrapper.classList.add('custom-dropdown-comm');
        }
        if (this.select.classList.contains('pane-type-select')) {
            this.wrapper.classList.add('custom-dropdown-pane');
        }

        // Trigger Button
        this.trigger = document.createElement('button');
        this.trigger.type = 'button';
        this.trigger.className = 'custom-dropdown-trigger';
        if (this.select.title) this.trigger.title = this.select.title;
        
        this.labelSpan = document.createElement('span');
        this.labelSpan.className = 'custom-dropdown-label';
        
        const arrow = document.createElement('span');
        arrow.className = 'custom-dropdown-caret';
        arrow.textContent = '▾';
        
        this.trigger.appendChild(this.labelSpan);
        this.trigger.appendChild(arrow);
        
        // Dropdown Menu Container
        this.menu = document.createElement('div');
        this.menu.className = 'custom-dropdown-menu';
        this.menu.style.display = 'none';
        
        this.wrapper.appendChild(this.trigger);
        this.wrapper.appendChild(this.menu);
        
        // Insert right after select in DOM
        if (this.select.parentNode) {
            this.select.parentNode.insertBefore(this.wrapper, this.select.nextSibling);
        }
        
        // Populate items
        this.rebuildMenu();
        
        // Intercept direct programmatic assignment of select.value
        const self = this;
        const originalDescriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
        if (originalDescriptor && originalDescriptor.set) {
            Object.defineProperty(this.select, 'value', {
                configurable: true,
                get() {
                    return originalDescriptor.get.call(this);
                },
                set(newVal) {
                    originalDescriptor.set.call(this, newVal);
                    self.syncFromSelect();
                }
            });
        }
        
        // Click to toggle
        this.trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            if (this.select.disabled) return;
            this.toggle();
        });
        
        // Change event sync
        this.select.addEventListener('change', () => {
            this.syncFromSelect();
        });

        // Watch for dynamic options changes (e.g. verse counts, translations loaded)
        this.observer = new MutationObserver(() => {
            this.rebuildMenu();
        });
        this.observer.observe(this.select, { childList: true, subtree: true, attributes: true });
    }
    
    rebuildMenu() {
        this.menu.innerHTML = '';
        const options = Array.from(this.select.options);
        
        if (options.length === 0) {
            this.labelSpan.textContent = '-';
            return;
        }

        const optgroups = this.select.querySelectorAll('optgroup');
        if (optgroups.length > 0) {
            optgroups.forEach(group => {
                const header = document.createElement('div');
                header.className = 'custom-dropdown-group-header';
                header.textContent = group.label;
                this.menu.appendChild(header);
                
                Array.from(group.children).forEach(opt => {
                    this.createItem(opt);
                });
            });
        } else {
            options.forEach(opt => {
                this.createItem(opt);
            });
        }
        
        this.syncFromSelect();
    }
    
    createItem(opt) {
        const item = document.createElement('div');
        item.className = 'custom-dropdown-item';
        item.dataset.value = opt.value;
        
        if (opt.disabled) {
            item.classList.add('disabled');
        }
        
        const textSpan = document.createElement('span');
        textSpan.className = 'custom-dropdown-item-text';
        textSpan.textContent = opt.text;
        item.appendChild(textSpan);
        
        const checkSpan = document.createElement('span');
        checkSpan.className = 'custom-dropdown-check';
        checkSpan.textContent = '✓';
        item.appendChild(checkSpan);
        
        if (!opt.disabled) {
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                this.selectValue(opt.value);
            });
        }
        
        this.menu.appendChild(item);
    }
    
    selectValue(val) {
        this.select.value = val;
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
        if (typeof this.select.onchange === 'function') {
            this.select.onchange(new Event('change'));
        }
        this.syncFromSelect();
        this.close();
    }
    
    syncFromSelect() {
        const selectedOpt = this.select.options[this.select.selectedIndex];
        if (selectedOpt) {
            this.labelSpan.textContent = selectedOpt.text;
        } else {
            this.labelSpan.textContent = '';
        }
        
        const items = this.menu.querySelectorAll('.custom-dropdown-item');
        items.forEach(item => {
            if (item.dataset.value === this.select.value) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
        
        if (this.select.disabled) {
            this.trigger.classList.add('disabled');
        } else {
            this.trigger.classList.remove('disabled');
        }
    }
    
    open() {
        CustomDropdown.closeAll();
        
        // Smart screen edge positioning
        const rect = this.wrapper.getBoundingClientRect();
        
        if (window.innerWidth - rect.left < 280) {
            this.menu.style.left = 'auto';
            this.menu.style.right = '0';
        } else {
            this.menu.style.left = '0';
            this.menu.style.right = 'auto';
        }
        
        if (window.innerHeight - rect.bottom < 260) {
            this.menu.style.top = 'auto';
            this.menu.style.bottom = 'calc(100% + 4px)';
        } else {
            this.menu.style.top = 'calc(100% + 4px)';
            this.menu.style.bottom = 'auto';
        }
        
        this.menu.style.display = 'flex';
        this.trigger.classList.add('open');
        this.isOpen = true;
        
        const activeItem = this.menu.querySelector('.custom-dropdown-item.active');
        if (activeItem) {
            activeItem.scrollIntoView({ block: 'nearest' });
        }
    }
    
    close() {
        this.menu.style.display = 'none';
        this.trigger.classList.remove('open');
        this.isOpen = false;
    }
    
    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }
    
    static closeAll() {
        document.querySelectorAll('.custom-dropdown-menu').forEach(m => m.style.display = 'none');
        document.querySelectorAll('.custom-dropdown-trigger').forEach(t => t.classList.remove('open'));
    }
}

// Global Document Listeners
document.addEventListener('click', (e) => {
    if (!e.target.closest('.custom-dropdown-container')) {
        CustomDropdown.closeAll();
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        CustomDropdown.closeAll();
    }
});

// Auto-upgrade helper for all matching selects
function initAllCustomDropdowns(root = document) {
    const selects = root.querySelectorAll('select:not([data-no-custom])');
    selects.forEach(sel => {
        if (sel.id === 'sel-book' || sel.style.display === 'none') return;
        if (!sel._customDropdown) {
            new CustomDropdown(sel);
        }
    });
}