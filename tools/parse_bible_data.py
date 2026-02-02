import sqlite3
import os

# Standard Bible Structure (Books and their Chapter counts)
# Note: This is a simplified map. For a full production app, we need the exact verse count per chapter to be 100% accurate 
# because some translations split verses differently. But for ASV/KJV, the standard KJV versification is usually used.
# I will use a standard flat list of verse counts for the first few books to demonstrate the parser logic.
# If I had the 'v11n' (versification) file, I'd use that. 
# For now, I will assume standard KJV versification for the ASV.

# List of books and their chapter lengths (number of verses per chapter)
# This data is widely available. I will include Genesis for the demo.
bible_structure = {
    "Genesis": [31, 25, 24, 26, 32, 22, 24, 22, 29, 32, 32, 20, 18, 24, 21, 16, 27, 33, 38, 18, 34, 24, 20, 67, 34, 35, 46, 22, 35, 43, 55, 32, 20, 31, 29, 43, 36, 30, 23, 23, 57, 38, 34, 34, 28, 34, 31, 22, 33, 26],
    "Exodus": [22, 25, 22, 31, 23, 30, 25, 32, 35, 29, 10, 51, 22, 31, 27, 36, 16, 27, 25, 26, 36, 31, 33, 18, 40, 37, 21, 43, 46, 38, 18, 35, 23, 35, 35, 38, 29, 31, 43, 38],
    # ... (I will need the full list for the full file, but let's start with Genesis/Exodus to verify)
}

def parse_bt4(file_path, db_path):
    print(f"Parsing {file_path}...")
    
    try:
        with open(file_path, 'rb') as f:
            content = f.read()
    except FileNotFoundError:
        print(f"File not found: {file_path}")
        return

    # Split by null byte
    raw_verses = content.split(b'\x00')
    
    # Remove empty last element if file ends with null
    if raw_verses[-1] == b'':
        raw_verses.pop()

    print(f"Found {len(raw_verses)} verses in the file.")
    
    # Connect to SQLite
    conn = sqlite3.connect(db_path)
    c = conn.cursor()
    
    # Create Tables
    c.execute('''CREATE TABLE IF NOT EXISTS books (id INTEGER PRIMARY KEY, name TEXT)''')
    c.execute('''CREATE TABLE IF NOT EXISTS verses (
                    id INTEGER PRIMARY KEY AUTOINCREMENT, 
                    book_id INTEGER, 
                    chapter INTEGER, 
                    verse INTEGER, 
                    text TEXT,
                    FOREIGN KEY(book_id) REFERENCES books(id)
                )''')
    
    # Clear existing data for a clean run
    c.execute("DELETE FROM books")
    c.execute("DELETE FROM verses")
    
    # Insert Books (Just Gen/Exodus for now to test mapping)
    book_map = {}
    current_book_id = 1
    for book_name in bible_structure.keys():
        c.execute("INSERT INTO books (id, name) VALUES (?, ?)", (current_book_id, book_name))
        book_map[book_name] = current_book_id
        current_book_id += 1

    conn.commit()

    # Iterate and Map
    verse_index = 0
    
    for book_name, chapters in bible_structure.items():
        book_id = book_map[book_name]
        for chapter_num, verse_count in enumerate(chapters, 1):
            for verse_num in range(1, verse_count + 1):
                if verse_index < len(raw_verses):
                    # Decode text (assuming cp1252 or utf-8, likely cp1252 for old Windows apps)
                    try:
                        text = raw_verses[verse_index].decode('cp1252').strip()
                    except UnicodeDecodeError:
                         text = raw_verses[verse_index].decode('latin1').strip()
                    
                    c.execute("INSERT INTO verses (book_id, chapter, verse, text) VALUES (?, ?, ?, ?)",
                              (book_id, chapter_num, verse_num, text))
                    verse_index += 1
                else:
                    break
    
    conn.commit()
    conn.close()
    print(f"Successfully parsed {verse_index} verses into {db_path}")

if __name__ == "__main__":
    parse_bt4('ASV.bt4', 'bible_app.db')
