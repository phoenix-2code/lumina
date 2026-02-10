<?php
/**
 * Test 1: Verse Alignment Baseline
 */
require_once __DIR__ . '/../../src/api/Database.php';

echo "🧪 Running Verse Alignment Baseline...
";
$db = Database::connect();

$tests = [
    ['id' => 1, 'ref' => 'Genesis 1:1'],
    ['id' => 23146, 'ref' => 'Matthew 1:1'],
    ['id' => 31102, 'ref' => 'Revelation 22:21']
];

foreach ($tests as $t) {
    $stmt = $db->prepare("SELECT b.name || ' ' || v.chapter || ':' || v.verse as ref FROM verses v JOIN books b ON v.book_id = b.id WHERE v.id = ? AND v.version = 'KJV'");
    $stmt->execute([$t['id']]);
    $actual = $stmt->fetchColumn();
    
    if ($actual === $t['ref']) {
        echo "✅ ID {$t['id']} matches {$t['ref']}
";
    } else {
        echo "❌ FAIL: ID {$t['id']} expected {$t['ref']}, got $actual
";
        exit(1);
    }
}
echo "✨ Baseline Alignment Verified.
";
