import sqlite3
import json
import re

# --- CONFIG ---
DB_PATH = 'bible_app.db'
JSON_FILE = 'kjv_strongs.json'

def parse_text(text):
    # 1. Force remove morphology tags: {(G5656)}, {(H8804)}, etc.
    # Pattern: curly brace containing parentheses
    text = re.sub(r'\{\([^}]+\)\}', '', text)
    
    # 2. Convert standard tags: word{G123} -> word|G123
    # Use regex to find a word followed immediately by a tag
    text = re.sub(r'([^\s{}]+)\{([HG]\d+)\}', r'\1|\2', text)
    
    # 3. Handle standalone tags (untranslated words): {G123} -> [?]|G123
    text = re.sub(r'\{([HG]\d+)\}', r'[?]|\1', text)
    
    # 4. Clean up remaining braces (just in case)
    text = text.replace('{', '').replace('}', '')
    
    # 5. Ensure words without tags get |H0
    parts = text.split()
    final = []
    for p in parts:
        if '|' in p:
            final.append(p)
        else:
            final.append(f"{p}|H0")
            
    return ' '.join(final)

def import_interlinear():
    print("Loading JSON...")
    with open(JSON_FILE, 'r', encoding='utf-8') as f:
        data = json.load(f)
        
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    c.execute("PRAGMA synchronous = OFF")
    
    print("Updating Database (31,102 verses)...")
    
    updates = []
    for v in data['verses']:
        # Map Book ID (Source uses 1=Gen, DB uses 1=Gen)
        book_id = v['book']
        chapter = v['chapter']
        verse = v['verse']
        raw_text = v['text']
        
        strongs_text = parse_text(raw_text)
        
        updates.append((strongs_text, book_id, chapter, verse))
        
        if len(updates) >= 5000:
            c.executemany("UPDATE verses SET strongs = ? WHERE book_id = ? AND chapter = ? AND verse = ? AND version = 'KJV'", updates)
            print(f"Updated batch... (Verse {book_id}:{chapter}:{verse})")
            updates = []
            
    if updates:
        c.executemany("UPDATE verses SET strongs = ? WHERE book_id = ? AND chapter = ? AND verse = ? AND version = 'KJV'", updates)
        
    conn.commit()
    conn.close()
    print("Full Interlinear Import Complete! 🌍")

if __name__ == "__main__":
    import_interlinear()
