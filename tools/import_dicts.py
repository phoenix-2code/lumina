import sqlite3
import os
import glob

# --- CONFIG ---
DB_PATH = 'bible_app.db'
SOURCE_DIR = '../' 

def scan_modules():
    modules = {
        'bibles': [],
        'commentaries': [],
        'dictionaries': [],
        'lexicons': [],
        'xrefs': []
    }
    for file in glob.glob(os.path.join(SOURCE_DIR, '*.*')):
        fname = os.path.basename(file)
        name, ext = os.path.splitext(fname)
        ext = ext.lower()
        if ext == '.bt4': modules['bibles'].append(name)
        elif ext == '.ct4': modules['commentaries'].append(name)
        elif ext == '.dt4': modules['dictionaries'].append(name)
        elif ext in ['.hx4', '.gx4']: modules['lexicons'].append(name)
        elif ext == '.xr4': modules['xrefs'].append(name)
    return modules

def import_dictionaries(mod_list, c):
    # Update table to include module
    c.execute("DROP TABLE IF EXISTS dictionaries")
    c.execute("CREATE TABLE dictionaries (topic TEXT, definition TEXT, module TEXT)")
    c.execute("CREATE INDEX idx_dict_topic ON dictionaries (topic)")
    c.execute("CREATE INDEX idx_dict_mod ON dictionaries (module)")

    for name in mod_list:
        print(f"Importing Dictionary: {name}")
        try:
            with open(os.path.join(SOURCE_DIR, f"{name}.dt4"), 'rb') as f:
                content = f.read()
                parts = content.split(b'\x00')
                batch = []
                for i in range(0, len(parts) - 1, 2):
                    try:
                        k = parts[i].decode('cp1252', errors='ignore').strip()
                        v = parts[i+1].decode('cp1252', errors='ignore').strip()
                        if k and v:
                            batch.append((k, v, name.upper()))
                    except: continue
                if batch:
                    c.executemany("INSERT INTO dictionaries (topic, definition, module) VALUES (?, ?, ?)", batch)
                    print(f"  -> {len(batch)} entries.")
        except Exception as e:
            print(f"  -> Failed: {e}")

def main():
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    c.execute("PRAGMA synchronous = OFF")
    
    mods = scan_modules()
    
    # Re-import dictionaries
    import_dictionaries(mods['dictionaries'], c)
    
    conn.commit()
    conn.close()
    print("Dictionary Import Complete. 📚")

if __name__ == "__main__":
    main()
