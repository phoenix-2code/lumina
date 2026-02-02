const Cache = {
    // Stores
    text: new Map(), // key: "Book_Chapter_Version_Interlinear"
    commentary: new Map(), // key: "Book_Chapter_Verse_Module"
    dictionary: new Map(), // key: "Term_Type_Module"
    xrefs: new Map(), // key: "Book_Chapter_Verse"
    
    // Limits (Simple LRU logic placeholder)
    MAX_SIZE: 50, 

    get: function(store, key) {
        if (this[store] && this[store].has(key)) {
            console.log(`[Cache Hit] ${store} -> ${key}`);
            return this[store].get(key);
        }
        return null;
    },

    set: function(store, key, data) {
        if (this[store]) {
            if (this[store].size >= this.MAX_SIZE) {
                // Remove first item (oldest)
                const firstKey = this[store].keys().next().value;
                this[store].delete(firstKey);
            }
            this[store].set(key, data);
        }
    },
    
    clear: function() {
        this.text.clear();
        this.commentary.clear();
        this.dictionary.clear();
        this.xrefs.clear();
    }
};
