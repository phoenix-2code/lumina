import sqlite3
import os
import re

def parse_cross_refs(file_path, db_path):
    print(f"Parsing Cross-References from {file_path}...")
    try:
        with open(file_path, 'rb') as f:
            content = f.read()
    except Exception as e:
        print(f"Error: {e}")
        return

    # Split by null byte
    parts = content.split(b'\x00')
    
    conn = sqlite3.connect(db_path)
    c = conn.cursor()
    
    c.execute("DROP TABLE IF EXISTS cross_references")
    c.execute('''CREATE TABLE cross_references (
                    from_id INTEGER, 
                    to_id INTEGER
                )''')
    c.execute("CREATE INDEX idx_xref_from ON cross_references (from_id)")
    
    current_key = None
    batch = []
    
    for p_bytes in parts:
        if not p_bytes: continue
        
        # Check if it starts with \x03 (it's a value)
        if p_bytes.startswith(b'\x03'):
            if current_key is None: continue
            
            # Value is like \x0336C0\x03\x033825\x03...
            # Extract hex strings between \x03
            hex_refs = re.findall(r'\x03([0-9A-F\-]+)\x03', p_bytes.decode('cp1252', errors='ignore'))
            
            for hr in hex_refs:
                if '-' in hr:
                    # Range: 3BEA-3BEB
                    try:
                        start_hex, end_hex = hr.split('-')
                        start_id = int(start_hex, 16)
                        end_id = int(end_hex, 16)
                        for tid in range(start_id, end_id + 1):
                            batch.append((current_key, tid))
                    except: continue
                else:
                    try:
                        tid = int(hr, 16)
                        batch.append((current_key, tid))
                    except: continue
        else:
            # It's a Key (Verse ID in hex)
            try:
                current_key = int(p_bytes.decode('cp1252', errors='ignore').strip(), 16)
            except:
                current_key = None
                
        # Commit in chunks
        if len(batch) > 10000:
            c.executemany("INSERT INTO cross_references (from_id, to_id) VALUES (?, ?)", batch)
            batch = []

    if batch:
        c.executemany("INSERT INTO cross_references (from_id, to_id) VALUES (?, ?)", batch)
        
    conn.commit()
    conn.close()
    print("Cross-references parsed successfully.")

if __name__ == "__main__":
    parse_cross_refs('../bcdxrefs.xr4', 'bible_app.db')
