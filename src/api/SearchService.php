<?php
/**
 * SearchService - Lumina v1.3.7
 * Handles high-performance FTS5 searching and pagination.
 */

class SearchService {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function search($params) {
        $q = $params['q'] ?? '';
        $version = $params['version'] ?? 'KJV';
        $offset = (int)($params['offset'] ?? 0);
        $dbAlias = ($version === 'KJV') ? 'main' : 'versions';

        $clean = preg_replace('/[^a-zA-Z0-9 ]/', '', $q);
        if (!$clean) return ["results" => [], "count" => 0];

        $count = $this->db->prepare("SELECT COUNT(*) FROM $dbAlias.verses_fts WHERE verses_fts MATCH :q AND version = :v");
        $count->execute([':q' => $clean, ':v' => $version]);
        $total = $count->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT b.name as book_name, v.chapter, v.verse, highlight($dbAlias.verses_fts, 0, '[[MARK]]', '[[/MARK]]') as text 
            FROM $dbAlias.verses_fts v 
            JOIN main.books b ON v.book_id = b.id 
            WHERE v.verses_fts MATCH :q AND v.version = :v 
            LIMIT 200 OFFSET $offset
        ");
        $stmt->execute([':q' => $clean, ':v' => $version]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$r) {
            $r['text'] = TextService::sanitizeHTML($r['text']);
            $r['text'] = str_replace(['[[MARK]]', '[[/MARK]]'], ['<mark>', '</mark>'], $r['text']);
        }

        return ["results" => $results, "count" => $total];
    }
}
