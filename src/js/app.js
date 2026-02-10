// --- App Initialization ---

window.onload = function() {
    console.log("Bible App Initializing...");

    // 1. Load Theme & Settings
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.body.setAttribute('data-theme', savedTheme);
    if(typeof updateThemeUI === 'function') updateThemeUI(savedTheme);
    
    const savedSize = localStorage.getItem('fontSize');
    if(savedSize) state.fontSize = parseInt(savedSize);
    if(typeof applyFontSize === 'function') applyFontSize();

    // Load Persistent Layout
    const savedLayout = localStorage.getItem('lumina_layout');
    if (savedLayout) {
        state.layout = JSON.parse(savedLayout);
        setLayout(state.layout.orientation);
        Object.keys(state.layout.panes).forEach(id => {
            const p = state.layout.panes[id];
            const el = document.getElementById(id);
            if (!el) return;
            if (p.minimized) el.classList.add('minimized');
            if (p.maximized) el.classList.add('maximized');
            if (p.closed) el.classList.add('closed');
            // We'll set the type in step 5
        });
    }

    // Load Persistent Navigation
    const savedNav = localStorage.getItem('lumina_nav');
    if (savedNav) {
        const nav = JSON.parse(savedNav);
        state.book = nav.book;
        state.chapter = nav.chapter;
        state.verse = nav.verse;
        state.version = nav.version;
    }

    // 2. Populate Bible Versions
    fetch('api.php?action=version_list')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('sel-version');
            if(sel) {
                sel.innerHTML = ''; 
                data.versions.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v;
                    opt.text = versionNames[v] || v; 
                    if(v === state.version) opt.selected = true;
                    sel.appendChild(opt);
                });
            }
        })
        .catch(e => console.error("Error loading versions:", e));

    // 3. Populate Commentaries
    fetch('api.php?action=commentary_list')
        .then(r => r.json())
        .then(data => {
            const container = document.querySelector('#tab-comm .ribbon-group > div');
            if(container) {
                container.style.display = 'grid'; 
                container.style.gridTemplateColumns = 'repeat(auto-fill, minmax(220px, 1fr))'; 
                container.style.gap = '8px'; 
                container.style.width = '100%';
                
                container.innerHTML = ''; 
                data.modules.forEach(mod => {
                    const btn = document.createElement('div');
                    btn.className = 'module-card';
                    btn.style.cssText = "background:var(--bg-panel); border:1px solid var(--border); padding:8px 12px; border-radius:4px; cursor:pointer; font-size:12px; display:flex; align-items:center; min-height:30px;";
                    
                    if (state.commModule.toUpperCase() === mod) {
                        btn.classList.add('active');
                        btn.style.borderColor = 'var(--accent)';
                        btn.style.backgroundColor = 'var(--bg-app)';
                    }
                    
                    btn.innerText = moduleNames[mod] || mod;
                    btn.title = btn.innerText;
                    
                    btn.onclick = () => {
                        document.querySelectorAll('.module-card').forEach(b => {
                            b.classList.remove('active');
                            b.style.borderColor = 'var(--border)';
                            b.style.backgroundColor = 'var(--bg-panel)';
                        });
                        btn.classList.add('active');
                        btn.style.borderColor = 'var(--accent)';
                        btn.style.backgroundColor = 'var(--bg-app)';
                        setCommModule(mod.toLowerCase());
                    };
                    container.appendChild(btn);
                });
            }
        });

    // 4. Populate Book Selector
    const bookSel = document.getElementById('sel-book');
    if(bookSel) {
        const ot = document.createElement('optgroup'); 
        ot.label = "Old Testament"; 
        otBooks.forEach(b => { 
            let o = document.createElement('option'); 
            o.value = b; 
            o.text = b; 
            ot.appendChild(o); 
        }); 
        bookSel.appendChild(ot);

        const nt = document.createElement('optgroup'); 
        nt.label = "New Testament"; 
        ntBooks.forEach(b => { 
            let o = document.createElement('option'); 
            o.value = b; 
            o.text = b; 
            nt.appendChild(o); 
        }); 
        bookSel.appendChild(nt);
    }

    // 5. Initial Load
    if(typeof updateVerseSelector === 'function') updateVerseSelector(); 
    
    // Update selectors to match state
    const selBook = document.getElementById('sel-book');
    const inpChapter = document.getElementById('inp-chapter');
    if (selBook) selBook.value = state.book;
    if (inpChapter) inpChapter.value = state.chapter;

    if(typeof loadPane === 'function') {
        Object.keys(state.layout.panes).forEach(id => {
            changePaneContent(id, state.layout.panes[id].type);
        });
        
        // Init History
        if(state.history) {
            state.history.push({ book: state.book, chapter: state.chapter, verse: state.verse });
            state.historyIndex = 0;
            if(typeof updateHistoryButtons === 'function') updateHistoryButtons();
        }
    }
};
