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
    let current = parseInt(document.getElementById('inp-chapter').value);
    let next = current + direction;
    if(next < 1) next = 1;
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
    titleSpan.innerText = `Commentary (${fullName})`;
    
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

function toggleInterlinear() {
    state.interlinear = !state.interlinear;
    const btn = document.getElementById('btn-interlinear');
    btn.innerText = state.interlinear ? "Interlinear: ON" : "Interlinear: OFF";
    btn.style.color = state.interlinear ? "var(--accent)" : "var(--text-main)";
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
    
    let commTitle = `Commentary (${state.commModule.toUpperCase()})`;
    if (moduleNames[state.commModule.toUpperCase()]) commTitle = moduleNames[state.commModule.toUpperCase()];

    const titles = {
        'bible': 'Bible Text',
        'commentary': commTitle,
        'dictionary': `Dictionary (${state.dictModule})`,
        'lex_browse': `Lexicon (${state.lexModule})`,
        'xrefs': 'Cross References',
        'search': `Search Results`
    };
    titleSpan.innerText = titles[type] || 'Pane';
    
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