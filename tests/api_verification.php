<?php
/**
 * Lumina API Core Verification Test
 * 
 * This script verifies the 1-31102 sequential ID alignment and 
 * core study tool mapping to ensure data integrity.
 */

require_once __DIR__ . '/../src/api/Database.php';
require_once __DIR__ . '/../src/api/Helper.php';

$success = true;
$results = [];

function assertTest($description, $condition) {
    global $results, $success;
    if ($condition) {
        $results[] = "✅ PASS: $description";
    } else {
        $results[] = "❌ FAIL: $description";
        $success = false;
    }
}

echo "Running Lumina Core Integrity Tests...
";
echo "====================================
";

try {
    $db = Database::connect();

    // 1. SEQUENCE TESTS
    $stmt = $db->prepare("SELECT b.name, v.chapter, v.verse FROM verses v JOIN books b ON v.book_id = b.id WHERE v.id = ? AND v.version = 'KJV'");
    
    // Genesis 1:1
    $stmt->execute([1]);
    $g11 = $stmt->fetch(PDO::FETCH_ASSOC);
    assertTest("ID 1 is Genesis 1:1", ($g11['name'] == 'Genesis' && $g11['chapter'] == 1 && $g11['verse'] == 1));

    // Genesis 50:26 (Last verse of Genesis)
    $stmt->execute([1533]);
    $g5026 = $stmt->fetch(PDO::FETCH_ASSOC);
    assertTest("ID 1533 is Genesis 50:26", ($g5026['name'] == 'Genesis' && $g5026['chapter'] == 50 && $g5026['verse'] == 26));

    // Matthew 1:1 (First verse of NT)
    $stmt->execute([23146]);
    $m11 = $stmt->fetch(PDO::FETCH_ASSOC);
    assertTest("ID 23146 is Matthew 1:1", ($m11['name'] == 'Matthew' && $m11['chapter'] == 1 && $m11['verse'] == 1));

    // Revelation 22:21 (Last verse of Bible)
    $stmt->execute([31102]);
    $r2221 = $stmt->fetch(PDO::FETCH_ASSOC);
    assertTest("ID 31102 is Revelation 22:21", ($r2221['name'] == 'Revelation' && $r2221['chapter'] == 22 && $r2221['verse'] == 21));

    // 2. COMMENTARY ALIGNMENT TEST
    $stmtComm = $db->prepare("SELECT ce.text FROM commentaries.commentary_entries ce JOIN commentaries.commentaries c ON ce.commentary_id = c.id WHERE c.abbreviation = 'mhc' AND ce.verse_id = ?");
    
    // Gen 1:1
    $stmtComm->execute([1]);
    $c1 = $stmtComm->fetchColumn();
    assertTest("MHC has Genesis Introduction at ID 1", (strpos($c1, 'INTRODUCTION TO GENESIS') !== false));

    // Gen 50:22 (The old ghost verse)
    $stmtComm->execute([1529]);
    $c1529 = $stmtComm->fetchColumn();
    assertTest("MHC has correct Joseph commentary at ID 1529", (strpos($c1529, 'prolonging of Joseph') !== false));

    // 3. SEARCH (FTS5) TEST
    $stmtSearch = $db->prepare("SELECT COUNT(*) FROM main.verses_fts WHERE verses_fts MATCH 'Abib' AND version = 'KJV'");
    $stmtSearch->execute();
    $abibCount = $stmtSearch->fetchColumn();
    assertTest("Search for 'Abib' returns 4 results in KJV", ($abibCount == 4));

    // 4. CROSS-VERSION TEST
    $stmtVer = $db->prepare("SELECT COUNT(*) FROM versions.verses WHERE version = ?");
    $stmtVer->execute(['NIV']);
    $nivCount = $stmtVer->fetchColumn();
    assertTest("NIV Version exists and has 31102 verses", ($nivCount == 31102));

} catch (Exception $e) {
    echo "🚨 CRITICAL TEST ERROR: " . $e->getMessage() . "
";
    $success = false;
}

foreach ($results as $r) echo "$r
";

echo "====================================
";
if ($success) {
    echo "💎 ALL CORE SYSTEMS STABLE
";
    exit(0);
} else {
    echo "⚠️ INTEGRITY BREACH DETECTED
";
    exit(1);
}
