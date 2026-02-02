import sqlite3
import os

def setup_search_index(db_path):
    print(f"Setting up Full-Text Search on {db_path}...")
    conn = sqlite3.connect(db_path)
    c = conn.cursor()
    
    # 1. Enable FTS5
    # We create a virtual table 'verses_fts' that mirrors the text content
    c.execute("DROP TABLE IF EXISTS verses_fts")
    c.execute('''CREATE VIRTUAL TABLE verses_fts USING fts5(
                    text, 
                    book_id UNINDEXED, 
                    chapter UNINDEXED, 
                    verse UNINDEXED, 
                    version UNINDEXED,
                    tokenize="porter"
                )''')
    
    # 2. Populate FTS Index
    print("Indexing verses... this might take a moment.")
    c.execute('''INSERT INTO verses_fts (rowid, text, book_id, chapter, verse, version)
                 SELECT id, text, book_id, chapter, verse, version FROM verses''')
                 
    # 3. Optimize
    c.execute("INSERT INTO verses_fts(verses_fts) VALUES('optimize')")
    
    conn.commit()
    conn.close()
    print("Search Index Ready! 🚀")

if __name__ == "__main__":
    setup_search_index('bible_app.db')
