// --- API Interaction & Caching ---

function loadPane(paneId) {
    const pane = document.getElementById(paneId);
    const type = pane.dataset.type;
    const contentDiv = document.getElementById(`${paneId}-content`);
    const titleSpan = document.getElementById(`${paneId}-title`);
    
    state.version = document.getElementById('sel-version').value || 'KJV';

    if (type === 'bible') {
        titleSpan.innerText = `${state.book} ${state.chapter} (${state.version})`;
        
        // 1. Cache Check
        const cacheKey = `${state.book}_${state.chapter}_${state.version}_${state.interlinear}`;
        const cachedData = Cache.get('text', cacheKey);
        
        if (cachedData) {
            renderBibleText(contentDiv, cachedData);
            return;
        }

        // 2. Fetch
        const apiUrl = `api.php?action=text&book=${encodeURIComponent(state.book)}&chapter=${state.chapter}&version=${state.version}&interlinear=${state.interlinear}`;
        fetch(apiUrl)
            .then(r => {
                if (!r.ok) throw new Error("Network response was not ok");
                return r.json();
            })
            .then(data => {
                if (!data.verses) { contentDiv.innerHTML = "Error loading."; return; }
                
                // 3. Cache Set
                Cache.set('text', cacheKey, data.verses);
                renderBibleText(contentDiv, data.verses);
            })
            .catch(err => {
                console.error("Fetch error:", err);
                contentDiv.innerHTML = "Failed to load content.";
            });
    } else {
        updatePaneContent(paneId);
    }
}

function renderBibleText(container, verses) {
    let html = '';
    verses.forEach(v => {
        let text = v.text;
        // Legacy manual replacements if DB doesn't have interlinear
        if (!state.interlinear && !v.strongs) {
            text = text.replace(/God/g, 'God<sup class="strongs" onclick="event.stopPropagation(); showDef(\'H430\', \'strong_hebrew\')">H430</sup>');
        }
        
        let linksHtml = '';
        if (v.modules) {
            const mods = v.modules.split(',');
            linksHtml = '<div class="verse-links">';
            mods.forEach(m => {
                linksHtml += `<span class="v-link" onclick="event.stopPropagation(); openComm('${m}', ${v.verse})">${m}</span>`;
            });
            linksHtml += '</div>';
        }
        
        html += `<div class="verse-row ${v.verse == state.verse ? 'active' : ''}" id="v-${v.verse}" onclick="setVerse(${v.verse})">
                    <span class="v-num">${v.verse}</span>${text}
                    ${linksHtml}
                 </div>`;
    });
    container.innerHTML = html;
    
    setTimeout(() => {
        const target = document.getElementById(`v-${state.verse}`);
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 50);
}

function updatePaneContent(paneId) {
    const pane = document.getElementById(paneId);
    const type = pane.dataset.type;
    const contentDiv = pane.querySelector('.pane-content');
    
    contentDiv.style.display = 'block';
    
    if (type === 'commentary') {
        const cacheKey = `${state.book}_${state.chapter}_${state.verse}_${state.commModule}`;
        const cached = Cache.get('commentary', cacheKey);
        
        if (cached) {
            renderCommentary(contentDiv, cached);
            return;
        }
        
        contentDiv.innerHTML = 'Loading...';
        const apiUrl = `api.php?action=commentary&book=${encodeURIComponent(state.book)}&chapter=${state.chapter}&verse=${state.verse}&module=${state.commModule}`;
        fetch(apiUrl)
            .then(r => r.json())
            .then(data => {
                Cache.set('commentary', cacheKey, data.text);
                renderCommentary(contentDiv, data.text);
            })
            .catch(e => contentDiv.innerHTML = "Error loading commentary.");

    } else if (type === 'xrefs') {
        const cacheKey = `${state.book}_${state.chapter}_${state.verse}`;
        const cached = Cache.get('xrefs', cacheKey);
        
        if (cached) {
            renderXrefs(contentDiv, cached);
            return;
        }

        contentDiv.innerHTML = 'Loading...';
        const apiUrl = `api.php?action=xrefs&book=${encodeURIComponent(state.book)}&chapter=${state.chapter}&verse=${state.verse}`;
        fetch(apiUrl)
            .then(r => {
                const ct = r.headers.get("content-type");
                if (!ct || !ct.includes("json")) {
                    throw new Error("API returned non-JSON response");
                }
                return r.json();
            })
            .then(data => {
                Cache.set('xrefs', cacheKey, data.xrefs);
                renderXrefs(contentDiv, data.xrefs);
            })
            .catch(e => {
                console.error(e);
                contentDiv.innerHTML = `<div class="comm-card" style="color:red;">Error loading cross-references.<br><small>${e.message}</small></div>`;
            });

    } else if (type === 'dictionary' || type === 'lex_browse') {
        contentDiv.style.display = 'flex';
        contentDiv.style.flexDirection = 'column';
        contentDiv.style.padding = '0';
        
        let isLex = (type === 'lex_browse');
        let currentMod = isLex ? state.lexModule : state.dictModule;
        
        renderDictLayout(contentDiv, currentMod);

        const listDiv = document.getElementById('dict-topics-list');
        const topicKey = `TOPICS_${currentMod}`;
        const cachedTopics = Cache.get('dictionary', topicKey);
        
        if (cachedTopics) {
            renderTopicList(listDiv, cachedTopics, currentMod);
        } else {
            listDiv.innerHTML = "Loading...";
            fetch(`api.php?action=topics&module=${currentMod}`)
                .then(r => r.json())
                .then(data => {
                    Cache.set('dictionary', topicKey, data.topics);
                    renderTopicList(listDiv, data.topics, currentMod);
                });
        }

        if(state.lookupTerm) {
            const defDiv = document.getElementById('dict-definition');
            let defType = (currentMod === 'HEBREW') ? 'strong_hebrew' : (currentMod === 'GREEK') ? 'strong_greek' : 'dictionary';
            const defKey = `${state.lookupTerm}_${defType}_${currentMod}`;
            const cachedDef = Cache.get('dictionary', defKey);
            
            if (cachedDef) {
                defDiv.innerHTML = `<div style="font-size:16px; line-height:1.6;">${(cachedDef || "Not found.").replace(/\n/g, '<br>')}<\/div>`;
            } else {
                defDiv.innerHTML = "Loading...";
                fetch(`api.php?action=definition&term=${encodeURIComponent(state.lookupTerm)}&type=${defType}&module=${currentMod}`)
                    .then(r => r.json())
                    .then(data => {
                        Cache.set('dictionary', defKey, data.definition);
                        defDiv.innerHTML = `<div style="font-size:16px; line-height:1.6;">${(data.definition || "Not found.").replace(/\n/g, '<br>')}<\/div>`;
                    });
            }
        }

    } else if (type === 'search') {
        if (!state.searchQuery) {
            contentDiv.innerHTML = `<div style="padding:20px; text-align:center;">Enter keywords.</div>`;
            return;
        }

        // Inline Debug Display
        if (state.searchQuery === 'DEBUG') {
            contentDiv.innerHTML = 'Running System Diagnostic...';
            fetch('debug.php')
                .then(r => r.text())
                .then(html => {
                    contentDiv.innerHTML = `<div style="font-family:monospace; font-size:12px; white-space:pre-wrap; background:#000; color:#0f0; padding:20px; border-radius:8px;">${html}</div>`;
                })
                .catch(e => {
                    contentDiv.innerHTML = `<div style="color:red; padding:20px;">Diagnostic Failed: ${e.message}</div>`;
                });
            return;
        }
        
        fetch(`api.php?action=search&q=${encodeURIComponent(state.searchQuery)}&version=${state.version}&scope=${encodeURIComponent(state.searchScope)}&offset=${state.searchOffset}`)
            .then(r => r.json())
            .then(data => {
                const currentShowing = Math.min(state.searchOffset + 200, data.count);
                document.getElementById(`${paneId}-title`).innerText = `Search: ${state.searchQuery} (${currentShowing} of ${data.count})`;
                
                let resultsHtml = "";
                if(!data.results || data.results.length === 0) {
                    resultsHtml = "No matches found.";
                } else {
                    data.results.forEach(r => {
                        resultsHtml += `<div class="comm-card" style="cursor:pointer; margin-top:10px;" 
                                     onclick="jumpTo('${r.book_name.replace(/'/g, "\'")}', ${r.chapter}, ${r.verse})"
                                     onmouseover="this.style.borderColor='var(--accent)'" 
                                     onmouseout="this.style.borderColor='var(--border)'">
                                    <div style="font-size:11px; font-weight:bold; color:var(--accent); text-transform:uppercase; margin-bottom:8px; border-bottom:1px solid var(--border); padding-bottom:4px;">
                                        ${r.book_name} ${r.chapter}:${r.verse}
                                    </div>
                                    <div style="font-size:15px; line-height:1.6; font-family:'Merriweather', serif;">${r.text}</div>
                                 </div>`;
                    });
                }

                if (state.searchOffset === 0) {
                    let wrapper = `<div style="padding:15px;" id="search-results-list">${resultsHtml}</div>`;
                    if (data.count > 200) {
                        wrapper += `<div id="search-load-more-container" style="padding:20px; text-align:center;">
                                        <button onclick="loadMoreResults('${paneId}')" class="primary" style="padding:8px 20px;">Load More Results</button>
                                    </div>`;
                    }
                    contentDiv.innerHTML = wrapper;
                } else {
                    const list = document.getElementById('search-results-list');
                    if (list) list.innerHTML += resultsHtml;
                    
                    const loadMoreContainer = document.getElementById('search-load-more-container');
                    if (loadMoreContainer) {
                        if (currentShowing >= data.count) {
                            loadMoreContainer.style.display = 'none';
                        }
                    }
                }
            });
    }
}

function loadMoreResults(paneId) {
    state.searchOffset += 200;
    updatePaneContent(paneId);
}

function renderCommentary(container, text) {
    container.innerHTML = `<div class="comm-card">
                        <h3 style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;">${state.book} ${state.chapter}:${state.verse} (${state.commModule.toUpperCase()})</h3>
                        ${text || "No commentary found."} 
                      </div>`;
}

function renderXrefs(container, refs) {
    if(!refs || refs.length === 0) {
        container.innerHTML = `<div class="comm-card"><h3>Cross References</h3><p>No cross references found.</p></div>`;
        return;
    }
    let html = `<div class="comm-card"><h3 style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;">Cross References for ${state.book} ${state.chapter}:${state.verse}</h3><div style="display:flex; flex-wrap:wrap; gap:8px;">`;
    
    refs.forEach(x => {
        // Safe book name for JS string
        const safeBook = x.book.replace(/'/g, "\\'");
        html += `<span class="v-link" onclick="jumpTo('${safeBook}', ${x.chapter}, ${x.verse})" style="font-size:13px; padding:6px 10px;">${x.book} ${x.chapter}:${x.verse}</span>`;
    });
    
    html += `</div></div>`;
    container.innerHTML = html;
}

function renderDictLayout(container, currentMod) {
    container.innerHTML = `
        <div style="flex:0 0 40px; background:var(--bg-app); border-bottom:1px solid var(--border); display:flex; align-items:center; padding:0 10px; gap:8px;">
            <span style="font-size:11px; font-weight:bold; color:var(--text-muted); text-transform:uppercase;">Source:</span>
            <select onchange="switchRefModule(this.value)" style="border:1px solid var(--border); padding:2px 4px; font-weight:600; font-size:12px;">
                <optgroup label="Dictionaries">
                    <option value="EASTON" ${currentMod=='EASTON'?'selected':''}>Easton's</option>
                    <option value="SMITH" ${currentMod=='SMITH'?'selected':''}>Smith's</option>
                    <option value="ATSD" ${currentMod=='ATSD'?'selected':''}>ATSD</option>
                    <option value="NAMES" ${currentMod=='NAMES'?'selected':''}>Bible Names</option>
                </optgroup>
                <optgroup label="Lexicons">
                    <option value="HEBREW" ${currentMod=='HEBREW'?'selected':''}>Strong's Hebrew</option>
                    <option value="GREEK" ${currentMod=='GREEK'?'selected':''}>Strong's Greek</option>
                </optgroup>
            </select>
            <div style="flex:1;"></div>
            <input type="text" placeholder="Filter..." onkeyup="filterSidebar(this.value)" style="padding:2px 5px; font-size:12px;">
        </div>
        <div style="flex:1; display:flex; overflow:hidden;">
            <div id="dict-sidebar" style="width:220px; border-right:1px solid var(--border); overflow-y:auto; background:var(--bg-app);">
                <div id="dict-topics-list">Loading...</div>
            </div>
            <div id="dict-display" style="flex:1; padding:20px; overflow-y:auto;">
                <div id="dict-definition">${state.lookupTerm ? 'Loading...' : '<div style="display:flex; justify-content:center; align-items:center; height:100%; color:var(--text-muted);">Select an item</div>'}
            </div>
        </div>`;
}

function renderTopicList(container, topics, currentMod) {
    let html = '';
    let defType = (currentMod === 'HEBREW') ? 'strong_hebrew' : (currentMod === 'GREEK') ? 'strong_greek' : 'dictionary';
    
    topics.forEach(t => {
        let activeClass = (t.id === state.lookupTerm) ? 'active' : '';
        // Add data-id for scroll targeting
        html += `<div class="sidebar-item ${activeClass}" data-id="${t.id}" 
                     onclick="showDef('${t.id}', '${defType}'); highlightItem(this);">${t.label}</div>`;
    });
    container.innerHTML = html;
    
    // Auto-Scroll to Active Item
    setTimeout(() => {
        const active = container.querySelector('.sidebar-item.active');
        if (active) {
            active.scrollIntoView({ behavior: 'auto', block: 'center' });
        } else if (state.lookupTerm) {
            // Fallback: Try to find by attribute if class wasn't set yet (e.g. initial load)
            const target = container.querySelector(`.sidebar-item[data-id="${state.lookupTerm}"]`);
            if (target) {
                highlightItem(target);
                target.scrollIntoView({ behavior: 'auto', block: 'center' });
            }
        }
    }, 100);
}