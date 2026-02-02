import sqlite3
import os

# Adjust path based on where we run it
db_path = 'bible_app.db'
if not os.path.exists(db_path):
    print(f"Error: {db_path} not found in {os.getcwd()}")
    exit(1)

conn = sqlite3.connect(db_path)
c = conn.cursor()
# John 1:1 KJV with Strongs
john1_1 = 'In|G1722 the|G0 beginning|G746 was|G2258 the|G3588 Word|G3056 and|G2532 the|G3588 Word|G3056 was|G2258 with|G4314 God|G2316 and|G2532 the|G3588 Word|G3056 was|G2258 God|G2316'
# Book ID 43 = John
c.execute("UPDATE verses SET strongs = ? WHERE book_id=43 AND chapter=1 AND verse=1 AND version='KJV'", (john1_1,))
conn.commit()
conn.close()
print('John 1:1 NT Interlinear Injected Successfully.')