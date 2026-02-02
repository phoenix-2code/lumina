import glob
import struct
import os

target = 7225 # H7225 (reshith)
target_hex = b'\x39\x1c' # Little endian 7225

print(f"Hunting for {target} (0x1C39) in KJV files...")

files = glob.glob('../KJV.bt*')
for fpath in files:
    try:
        with open(fpath, 'rb') as f:
            data = f.read(5000) # Read first 5KB
            
            # 1. Look for raw bytes
            idx = data.find(target_hex)
            if idx != -1:
                print(f"[MATCH] {fpath} at byte {idx}")
                
                # Show surrounding bytes
                start = max(0, idx-10)
                chunk = data[start:idx+10]
                print(f"   Context: {chunk.hex()}")
                
    except Exception as e:
        print(f"Error reading {fpath}: {e}")
