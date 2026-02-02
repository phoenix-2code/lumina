import sqlite3
import os

def parse_commentary(file_path, db_path, name):
    print(f"Parsing Commentary {name} from {file_path}...")
    try:
        with open(file_path, 'rb') as f:
            content = f.read()
    except: return

    parts = content.split(b'\x00')
    conn = sqlite3.connect(db_path)
    c = conn.cursor()
    
    # Drop old table to change schema
    c.execute(f"DROP TABLE IF EXISTS commentary_{name}")
    c.execute(f"CREATE TABLE commentary_{name} (verse_id INTEGER PRIMARY KEY, text TEXT)")
    
    batch = []
    for i in range(0, len(parts) - 1, 2):
        key_hex = parts[i].decode('cp1252', errors='ignore').strip()
        val = parts[i+1].decode('cp1252', errors='ignore').strip()
        if key_hex and val:
            try:
                key_int = int(key_hex, 16)
                batch.append((key_int, val))
            except ValueError:
                continue
            
    c.executemany(f"INSERT OR REPLACE INTO commentary_{name} (verse_id, text) VALUES (?, ?)", batch)
    conn.commit()
    conn.close()
    print(f"Finished {name}. Total sections: {len(batch)}")

if __name__ == "__main__":
    db = 'bible_app.db'
    parse_commentary('../MHC.ct4', db, 'mhc')
    parse_commentary('../Barnes.ct4', db, 'barnes')
    parse_commentary('../JFB.ct4', db, 'jfb')
    parse_commentary('../RWP.ct4', db, 'rwp')
    parse_commentary('../ACC.ct4', db, 'acc')
