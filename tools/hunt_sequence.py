import glob
import struct
import os

# Isaiah 1:1 Sequence
# 2377 (Vision), 3470 (Isaiah), 531 (Amoz)
target_seq = [2377, 3470, 531]

print(f"Hunting for sequence {target_seq} in all files...")

files = glob.glob('../*.*')
for fpath in files:
    if 'bible_app.db' in fpath: continue
    
    try:
        with open(fpath, 'rb') as f:
            data = f.read() # Read all (files are small enough)
            
            # Convert to list of shorts (2-byte ints)
            # Strongs numbers are usually 2 bytes
            count = len(data) // 2
            shorts = struct.unpack('<' + 'H' * count, data)
            
            # Scan for sequence
            for i in range(len(shorts) - 3):
                if shorts[i] == target_seq[0]:
                    # Found first number, check next
                    # Allow for some padding (zeros) between numbers
                    window = shorts[i:i+10]
                    
                    # Check if 3470 exists in the next few numbers
                    if target_seq[1] in window:
                        print(f"[MATCH] {fpath} at offset {i*2}")
                        print(f"   Context: {window}")
                        
    except Exception as e:
        pass
