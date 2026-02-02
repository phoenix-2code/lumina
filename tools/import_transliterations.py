import sqlite3
import os

# --- CONFIG ---
DB_PATH = 'bible_app.db'
SD2 = '../Strongs.sd2'

def extract_transliterations():
    if not os.path.exists(SD2):
        print("Strongs.sd2 not found.")
        return
    
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    
    # 1. Update schema
    try:
        c.execute("ALTER TABLE lexicons ADD COLUMN transliteration TEXT")
    except: pass
    
    print("Extracting Transliterations from Strongs.sd2 (Sequential Mode)...")
    
    try:
        with open(SD2, 'rb') as f:
            content = f.read()
            parts = content.split(b'\x00')
            
        print(f"File split into {len(parts)} segments.")
        
        # Hebrew: H1 to H8675
        # Starts at index 3, every 3rd item
        h_count = 0
        for i in range(1, 8676):
            idx = 3 + (i-1) * 3
            if idx < len(parts):
                word = parts[idx].decode('cp1252', errors='ignore').strip()
                if word:
                    c.execute("UPDATE lexicons SET transliteration = ? WHERE id = ?", (word, f"H{i}"))
                    h_count += 1
        
        print(f"Imported {h_count} Hebrew transliterations.")
        
        # Greek: G1 to G5625
        # Starts after Hebrew (8675 * 3 + 3 = 26028)
        g_count = 0
        offset = 26028
        for i in range(1, 5626):
            idx = offset + (i-1) * 3
            if idx < len(parts):
                word = parts[idx].decode('cp1252', errors='ignore').strip()
                if word:
                    c.execute("UPDATE lexicons SET transliteration = ? WHERE id = ?", (word, f"G{i}"))
                    g_count += 1
                    
        print(f"Imported {g_count} Greek transliterations.")
        
    except Exception as e:
        print(f"Error: {e}")
        
    conn.commit()
    conn.close()
    print("Sequential Import Complete.")

if __name__ == "__main__":
    extract_transliterations()