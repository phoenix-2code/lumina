import sqlite3
import json

def export_to_json(db_path, json_path):
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    c = conn.cursor()
    
    # Get all verses joined with book names
    c.execute('''
        SELECT b.name as book, v.chapter, v.verse, v.text 
        FROM verses v 
        JOIN books b ON v.book_id = b.id
        ORDER BY v.id
    ''')
    
    rows = c.fetchall()
    
    bible_data = []
    for row in rows:
        bible_data.append({
            "book": row['book'],
            "chapter": row['chapter'],
            "verse": row['verse'],
            "text": row['text']
        })
        
    with open(json_path, 'w') as f:
        json.dump(bible_data, f, indent=2)
        
    print(f"Exported {len(bible_data)} verses to {json_path}")

if __name__ == "__main__":
    export_to_json('bible_app.db', 'bible_dump.json')
