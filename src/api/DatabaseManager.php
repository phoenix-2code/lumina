<?php
/**
 * DatabaseManager - Lumina v1.3.0
 * Handles multi-database attachment and on-demand loading.
 */
class DatabaseManager {
    private static $instance = null;
    private $pdo;
    private $attached = [];

    private function __construct() {
        $root = __DIR__ . '/../../assets/data';
        $corePath = "$root/core.db";
        
        // Ensure core exists
        if (!file_exists($corePath)) {
            throw new Exception("Core database missing at $corePath");
        }

        $this->pdo = new PDO("sqlite:$corePath");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Auto-attach standard study tools if they exist
        $this->ensureAttached('versions');
        $this->ensureAttached('commentaries');
        $this->ensureAttached('extras');

        // Performance Tuning
        $this->pdo->exec("PRAGMA journal_mode = WAL");
        $this->pdo->exec("PRAGMA synchronous = NORMAL");
    }

    public static function getInstance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    /**
     * Attaches an external database (e.g. 'versions', 'commentaries') only when needed.
     */
    public function ensureAttached($alias) {
        if (isset($this->attached[$alias])) return true;

        $path = __DIR__ . "/../../assets/data/{$alias}.db";
        if (file_exists($path)) {
            // Note: Use the alias as the schema name in queries (e.g. commentaries.commentary_entries)
            $this->pdo->exec("ATTACH DATABASE '$path' AS $alias");
            $this->attached[$alias] = true;
            return true;
        }
        return false;
    }
}
