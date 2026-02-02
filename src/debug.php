<?php
// modern_app/src/debug.php
header('Content-Type: text/html');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<style>body{background:#111;color:#0f0;font-family:monospace;padding:20px;line-height:1.4;} h2{border-bottom:1px solid #0f0;margin-top:20px;} .err{color:red;} .ok{color:#0f0;} .warn{color:yellow;}</style>";
echo "<h1>🔧 FaithStream Diagnostic (Native PHP)</h1>";

// 1. Environment
echo "<h2>1. Environment</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "OS: " . PHP_OS . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Current Script: " . __FILE__ . "<br>";

// 2. File System Check
echo "<h2>2. File System Check</h2>";
$paths_to_check = [
    __DIR__ . '/../assets/bible_app.db',
    __DIR__ . '/../../assets/bible_app.db',
    $_SERVER['DOCUMENT_ROOT'] . '/../assets/bible_app.db',
    'C:/bible/modern_app/assets/bible_app.db'
];

$real_db_path = null;

foreach ($paths_to_check as $path) {
    echo "Checking: $path ... ";
    if (file_exists($path)) {
        echo "<span class='ok'>FOUND</span> (" . round(filesize($path)/1024/1024, 2) . " MB)<br>";
        $real_db_path = $path;
    } else {
        echo "<span class='err'>MISSING</span><br>";
    }
}

// 3. Database Integrity
echo "<h2>3. Database Integrity</h2>";
if ($real_db_path) {
    try {
        $pdo = new PDO("sqlite:$real_db_path");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connection: <span class='ok'>SUCCESS</span><br>";
        
        // Check Tables
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables found: " . count($tables) . "<br>";
        echo "List: " . implode(", ", $tables) . "<br>";
        
        // Critical Counts
        if (in_array('verses', $tables)) {
            $count = $pdo->query("SELECT COUNT(*) FROM verses")->fetchColumn();
            echo "Verses Count: " . number_format($count) . " (Expected: ~31,102) ";
            echo ($count > 30000) ? "<span class='ok'>OK</span>" : "<span class='err'>LOW</span>";
            echo "<br>";
        } else {
            echo "<span class='err'>CRITICAL: 'verses' table missing!</span><br>";
        }

        if (in_array('dictionaries', $tables)) {
            $count = $pdo->query("SELECT COUNT(*) FROM dictionaries")->fetchColumn();
            echo "Dictionary Entries: " . number_format($count) . "<br>";
        }

    } catch (Exception $e) {
        echo "<span class='err'>Connection Failed: " . $e->getMessage() . "</span>";
    }
} else {
    echo "<span class='err'>SKIPPED: No DB file found.</span>";
}

// 4. API Logic Check
echo "<h2>4. API Logic Check</h2>";
$helperPath = __DIR__ . '/api/Helper.php';
if (file_exists($helperPath)) {
    include_once $helperPath;
    echo "Helper.php loaded.<br>";
    if (class_exists('Helper')) {
        echo "Helper Class: <span class='ok'>EXISTS</span><br>";
    } else {
        echo "Helper Class: <span class='err'>MISSING</span><br>";
    }
} else {
    echo "Helper.php: <span class='err'>MISSING at $helperPath</span><br>";
}