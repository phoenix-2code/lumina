<?php
class Database {
    private static $pdo = null;

    public static function connect() {
        if (self::$pdo === null) {
            try {
                // Adjust path: Class is in src/api/
                // DB is in assets/bible_app.db
                // So we go up two levels: ../../assets/
                $dbPath = __DIR__ . '/../../assets/bible_app.db';
                self::$pdo = new PDO("sqlite:$dbPath");
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                // Optimization
                self::$pdo->exec("PRAGMA synchronous = OFF");
                self::$pdo->exec("PRAGMA journal_mode = WAL");
            } catch (PDOException $e) {
                die(json_encode(["error" => "Database Connection Failed: " . $e->getMessage()]));
            }
        }
        return self::$pdo;
    }
}
