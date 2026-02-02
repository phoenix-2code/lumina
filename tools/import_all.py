import sqlite3
import os
import glob

# --- CONFIG ---
DB_PATH = 'bible_app.db'
SOURCE_DIR = '../' # Parent directory where .ct4, .bt4, .dt4 files are

def scan_modules():
    modules = {
        'bibles': [],
        'commentaries': [],
        'dictionaries': [],
        'lexicons': [],
        'xrefs': []
    }
    
    # Scan directory
    for file in glob.glob(os.path.join(SOURCE_DIR, '*.*')):
        fname = os.path.basename(file)
        name, ext = os.path.splitext(fname)
        ext = ext.lower()
        
        if ext == '.bt4':
            modules['bibles'].append(name)
        elif ext == '.ct4':
            modules['commentaries'].append(name)
        elif ext == '.dt4':
            modules['dictionaries'].append(name)
        elif ext in ['.hx4', '.gx4']:
            modules['lexicons'].append(name)
        elif ext == '.xr4':
            modules['xrefs'].append(name)
            
    print(f"Found: {len(modules['bibles'])} Bibles, {len(modules['commentaries'])} Commentaries, {len(modules['dictionaries'])} Dictionaries.")
    return modules

def import_commentaries(mod_list, c):
    for name in mod_list:
        table_name = f"commentary_{name.lower()}"
        print(f"Importing Commentary: {name} -> {table_name}")
        
        c.execute(f"CREATE TABLE IF NOT EXISTS {table_name} (verse_id INTEGER PRIMARY KEY, text TEXT)")
        
        try:
            with open(os.path.join(SOURCE_DIR, f"{name}.ct4"), 'rb') as f:
                content = f.read()
                parts = content.split(b'\x00')
                
                batch = []
                for i in range(0, len(parts) - 1, 2):
                    try:
                        k_hex = parts[i].decode('cp1252', errors='ignore').strip()
                        v = parts[i+1].decode('cp1252', errors='ignore').strip()
                        if k_hex and v:
                            k_int = int(k_hex, 16)
                            batch.append((k_int, v))
                    except: continue
                
                if batch:
                    c.executemany(f"INSERT OR REPLACE INTO {table_name} (verse_id, text) VALUES (?, ?)", batch)
                    print(f"  -> {len(batch)} entries.")
        except Exception as e:
            print(f"  -> Failed: {e}")

def import_bibles(mod_list, c):
    # Just ensure we have KJV and ASV for now, logic is complex for unknown formats
    # Use existing parser logic if we want to add more later
    pass 

def build_availability_index(mod_list, c):
    print("Building Verse Module Index...")
    c.execute("DROP TABLE IF EXISTS verse_modules")
    c.execute("CREATE TABLE verse_modules (verse_id INTEGER, modules TEXT, PRIMARY KEY (verse_id))")
    
    # We need to aggregate which modules have content for which verse
    # This is heavy, so we do it in memory first
    verse_map = {} # {verse_id: ['MHC', 'ACC', ...]}
    
    for name in mod_list:
        table_name = f"commentary_{name.lower()}"
        # Check if table exists
        try:
            c.execute(f"SELECT verse_id FROM {table_name}")
            ids = c.fetchall()
            mod_code = name.upper()
            
            for (vid,) in ids:
                if vid not in verse_map:
                    verse_map[vid] = []
                verse_map[vid].append(mod_code)
        except: continue
        
    print(f"Indexing {len(verse_map)} verses...")
    
    batch = []
    for vid, mods in verse_map.items():
        batch.append((vid, ','.join(sorted(mods))))
        
    c.executemany("INSERT INTO verse_modules (verse_id, modules) VALUES (?, ?)", batch)
    print("Index Complete.")

def main():
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    c.execute("PRAGMA synchronous = OFF")
    
    mods = scan_modules()
    
    # 1. Import all Commentaries
    import_commentaries(mods['commentaries'], c)
    
    # 2. Build the "Quick Link" Index
    build_availability_index(mods['commentaries'], c)
    
    conn.commit()
    conn.close()
    print("Master Import Complete. 🚀")

if __name__ == "__main__":
    main()
