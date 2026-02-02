<?php
class Database {
    private static $pdo = null;

    public static function connect() {
        if (self::$pdo === null) {
            try {
                // Potential paths to find bible_app.db
                $candidates = [
                    __DIR__ . '/../../assets/bible_app.db', // Standard Structure (Dev/Prod)
                    __DIR__ . '/../assets/bible_app.db',    // Flattened Structure
                    __DIR__ . '/assets/bible_app.db',       // Direct inclusion
                    'assets/bible_app.db',                  // Relative to web root
                    'bible_app.db'                          // Root fallback
                ];

                $dbPath = null;
                foreach ($candidates as $p) {
                    if (file_exists($p)) {
                        $dbPath = $p;
                        break;
                    }
                }

                if (!$dbPath) {
                    // Log failure for debugging in production
                    $logMsg = date('[Y-m-d H:i:s] ') . "DB Not Found. Searched in:\n" . implode("\n", $candidates) . "\n";
                    file_put_contents(__DIR__ . '/../../db_error.log', $logMsg, FILE_APPEND);
                    throw new Exception("Database file (bible_app.db) not found in expected locations.");
                }

                self::$pdo = new PDO("sqlite:$dbPath");
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Performance Optimizations
                self::$pdo->exec("PRAGMA synchronous = OFF");
                self::$pdo->exec("PRAGMA journal_mode = WAL");
            } catch (Exception $e) {
                // Ensure we always return JSON even on fatal DB error
                header('Content-Type: application/json');
                die(json_encode(["error" => "Database Error: " . $e->getMessage()]));
            }
        }
        return self::$pdo;
    }
}
