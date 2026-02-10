<?php
require_once 'DatabaseManager.php';

/**
 * Legacy Database Proxy
 * Redirects legacy connect() calls to the new DatabaseManager.
 */
class Database {
    public static function connect() {
        return DatabaseManager::getInstance()->getConnection();
    }
}