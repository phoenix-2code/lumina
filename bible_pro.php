<?php
try {
    $db = new PDO('sqlite:bible_app.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// --- Helper Functions ---
function searchBible($query, $version = 'KJV') {
    global $db;
    // Use FTS5 match query
    // Highlighting is done via the 'highlight' function in FTS5
    $stmt = $db->prepare("
        SELECT rowid, highlight(verses_fts, 0, '<b>', '</b>') as text, book_id, chapter, verse 
        FROM verses_fts 
        WHERE verses_fts MATCH :query AND version = :version 
        ORDER BY rank 
        LIMIT 50
    ");
    // FTS query syntax: wrap phrases in quotes if needed
    // Simple sanitization: remove non-alphanumeric chars except spaces
    $clean_query = preg_replace('/[^a-zA-Z0-9 ]/', '', $query);
    if (!$clean_query) return [];
    
    // Use NEAR operator logic or simple phrase match depending on need. 
    // Here we use standard match which treats spaces as AND by default in recent sqlite, 
    // or we can wrap in quotes for exact phrase.
    // Let's assume user wants simple keyword search for now.
    $stmt->execute([':query' => $clean_query, ':version' => $version]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Enhance results with book names
    foreach ($results as &$r) {
        $stmt_b = $db->prepare("SELECT name FROM books WHERE id = ?");
        $stmt_b->execute([$r['book_id']]);
        $r['book_name'] = $stmt_b->fetchColumn();
    }
    return $results;
}

function getGlobalVerseID($book, $chapter, $verse) {
    // This structure matches our robust_parser.py
    $bible_structure = [
        "Genesis" => [31, 25, 24, 26, 32, 22, 24, 22, 29, 32, 32, 20, 18, 24, 21, 16, 27, 33, 38, 18, 34, 24, 20, 67, 34, 35, 46, 22, 35, 43, 55, 32, 20, 31, 29, 43, 36, 30, 23, 23, 57, 38, 34, 34, 28, 34, 31, 22, 33, 26],
        "Exodus" => [22, 25, 22, 31, 23, 30, 25, 32, 35, 29, 10, 51, 22, 31, 27, 36, 16, 27, 25, 26, 36, 31, 33, 18, 40, 37, 21, 43, 46, 38, 18, 35, 23, 35, 35, 38, 29, 31, 43, 38],
        "Leviticus" => [17, 16, 17, 35, 19, 30, 38, 36, 24, 20, 47, 8, 59, 57, 33, 34, 16, 30, 37, 27, 24, 33, 44, 23, 55, 46, 34],
        "Numbers" => [54, 34, 51, 49, 31, 27, 89, 26, 23, 36, 35, 16, 33, 45, 41, 50, 13, 32, 22, 29, 35, 41, 30, 25, 18, 65, 23, 31, 40, 16, 54, 42, 56, 29, 34, 13],
        "Deuteronomy" => [46, 37, 29, 49, 33, 25, 26, 20, 29, 22, 32, 32, 18, 29, 23, 22, 20, 22, 21, 20, 23, 30, 25, 22, 19, 19, 26, 68, 29, 20, 30, 52, 29, 12],
        "Joshua" => [18, 24, 17, 24, 15, 27, 26, 35, 27, 43, 23, 24, 33, 15, 63, 10, 18, 28, 51, 9, 45, 34, 16, 33],
        "Judges" => [36, 23, 31, 24, 31, 40, 25, 35, 57, 18, 40, 15, 25, 20, 20, 31, 13, 31, 30, 48, 25],
        "Ruth" => [22, 23, 18, 22],
        "1 Samuel" => [28, 36, 21, 22, 12, 21, 17, 22, 27, 27, 15, 25, 23, 52, 35, 23, 58, 30, 24, 42, 15, 23, 29, 22, 44, 25, 12, 25, 11, 31, 13],
        "2 Samuel" => [27, 32, 39, 12, 25, 23, 29, 18, 13, 19, 27, 31, 39, 33, 37, 23, 29, 33, 43, 26, 22, 51, 39, 25],
        "1 Kings" => [53, 46, 28, 34, 18, 38, 51, 66, 28, 29, 43, 33, 34, 31, 34, 34, 24, 46, 21, 43, 29, 53],
        "2 Kings" => [18, 25, 27, 44, 27, 33, 20, 29, 37, 36, 21, 21, 25, 29, 38, 20, 41, 37, 37, 21, 26, 20, 37, 20, 30],
        "1 Chronicles" => [54, 55, 24, 43, 26, 81, 40, 40, 44, 14, 47, 40, 14, 17, 29, 43, 27, 17, 19, 8, 30, 19, 32, 31, 31, 32, 34, 21, 30],
        "2 Chronicles" => [17, 18, 17, 22, 14, 42, 22, 18, 31, 19, 23, 16, 22, 15, 19, 14, 19, 34, 11, 37, 20, 12, 21, 27, 28, 23, 9, 27, 36, 27, 21, 33, 25, 33, 25, 33, 27, 23],
        "Ezra" => [11, 70, 13, 24, 17, 22, 28, 36, 15, 44],
        "Nehemiah" => [11, 20, 32, 23, 19, 19, 73, 18, 38, 39, 36, 47, 31],
        "Esther" => [22, 23, 15, 17, 14, 14, 10, 17, 32, 3],
        "Job" => [22, 13, 26, 21, 27, 30, 21, 22, 35, 22, 20, 25, 28, 22, 35, 22, 16, 21, 29, 29, 34, 30, 17, 25, 6, 14, 23, 28, 25, 31, 40, 22, 33, 37, 16, 33, 24, 41, 30, 24, 34, 17],
        "Psalms" => [6, 12, 8, 8, 12, 10, 17, 9, 20, 18, 7, 8, 6, 7, 5, 11, 15, 50, 14, 9, 13, 31, 6, 10, 22, 12, 14, 9, 11, 12, 24, 11, 22, 22, 28, 12, 40, 22, 13, 17, 13, 11, 5, 26, 17, 11, 9, 14, 20, 23, 19, 9, 6, 7, 23, 13, 11, 11, 17, 12, 8, 12, 11, 10, 13, 20, 7, 35, 36, 5, 24, 20, 28, 23, 10, 12, 20, 72, 13, 19, 16, 8, 18, 12, 13, 17, 7, 18, 52, 17, 16, 15, 5, 23, 11, 13, 12, 9, 9, 5, 8, 28, 22, 35, 45, 48, 43, 13, 31, 7, 10, 10, 9, 8, 18, 19, 2, 29, 176, 7, 8, 9, 4, 8, 5, 6, 5, 6, 8, 8, 3, 18, 3, 3, 21, 26, 9, 8, 24, 13, 10, 7, 12, 15, 21, 10, 20, 14, 9, 6],
        "Proverbs" => [33, 22, 35, 27, 23, 35, 27, 36, 18, 32, 31, 28, 25, 35, 33, 33, 28, 24, 29, 30, 31, 29, 35, 34, 28, 28, 27, 28, 27, 33, 31],
        "Ecclesiastes" => [18, 26, 22, 16, 20, 12, 29, 17, 18, 20, 10, 14],
        "Song of Solomon" => [17, 17, 11, 16, 16, 13, 13, 14],
        "Isaiah" => [31, 22, 26, 6, 30, 13, 25, 22, 21, 34, 16, 6, 22, 32, 9, 14, 14, 7, 25, 6, 17, 25, 18, 23, 12, 21, 13, 29, 24, 33, 9, 20, 24, 17, 10, 22, 38, 22, 8, 31, 29, 25, 28, 28, 25, 13, 15, 22, 26, 11, 23, 15, 12, 17, 13, 12, 21, 14, 21, 22, 11, 12, 19, 12, 25, 24],
        "Jeremiah" => [19, 37, 25, 31, 31, 30, 34, 22, 26, 25, 23, 17, 27, 22, 21, 21, 27, 23, 15, 18, 14, 30, 40, 10, 38, 24, 22, 17, 32, 24, 40, 44, 26, 22, 19, 32, 21, 28, 18, 16, 18, 22, 13, 30, 5, 28, 7, 47, 39, 46, 64, 34],
        "Lamentations" => [22, 22, 66, 22, 22],
        "Ezekiel" => [28, 10, 27, 17, 17, 14, 27, 18, 11, 22, 25, 28, 23, 23, 8, 63, 24, 32, 14, 49, 32, 31, 49, 27, 17, 21, 36, 26, 21, 26, 18, 32, 33, 31, 15, 38, 28, 23, 29, 49, 26, 20, 27, 31, 25, 24, 23, 35],
        "Daniel" => [21, 49, 30, 37, 31, 28, 28, 27, 27, 21, 45, 13],
        "Hosea" => [11, 23, 5, 19, 15, 11, 16, 14, 17, 15, 12, 14, 16, 9],
        "Joel" => [20, 32, 21],
        "Amos" => [15, 16, 15, 13, 27, 14, 17, 14, 15],
        "Obadiah" => [21],
        "Jonah" => [17, 10, 10, 11],
        "Micah" => [16, 13, 12, 13, 15, 16, 20],
        "Nahum" => [15, 13, 19],
        "Habakkuk" => [17, 20, 19],
        "Zephaniah" => [18, 15, 20],
        "Haggai" => [15, 23],
        "Zechariah" => [21, 13, 10, 14, 11, 15, 14, 23, 17, 12, 17, 14, 9, 21],
        "Malachi" => [14, 17, 18, 6],
        "Matthew" => [25, 23, 17, 25, 48, 34, 29, 34, 38, 42, 30, 50, 58, 36, 39, 28, 27, 35, 30, 34, 46, 46, 39, 51, 46, 75, 66, 20],
        "Mark" => [45, 28, 35, 41, 43, 56, 37, 38, 50, 52, 33, 44, 37, 72, 47, 20],
        "Luke" => [80, 52, 38, 44, 39, 49, 50, 56, 62, 42, 54, 59, 35, 35, 32, 31, 37, 43, 48, 47, 38, 71, 56, 53],
        "John" => [51, 25, 36, 54, 47, 71, 53, 59, 41, 42, 57, 50, 38, 31, 27, 33, 26, 40, 42, 31, 25],
        "Acts" => [26, 47, 26, 37, 42, 15, 60, 40, 43, 48, 30, 25, 52, 28, 41, 40, 34, 28, 41, 38, 40, 30, 35, 27, 27, 32, 44, 31],
        "Romans" => [32, 29, 31, 25, 21, 23, 25, 39, 33, 21, 36, 21, 14, 23, 33, 27],
        "1 Corinthians" => [31, 16, 23, 21, 13, 20, 40, 13, 27, 33, 34, 31, 13, 40, 58, 24],
        "2 Corinthians" => [24, 17, 18, 18, 21, 18, 16, 24, 15, 18, 33, 21, 14],
        "Galatians" => [24, 21, 29, 31, 26, 18],
        "Ephesians" => [23, 22, 21, 32, 33, 24],
        "Philippians" => [30, 30, 21, 23],
        "Colossians" => [29, 23, 25, 18],
        "1 Thessalonians" => [10, 20, 13, 18, 28],
        "2 Thessalonians" => [12, 17, 18],
        "1 Timothy" => [20, 15, 16, 16, 25, 21],
        "2 Timothy" => [18, 26, 17, 22],
        "Titus" => [16, 15, 15],
        "Philemon" => [25],
        "Hebrews" => [14, 18, 19, 16, 14, 20, 28, 13, 28, 39, 40, 29, 25],
        "James" => [27, 26, 18, 17, 20],
        "1 Peter" => [25, 25, 22, 19, 14],
        "2 Peter" => [21, 22, 18],
        "1 John" => [10, 29, 24, 21, 21],
        "2 John" => [13],
        "3 John" => [14],
        "Jude" => [25],
        "Revelation" => [20, 29, 22, 11, 14, 17, 17, 13, 21, 11, 19, 17, 18, 20, 8, 21, 18, 24, 21, 15, 27, 21]
    ];
    $vid = 0;
    foreach ($bible_structure as $b_name => $chapters) {
        if ($b_name == $book) {
            foreach ($chapters as $c_idx => $v_count) {
                $c_num = $c_idx + 1;
                if ($c_num == $chapter) {
                    return $vid + $verse;
                }
                $vid += $v_count;
            }
        } else {
            $vid += array_sum($chapters);
        }
    }
    return $vid;
}

function getVerseRefFromID($vid) {
    // Inverse of getGlobalVerseID
    $bible_structure = [
        "Genesis" => [31, 25, 24, 26, 32, 22, 24, 22, 29, 32, 32, 20, 18, 24, 21, 16, 27, 33, 38, 18, 34, 24, 20, 67, 34, 35, 46, 22, 35, 43, 55, 32, 20, 31, 29, 43, 36, 30, 23, 23, 57, 38, 34, 34, 28, 34, 31, 22, 33, 26],
        "Exodus" => [22, 25, 22, 31, 23, 30, 25, 32, 35, 29, 10, 51, 22, 31, 27, 36, 16, 27, 25, 26, 36, 31, 33, 18, 40, 37, 21, 43, 46, 38, 18, 35, 23, 35, 35, 38, 29, 31, 43, 38],
        "Leviticus" => [17, 16, 17, 35, 19, 30, 38, 36, 24, 20, 47, 8, 59, 57, 33, 34, 16, 30, 37, 27, 24, 33, 44, 23, 55, 46, 34],
        "Numbers" => [54, 34, 51, 49, 31, 27, 89, 26, 23, 36, 35, 16, 33, 45, 41, 50, 13, 32, 22, 29, 35, 41, 30, 25, 18, 65, 23, 31, 40, 16, 54, 42, 56, 29, 34, 13],
        "Deuteronomy" => [46, 37, 29, 49, 33, 25, 26, 20, 29, 22, 32, 32, 18, 29, 23, 22, 20, 22, 21, 20, 23, 30, 25, 22, 19, 19, 26, 68, 29, 20, 30, 52, 29, 12],
        "Joshua" => [18, 24, 17, 24, 15, 27, 26, 35, 27, 43, 23, 24, 33, 15, 63, 10, 18, 28, 51, 9, 45, 34, 16, 33],
        "Judges" => [36, 23, 31, 24, 31, 40, 25, 35, 57, 18, 40, 15, 25, 20, 20, 31, 13, 31, 30, 48, 25],
        "Ruth" => [22, 23, 18, 22],
        "1 Samuel" => [28, 36, 21, 22, 12, 21, 17, 22, 27, 27, 15, 25, 23, 52, 35, 23, 58, 30, 24, 42, 15, 23, 29, 22, 44, 25, 12, 25, 11, 31, 13],
        "2 Samuel" => [27, 32, 39, 12, 25, 23, 29, 18, 13, 19, 27, 31, 39, 33, 37, 23, 29, 33, 43, 26, 22, 51, 39, 25],
        "1 Kings" => [53, 46, 28, 34, 18, 38, 51, 66, 28, 29, 43, 33, 34, 31, 34, 34, 24, 46, 21, 43, 29, 53],
        "2 Kings" => [18, 25, 27, 44, 27, 33, 20, 29, 37, 36, 21, 21, 25, 29, 38, 20, 41, 37, 37, 21, 26, 20, 37, 20, 30],
        "1 Chronicles" => [54, 55, 24, 43, 26, 81, 40, 40, 44, 14, 47, 40, 14, 17, 29, 43, 27, 17, 19, 8, 30, 19, 32, 31, 31, 32, 34, 21, 30],
        "2 Chronicles" => [17, 18, 17, 22, 14, 42, 22, 18, 31, 19, 23, 16, 22, 15, 19, 14, 19, 34, 11, 37, 20, 12, 21, 27, 28, 23, 9, 27, 36, 27, 21, 33, 25, 33, 25, 33, 27, 23],
        "Ezra" => [11, 70, 13, 24, 17, 22, 28, 36, 15, 44],
        "Nehemiah" => [11, 20, 32, 23, 19, 19, 73, 18, 38, 39, 36, 47, 31],
        "Esther" => [22, 23, 15, 17, 14, 14, 10, 17, 32, 3],
        "Job" => [22, 13, 26, 21, 27, 30, 21, 22, 35, 22, 20, 25, 28, 22, 35, 22, 16, 21, 29, 29, 34, 30, 17, 25, 6, 14, 23, 28, 25, 31, 40, 22, 33, 37, 16, 33, 24, 41, 30, 24, 34, 17],
        "Psalms" => [6, 12, 8, 8, 12, 10, 17, 9, 20, 18, 7, 8, 6, 7, 5, 11, 15, 50, 14, 9, 13, 31, 6, 10, 22, 12, 14, 9, 11, 12, 24, 11, 22, 22, 28, 12, 40, 22, 13, 17, 13, 11, 5, 26, 17, 11, 9, 14, 20, 23, 19, 9, 6, 7, 23, 13, 11, 11, 17, 12, 8, 12, 11, 10, 13, 20, 7, 35, 36, 5, 24, 20, 28, 23, 10, 12, 20, 72, 13, 19, 16, 8, 18, 12, 13, 17, 7, 18, 52, 17, 16, 15, 5, 23, 11, 13, 12, 9, 9, 5, 8, 28, 22, 35, 45, 48, 43, 13, 31, 7, 10, 10, 9, 8, 18, 19, 2, 29, 176, 7, 8, 9, 4, 8, 5, 6, 5, 6, 8, 8, 3, 18, 3, 3, 21, 26, 9, 8, 24, 13, 10, 7, 12, 15, 21, 10, 20, 14, 9, 6],
        "Proverbs" => [33, 22, 35, 27, 23, 35, 27, 36, 18, 32, 31, 28, 25, 35, 33, 33, 28, 24, 29, 30, 31, 29, 35, 34, 28, 28, 27, 28, 27, 33, 31],
        "Ecclesiastes" => [18, 26, 22, 16, 20, 12, 29, 17, 18, 20, 10, 14],
        "Song of Solomon" => [17, 17, 11, 16, 16, 13, 13, 14],
        "Isaiah" => [31, 22, 26, 6, 30, 13, 25, 22, 21, 34, 16, 6, 22, 32, 9, 14, 14, 7, 25, 6, 17, 25, 18, 23, 12, 21, 13, 29, 24, 33, 9, 20, 24, 17, 10, 22, 38, 22, 8, 31, 29, 25, 28, 28, 25, 13, 15, 22, 26, 11, 23, 15, 12, 17, 13, 12, 21, 14, 21, 22, 11, 12, 19, 12, 25, 24],
        "Jeremiah" => [19, 37, 25, 31, 31, 30, 34, 22, 26, 25, 23, 17, 27, 22, 21, 21, 27, 23, 15, 18, 14, 30, 40, 10, 38, 24, 22, 17, 32, 24, 40, 44, 26, 22, 19, 32, 21, 28, 18, 16, 18, 22, 13, 30, 5, 28, 7, 47, 39, 46, 64, 34],
        "Lamentations" => [22, 22, 66, 22, 22],
        "Ezekiel" => [28, 10, 27, 17, 17, 14, 27, 18, 11, 22, 25, 28, 23, 23, 8, 63, 24, 32, 14, 49, 32, 31, 49, 27, 17, 21, 36, 26, 21, 26, 18, 32, 33, 31, 15, 38, 28, 23, 29, 49, 26, 20, 27, 31, 25, 24, 23, 35],
        "Daniel" => [21, 49, 30, 37, 31, 28, 28, 27, 27, 21, 45, 13],
        "Hosea" => [11, 23, 5, 19, 15, 11, 16, 14, 17, 15, 12, 14, 16, 9],
        "Joel" => [20, 32, 21],
        "Amos" => [15, 16, 15, 13, 27, 14, 17, 14, 15],
        "Obadiah" => [21],
        "Jonah" => [17, 10, 10, 11],
        "Micah" => [16, 13, 12, 13, 15, 16, 20],
        "Nahum" => [15, 13, 19],
        "Habakkuk" => [17, 20, 19],
        "Zephaniah" => [18, 15, 20],
        "Haggai" => [15, 23],
        "Zechariah" => [21, 13, 10, 14, 11, 15, 14, 23, 17, 12, 17, 14, 9, 21],
        "Malachi" => [14, 17, 18, 6],
        "Matthew" => [25, 23, 17, 25, 48, 34, 29, 34, 38, 42, 30, 50, 58, 36, 39, 28, 27, 35, 30, 34, 46, 46, 39, 51, 46, 75, 66, 20],
        "Mark" => [45, 28, 35, 41, 43, 56, 37, 38, 50, 52, 33, 44, 37, 72, 47, 20],
        "Luke" => [80, 52, 38, 44, 39, 49, 50, 56, 62, 42, 54, 59, 35, 35, 32, 31, 37, 43, 48, 47, 38, 71, 56, 53],
        "John" => [51, 25, 36, 54, 47, 71, 53, 59, 41, 42, 57, 50, 38, 31, 27, 33, 26, 40, 42, 31, 25],
        "Acts" => [26, 47, 26, 37, 42, 15, 60, 40, 43, 48, 30, 25, 52, 28, 41, 40, 34, 28, 41, 38, 40, 30, 35, 27, 27, 32, 44, 31],
        "Romans" => [32, 29, 31, 25, 21, 23, 25, 39, 33, 21, 36, 21, 14, 23, 33, 27],
        "1 Corinthians" => [31, 16, 23, 21, 13, 20, 40, 13, 27, 33, 34, 31, 13, 40, 58, 24],
        "2 Corinthians" => [24, 17, 18, 18, 21, 18, 16, 24, 15, 18, 33, 21, 14],
        "Galatians" => [24, 21, 29, 31, 26, 18],
        "Ephesians" => [23, 22, 21, 32, 33, 24],
        "Philippians" => [30, 30, 21, 23],
        "Colossians" => [29, 23, 25, 18],
        "1 Thessalonians" => [10, 20, 13, 18, 28],
        "2 Thessalonians" => [12, 17, 18],
        "1 Timothy" => [20, 15, 16, 16, 25, 21],
        "2 Timothy" => [18, 26, 17, 22],
        "Titus" => [16, 15, 15],
        "Philemon" => [25],
        "Hebrews" => [14, 18, 19, 16, 14, 20, 28, 13, 28, 39, 40, 29, 25],
        "James" => [27, 26, 18, 17, 20],
        "1 Peter" => [25, 25, 22, 19, 14],
        "2 Peter" => [21, 22, 18],
        "1 John" => [10, 29, 24, 21, 21],
        "2 John" => [13],
        "3 John" => [14],
        "Jude" => [25],
        "Revelation" => [20, 29, 22, 11, 14, 17, 17, 13, 21, 11, 19, 17, 18, 20, 8, 21, 18, 24, 21, 15, 27, 21]
    ];
    $remaining = $vid;
    foreach ($bible_structure as $book => $chapters) {
        $total_in_book = array_sum($chapters);
        if ($remaining <= $total_in_book) {
            foreach ($chapters as $idx => $v_count) {
                if ($remaining <= $v_count) {
                    return ["book" => $book, "chapter" => $idx + 1, "verse" => $remaining];
                }
                $remaining -= $v_count;
            }
        }
        $remaining -= $total_in_book;
    }
    return null;
}

function getCrossReferences($vid) {
    global $db;
    $stmt = $db->prepare("SELECT to_id FROM cross_references WHERE from_id = ?");
    $stmt->execute([$vid]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $refs = [];
    foreach ($ids as $id) {
        $ref = getVerseRefFromID($id);
        if ($ref) {
            $refs[] = $ref;
        }
    }
    return $refs;
}

function getCommentary($verse_id, $module = 'mhc') {
    global $db;
    // Find the largest verse_id <= target verse_id (handles verse ranges)
    $stmt = $db->prepare("SELECT text FROM commentary_{$module} WHERE verse_id <= ? ORDER BY verse_id DESC LIMIT 1");
    $stmt->execute([$verse_id]);
    return $stmt->fetchColumn();
}

function getVerse($book, $chapter, $verse, $version) {
    global $db;
    $stmt = $db->prepare("SELECT text FROM verses JOIN books b ON verses.book_id = b.id WHERE b.name = ? AND chapter = ? AND verse = ? AND version = ?");
    $stmt->execute([$book, $chapter, $verse, $version]);
    return $stmt->fetchColumn();
}

function getDefinition($term, $type = 'dictionary') {
    global $db;
    // robust_parser.py consolidated lexicons into one table with H/G prefixes
    if ($type == 'strong_hebrew' || $type == 'strong_greek') {
        $stmt = $db->prepare("SELECT definition FROM lexicons WHERE id = ?");
    } else {
        // robust_parser.py consolidated dictionaries into one table
        $stmt = $db->prepare("SELECT definition FROM dictionaries WHERE topic = ? COLLATE NOCASE");
    }
    $stmt->execute([$term]);
    return $stmt->fetchColumn();
}

// --- Request Handling ---
$action = $_GET['action'] ?? 'view';
$book = $_GET['book'] ?? 'Genesis';
$chapter = $_GET['chapter'] ?? 1;
$verse_focus = $_GET['verse'] ?? 1; // Added focus verse for commentary
$version = $_GET['version'] ?? 'KJV';
$comm_module = $_GET['comm'] ?? 'mhc';
$lookup = $_GET['lookup'] ?? ''; 
$lookupType = $_GET['type'] ?? 'dictionary';
$search_query = $_GET['q'] ?? ''; // Global Search

// Get Books
$books = $db->query("SELECT name FROM books")->fetchAll(PDO::FETCH_COLUMN);

// Handle Search vs View
$search_results = [];
if ($search_query) {
    $search_results = searchBible($search_query, $version);
}

// Get Chapter Text (Always load context)
$stmt = $db->prepare("SELECT v.verse, v.text FROM verses v JOIN books b ON v.book_id = b.id WHERE b.name = :book AND v.chapter = :chapter AND v.version = :version");
$stmt->execute([':book' => $book, ':chapter' => $chapter, ':version' => $version]);
$verses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get Commentary
$vid = getGlobalVerseID($book, $chapter, $verse_focus);
$commentary = getCommentary($vid, $comm_module);

// Get Cross References
$xrefs = getCrossReferences($vid);

// Get Definition
$definition = '';
if ($lookup) {
    $definition = getDefinition($lookup, $lookupType);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Power BibleCD Clone</title>
    <style>
        :root { --bg-color: #f0f0f0; --win-bg: #fff; --header-bg: #d4d0c8; --border: #808080; }
        body { font-family: 'Tahoma', sans-serif; background: var(--bg-color); height: 100vh; margin: 0; display: flex; flex-direction: column; overflow: hidden; }
        
        /* Toolbar */
        #toolbar { background: var(--header-bg); padding: 5px; border-bottom: 1px solid var(--border); display: flex; gap: 10px; align-items: center; }
        
        /* Workspace (MDI Area) */
        #workspace { flex: 1; position: relative; display: flex; gap: 5px; padding: 5px; overflow: hidden; }
        
        /* Windows */
        .window { 
            background: var(--win-bg); border: 2px solid var(--header-bg); border-top: 2px solid navy; 
            display: flex; flex-direction: column; box-shadow: 2px 2px 5px rgba(0,0,0,0.3);
            min-width: 300px;
        }
        .win-header { 
            background: navy; color: white; padding: 2px 5px; font-weight: bold; font-size: 12px; 
            display: flex; justify-content: space-between; cursor: move;
        }
        .win-content { flex: 1; overflow-y: auto; padding: 10px; font-family: 'Times New Roman', serif; font-size: 16px; }
        
        /* Layouts */
        #pane-bible { flex: 2; }
        #pane-tools { flex: 1; display: flex; flex-direction: column; gap: 5px; }
        #pane-dict { flex: 1; }
        #pane-comm { flex: 1; }
        
        /* Bible Text Styling */
        .verse-row { margin-bottom: 8px; cursor: pointer; border-radius: 3px; }
        .verse-row:hover { background: #f9f9f9; }
        .verse-row.active { background: #e8f0fe; border-left: 3px solid #1a73e8; }
        .v-num { color: #cc0000; font-weight: bold; font-size: 0.8em; vertical-align: super; margin-right: 5px; }
        .strongs { color: green; font-size: 0.7em; cursor: pointer; vertical-align: sub; display: none; } 
        .show-strongs .strongs { display: inline; }
        
        /* Forms */
        select, input, button { font-size: 12px; }
        h3 { margin-top: 0; font-size: 1.1rem; border-bottom: 1px solid #eee; padding-bottom: 5px; }
    </style>
    <script>
        function loadDefinition(term, type) {
            const url = new URL(window.location);
            url.searchParams.set('lookup', term);
            url.searchParams.set('type', type);
            window.location = url;
        }
        
        function setVerse(v) {
            const url = new URL(window.location);
            url.searchParams.set('verse', v);
            window.location = url;
        }

        function toggleStrongs() {
            document.getElementById('pane-bible').classList.toggle('show-strongs');
        }
    </script>
</head>
<body>

<!-- Main Toolbar -->
<div id="toolbar">
    <form method="GET">
        <select name="version" onchange="this.form.submit()">
            <option value="KJV" <?= $version === 'KJV' ? 'selected' : '' ?>>KJV</option>
            <option value="ASV" <?= $version === 'ASV' ? 'selected' : '' ?>>ASV</option>
        </select>
        <select name="book" onchange="this.form.submit()">
            <?php foreach ($books as $b): ?>
                <option value="<?= $b ?>" <?= $b === $book ? 'selected' : '' ?>><?= $b ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="chapter" value="<?= $chapter ?>" style="width: 40px;" min="1">
        <button type="submit">Go</button>
    </form>
    
    <button onclick="toggleStrongs()">Toggle Strongs</button>
    
    <span style="border-left: 1px solid #808080; height: 20px; margin: 0 10px;"></span>
    
    <form method="GET">
        <input type="hidden" name="book" value="<?= $book ?>">
        <input type="hidden" name="chapter" value="<?= $chapter ?>">
        <input type="hidden" name="verse" value="<?= $verse_focus ?>">
        <select name="comm" onchange="this.form.submit()">
            <option value="mhc" <?= $comm_module === 'mhc' ? 'selected' : '' ?>>Matthew Henry</option>
            <option value="barnes" <?= $comm_module === 'barnes' ? 'selected' : '' ?>>Barnes' Notes</option>
            <option value="jfb" <?= $comm_module === 'jfb' ? 'selected' : '' ?>>JFB</option>
            <option value="acc" <?= $comm_module === 'acc' ? 'selected' : '' ?>>Adam Clarke</option>
            <option value="rwp" <?= $comm_module === 'rwp' ? 'selected' : '' ?>>Robertson's WP</option>
        </select>
    </form>

    <form method="GET" style="margin-left:auto;">
        <input type="hidden" name="version" value="<?= $version ?>">
        <input type="text" name="q" placeholder="Search Bible..." value="<?= htmlspecialchars($search_query) ?>">
        <button type="submit">Search</button>
    </form>
</div>

<!-- MDI Workspace -->
<div id="workspace">

    <?php if ($search_query): ?>
        <!-- Search Results Pane (Floats over content or takes left side) -->
        <div class="window" style="flex: 1; border-color: #d00;">
            <div class="win-header" style="background: #d00;">
                <span>Search Results: "<?= htmlspecialchars($search_query) ?>" (<?= count($search_results) ?> found)</span>
                <span onclick="window.location='?book=<?= urlencode($book) ?>&chapter=<?= $chapter ?>'" style="cursor:pointer;">[CLOSE]</span>
            </div>
            <div class="win-content">
                <?php if (empty($search_results)): ?>
                    <p>No matches found.</p>
                <?php else: ?>
                    <?php foreach ($search_results as $r): ?>
                        <div class="verse-row" onclick="window.location='?version=<?= $version ?>&book=<?= urlencode($r['book_name']) ?>&chapter=<?= $r['chapter'] ?>&verse=<?= $r['verse'] ?>'">
                            <span style="color:blue; font-weight:bold;"><?= $r['book_name'] ?> <?= $r['chapter'] ?>:<?= $r['verse'] ?></span>
                            <br>
                            <span><?= $r['text'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Left Pane: Bible Text -->
    <div id="pane-bible" class="window" style="<?= $search_query ? 'display:none;' : '' ?>">
        <div class="win-header">
            <span><?= htmlspecialchars($book) ?> <?= $chapter ?> (<?= htmlspecialchars($version) ?>)</span>
            <span>[X]</span>
        </div>
        <div class="win-content">
            <?php foreach ($verses as $v): ?>
                <div class="verse-row <?= $v['verse'] == $verse_focus ? 'active' : '' ?>" onclick="setVerse(<?= $v['verse'] ?>)">
                    <span class="v-num"><?= $v['verse'] ?></span>
                    <?php 
                        $text = htmlspecialchars($v['text']);
                        if ($version == 'KJV') {
                             $text = str_replace('God', 'God<sup class="strongs" onclick="event.stopPropagation(); loadDefinition(\'H430\', \'strong_hebrew\')">&lt;H430&gt;</sup>', $text);
                             $text = str_replace('beginning', 'beginning<sup class="strongs" onclick="event.stopPropagation(); loadDefinition(\'H7225\', \'strong_hebrew\')">&lt;H7225&gt;</sup>', $text);
                        }
                        echo $text;
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right Pane: Tools -->
    <div id="pane-tools">
        
        <!-- Top Right: Dictionary/Lexicon -->
        <div id="pane-dict" class="window">
            <div class="win-header">
                <span>Dictionary / Lexicon</span>
                <span>[X]</span>
            </div>
            <div class="win-content">
                <?php if ($lookup): ?>
                    <h3><?= htmlspecialchars($lookup) ?></h3>
                    <p><?= nl2br(htmlspecialchars($definition ?: 'Definition not found.')) ?></p>
                <?php else: ?>
                    <p><i>Click a Green Tag or search to define.</i></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Middle Right: Cross References -->
        <div id="pane-xref" class="window" style="flex: 0.5; min-height: 150px;">
            <div class="win-header">
                <span>Cross References</span>
                <span>[X]</span>
            </div>
            <div class="win-content">
                <?php if ($xrefs): ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($xrefs as $xr): ?>
                            <a href="?version=<?= $version ?>&book=<?= urlencode($xr['book']) ?>&chapter=<?= $xr['chapter'] ?>&verse=<?= $xr['verse'] ?>" class="x-ref" style="font-size: 14px;">
                                <?= $xr['book'] ?> <?= $xr['chapter'] ?>:<?= $xr['verse'] ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><i>No cross references for this verse.</i></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bottom Right: Commentary -->
        <div id="pane-comm" class="window">
            <div class="win-header">
                <span>Commentary (<?= strtoupper($comm_module) ?>)</span>
                <span>[X]</span>
            </div>
            <div class="win-content">
                <h3><?= $book ?> <?= $chapter ?>:<?= $verse_focus ?></h3>
                <div style="white-space: pre-wrap; line-height: 1.5;">
                    <?= htmlspecialchars($commentary ?: 'No commentary found for this verse.') ?>
                </div>
            </div>
        </div>

    </div>

</div>

</body>
</html>


    <!-- Right Pane: Tools -->
    <div id="pane-tools">
        
        <!-- Top Right: Dictionary/Lexicon -->
        <div id="pane-dict" class="window">
            <div class="win-header">
                <span>Dictionary / Lexicon</span>
                <span>[X]</span>
            </div>
            <div class="win-content">
                <?php if ($lookup): ?>
                    <h3><?= htmlspecialchars($lookup) ?></h3>
                    <p><?= nl2br(htmlspecialchars($definition ?: 'Definition not found.')) ?></p>
                <?php else: ?>
                    <p><i>Click a Green Tag or search to define.</i></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bottom Right: Commentary -->
        <div id="pane-comm" class="window">
            <div class="win-header">
                <span>Commentary (Matthew Henry)</span>
                <span>[X]</span>
            </div>
            <div class="win-content">
                <p><b><?= $book ?> <?= $chapter ?></b></p>
                <p><i>(Commentary extraction pending... displaying placeholder)</i></p>
                <p>This chapter gives us an account of the creation of the world...</p>
            </div>
        </div>

    </div>

</div>

</body>
</html>
