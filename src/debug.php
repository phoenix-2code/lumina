<?php
// modern_app/src/debug.php
header('Content-Type: text/html');

function listFolderFiles($dir){
    $ffs = scandir($dir);
    echo "<ul>";
    foreach($ffs as $ff){
        if($ff != '.' && $ff != '..'){
            echo "<li>$ff";
            if(is_dir($dir.'/'.$ff)) listFolderFiles($dir.'/'.$ff);
            echo "</li>";
        }
    }
    echo "</ul>";
}

echo "<h1>System Diagnostic</h1>";
echo "<h2>Document Root</h2>" . $_SERVER['DOCUMENT_ROOT'];
echo "<h2>Current Dir</h2>" . __DIR__;
echo "<h2>File Structure</h2>";
listFolderFiles(__DIR__ . '/..'); // List parent dir (resources)
