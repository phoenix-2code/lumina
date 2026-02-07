<?php
// modern_app/src/debug.php
header('Content-Type: text/html');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<style>body{background:#000;color:#0f0;font-family:monospace;padding:20px;} h2{color:yellow;border-bottom:1px solid #0f0;} .err{color:red;} .ok{color:#0f0;}</style>";
echo "<h1>🕵️‍♂️ FaithStream Integrity Check</h1>";

$resourcesDir = realpath(__DIR__ . '/..');
echo "<h2>1. Directory: $resourcesDir</h2>";

function listFiles($dir) {
    $items = scandir($dir);
    echo "<ul>";
    foreach($items as $item) {
        if($item == "." || $item == "..") continue;
        $path = $dir . '/' . $item;
        $size = is_dir($path) ? "" : " (" . round(filesize($path)/1024, 2) . " KB)";
        echo "<li>$item $size";
        if(is_dir($path)) listFiles($path);
        echo "</li>";
    }
    echo "</ul>";
}
listFiles($resourcesDir);

echo "<h2>2. Database Verification</h2>";
$dbPath = $resourcesDir . '/assets/bible_app.db';

if (file_exists($dbPath)) {
    $size = filesize($dbPath);
    echo "Path: $dbPath<br>";
    echo "Size: " . number_format($size) . " bytes (" . round($size/1024/1024, 2) . " MB)<br>";
    
    if ($size < 2048) {
        echo "<h3 class='err'>⚠️ WARNING: File size is too small! Likely a Git LFS Pointer.</h3>";
        $content = file_get_contents($dbPath);
        echo "File Content Start: <pre style='background:#222;padding:10px;'>" . htmlspecialchars(substr($content, 0, 200)) . "</pre>";
    } else {
        echo "<span class='ok'>✅ Size looks realistic.</span><br>";
        
        // Check Header
        $fp = fopen($dbPath, 'rb');
        $header = fread($fp, 16);
        fclose($fp);
        echo "Hex Header: " . bin2hex($header) . "<br>";
        if (strpos($header, "SQLite format 3") === 0) {
            echo "<span class='ok'>✅ Valid SQLite Header detected.</span><br>";
        } else {
            echo "<span class='err'>❌ INVALID SQLite Header!</span><br>";
        }

        // Test Query
        try {
            $pdo = new PDO("sqlite:$dbPath");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "Connection: <span class='ok'>SUCCESS</span><br>";
            $count = $pdo->query("SELECT COUNT(*) FROM verses")->fetchColumn();
            echo "Verses in DB: <span class='ok'>" . number_format($count) . "</span><br>";
        } catch (Exception $e) {
            echo "Query Failed: <span class='err'>" . $e->getMessage() . "</span><br>";
        }
    }
} else {
    echo "<span class='err'>❌ Database file NOT FOUND at $dbPath</span>";
}
