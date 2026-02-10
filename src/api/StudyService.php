<?php
/**
 * StudyService - Lumina v1.3.7
 * Handles Study Tools: Commentaries, Cross-references, and Dictionaries.
 */

class StudyService {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getCommentary($params) {
        $book = $params['book'] ?? '';
        $chapter = (int)($params['chapter'] ?? 0);
        $verse = (int)($params['verse'] ?? 0);
        $module = strtolower($params['module'] ?? 'mhc');

        $stmtId = $this->db->prepare("SELECT v.id FROM main.verses v JOIN main.books b ON v.book_id = b.id WHERE b.name = ? AND v.chapter = ? AND v.verse = ? LIMIT 1");
        $stmtId->execute([$book, $chapter, $verse]);
        $vid = $stmtId->fetchColumn();

        if (!$vid) throw new Exception("Verse not found.");

        $stmt = $this->db->prepare("SELECT ce.text FROM commentaries.commentary_entries ce JOIN commentaries.commentaries c ON ce.commentary_id = c.id WHERE c.abbreviation = ? AND ce.verse_id = ?");
        $stmt->execute([$module, $vid]);
        $text = $stmt->fetchColumn();

        return TextService::formatCommentary($text) ?: "No commentary found.";
    }

    public function getCrossReferences($params) {
        $book = $params['book'] ?? '';
        $chapter = (int)($params['chapter'] ?? 0);
        $verse = (int)($params['verse'] ?? 0);

        $stmtId = $this->db->prepare("SELECT v.id FROM main.verses v JOIN main.books b ON v.book_id = b.id WHERE b.name = ? AND v.chapter = ? AND v.verse = ? LIMIT 1");
        $stmtId->execute([$book, $chapter, $verse]);
        $vid = $stmtId->fetchColumn();

        if (!$vid) throw new Exception("Reference not found.");

        $stmt = $this->db->prepare("SELECT b.name as book, v.chapter, v.verse FROM extras.cross_references xr JOIN main.verses v ON xr.to_verse_id = v.id JOIN main.books b ON v.book_id = b.id WHERE xr.from_verse_id = ?");
        $stmt->execute([$vid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDefinition($params) {
        $term = $params['term'] ?? '';
        $type = $params['type'] ?? 'dictionary';
        $module = $params['module'] ?? 'EASTON';

        if (!$term) throw new Exception("Missing term.");

        if ($type == 'strong_hebrew' || $type == 'strong_greek') {
            $stmt = $this->db->prepare("SELECT definition FROM extras.lexicon WHERE id = ?");
            $stmt->execute([$term]);
        } else {
            $stmt = $this->db->prepare("SELECT definition FROM extras.dictionaries WHERE topic = ? AND module = ? COLLATE NOCASE");
            $stmt->execute([$term, strtoupper($module)]);
        }
        $text = $stmt->fetchColumn();
        return $text ? TextService::formatCommentary($text) : "Not found.";
    }

    public function getCommentaryList() {
        $mods = $this->db->query("SELECT abbreviation FROM commentaries.commentaries ORDER BY abbreviation")->fetchAll(PDO::FETCH_COLUMN);
        return array_map('strtoupper', $mods);
    }
}
