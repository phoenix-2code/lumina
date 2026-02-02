import sqlite3
import os
import glob

SOURCE_DIR = '../'
DB_PATH = 'bible_app.db'

def import_dictionaries():
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    c.execute('PRAGMA synchronous = OFF')
    
    # Reset dictionaries table
    c.execute('DROP TABLE IF EXISTS dictionaries')
    c.execute('CREATE TABLE dictionaries (topic TEXT, definition TEXT, module TEXT)')
    c.execute('CREATE INDEX idx_dict_topic ON dictionaries (topic)')
    c.execute('CREATE INDEX idx_dict_mod ON dictionaries (module)')

    files = glob.glob(os.path.join(SOURCE_DIR, '*.dt4'))
    for file in files:
        name = os.path.basename(file).split('.')[0]
        print(f'Importing: {name}')
        try:
            with open(file, 'rb') as f:
                content = f.read()
                parts = content.split(b'\x00')
                
                batch = []
                # Simple iterator to handle variable chunking
                i = 0
                while i < len(parts):
                    # Potential Topic
                    key_bytes = parts[i]
                    
                    # Skip empty keys
                    if not key_bytes.strip(): 
                        i += 1
                        continue
                        
                    try:
                        key = key_bytes.decode('cp1252').strip()
                    except:
                        i += 1
                        continue

                    # Heuristic: Topics are usually short (< 100 chars) and have no newlines
                    if len(key) > 100 or '\r' in key or '\n' in key:
                        # This is likely a definition fragment or garbage, skip it
                        i += 1
                        continue
                        
                    # If we found a valid key, the NEXT chunk is likely the definition
                    if i + 1 < len(parts):
                        val_bytes = parts[i+1]
                        try:
                            val = val_bytes.decode('cp1252').strip()
                            if val:
                                batch.append((key, val, name.upper()))
                                i += 2 # Consumed Key and Value
                            else:
                                i += 1 # Value was empty, maybe key was garbage?
                        except:
                            i += 1
                    else:
                        break # End of file
                
                if batch:
                    c.executemany('INSERT INTO dictionaries (topic, definition, module) VALUES (?, ?, ?)', batch)
                    print(f'  -> Success: {len(batch)} entries')
                    
        except Exception as e:
            print(f'  -> FAILED: {e}')
    
    conn.commit()
    conn.close()

if __name__ == "__main__":
    import_dictionaries()
