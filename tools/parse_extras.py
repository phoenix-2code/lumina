import sqlite3
import os
import re

def parse_lexicon(file_path, db_path, table_name, prefix=''):
    print(f"Parsing Lexicon {file_path} into {table_name}...")
    try:
        with open(file_path, 'rb') as f:
            content = f.read()
    except FileNotFoundError:
        print(f"File not found: {file_path}")
        return

    # Split by null byte
    parts = content.split(b'\x00')
    
    conn = sqlite3.connect(db_path)
    c = conn.cursor()
    
    c.execute(f'''CREATE TABLE IF NOT EXISTS {table_name} (
                    id TEXT PRIMARY KEY, 
                    definition TEXT
                )''')
    
    # Use a transaction
    c.execute("BEGIN")
    
    # Iterate in pairs: Key, Value
    count = 0
    # Sometimes the split leaves empty strings or headers. 
    # Based on probe: [0] '01', [1] 'def', [2] '02'...
    # So we iterate i, i+1
    
    for i in range(0, len(parts) - 1, 2):
        key_bytes = parts[i]
        val_bytes = parts[i+1]
        
        # Decode
        try:
            key = key_bytes.decode('cp1252').strip()
            # If key is numeric, add prefix (H or G) and pad
            if key.isdigit():
                # Strong's numbers usually don't have leading zeros in DBs, 
                # but the file has '01'. Let's strip '0' and add prefix.
                key_num = str(int(key)) 
                key = f"{prefix}{key_num}"
            
            val = val_bytes.decode('cp1252').strip()
            # Clean up formatting codes if any (basic cleanup)
            val = val.replace('\r\n', '\n')
            
            if key and val:
                c.execute(f"INSERT OR REPLACE INTO {table_name} (id, definition) VALUES (?, ?)", (key, val))
                count += 1
                
        except UnicodeDecodeError:
            continue # Skip bad chunks
            
    conn.commit()
    conn.close()
    print(f"Parsed {count} entries into {table_name}.")

def parse_dictionary(file_path, db_path, table_name):
    print(f"Parsing Dictionary {file_path} into {table_name}...")
    try:
        with open(file_path, 'rb') as f:
            content = f.read()
    except FileNotFoundError:
        print(f"File not found: {file_path}")
        return

    parts = content.split(b'\x00')
    conn = sqlite3.connect(db_path)
    c = conn.cursor()
    
    c.execute(f'''CREATE TABLE IF NOT EXISTS {table_name} (
                    topic TEXT PRIMARY KEY, 
                    definition TEXT
                )''')
    
    c.execute("BEGIN")
    count = 0
    
    # Based on Easton probe: [0] 'A', [1] 'Alpha def...', [2] 'Aaron', [3] 'Aaron def...'
    # So it is Key, Value pairs.
    
    for i in range(0, len(parts) - 1, 2):
        topic_bytes = parts[i]
        def_bytes = parts[i+1]
        
        try:
            topic = topic_bytes.decode('cp1252').strip()
            definition = def_bytes.decode('cp1252').strip()
            
            if topic and definition:
                c.execute(f"INSERT OR REPLACE INTO {table_name} (topic, definition) VALUES (?, ?)", (topic, definition))
                count += 1
        except:
            continue

    conn.commit()
    conn.close()
    print(f"Parsed {count} entries into {table_name}.")

if __name__ == "__main__":
    db = 'bible_app.db'
    parse_lexicon('StrHeb.hx4', db, 'lexicon_hebrew', 'H')
    parse_lexicon('StrGrk.gx4', db, 'lexicon_greek', 'G')
    parse_dictionary('Easton.dt4', db, 'dictionary_easton')
