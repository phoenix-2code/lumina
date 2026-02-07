<?php
class Database {
    private static $pdo = null;

    public static function connect() {
        if (self::$pdo === null) {
            try {
                // Priority 1: Environment Variable from Electron
                $envPath = getenv('BIBLE_DB_PATH');
                
                // Nuclear Option: Anchor to Document Root
                $root = $_SERVER['DOCUMENT_ROOT'] ?: __DIR__ . '/..';
                
                // --- DEBUG LOGGING ---
                $debugInfo = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'BIBLE_DB_PATH_ENV' => $envPath ?: 'NOT SET',
                    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
                    '__DIR__' => __DIR__,
                    'is_packaged' => (strpos(__DIR__, 'app.asar') !== false) ? 'YES' : 'NO'
                ];
                
                $candidates = [];
                if ($envPath) $candidates[] = $envPath;
                $candidates[] = $root . '/../assets/bible_app.db';
                $candidates[] = $root . '/assets/bible_app.db';
                $candidates[] = __DIR__ . '/../../assets/bible_app.db';
                $candidates[] = 'C:/bible/modern_app/assets/bible_app.db';

                $dbPath = null;
                $searchLog = "Searching for DB...\n";
                foreach ($candidates as $p) {
                    if (empty($p)) continue;
                    $exists = file_exists($p) ? "FOUND" : "MISSING";
                    $searchLog .= "[$exists] $p\n";
                    if ($exists === "FOUND") {
                        $dbPath = $p;
                        break;
                    }
                }

                if (!$dbPath) {
                    // Log failure for debugging in production
                    $logMsg = "--- DATABASE CONNECTION ERROR ---\n";
                    $logMsg .= "Environment:\n" . print_r($debugInfo, true) . "\n";
                    $logMsg .= $searchLog . "\n";
                    file_put_contents(__DIR__ . '/../../db_error.log', $logMsg, FILE_APPEND);
                    throw new Exception("Database file not found. Check db_error.log for details.");
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
