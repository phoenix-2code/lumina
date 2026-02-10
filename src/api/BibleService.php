<?php
/**
 * BibleService - Lumina v1.3.7
 * Handles Bible text retrieval, versions, and interlinear processing.
 */

class BibleService {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getChapter($params) {
        $book = $params['book'] ?? 'Genesis';
        $chapter = (int)($params['chapter'] ?? 1);
        $version = $params['version'] ?? 'KJV';
        $interlinear = $params['interlinear'] ?? 'false';

        if ($chapter <= 0) throw new Exception("Invalid chapter.");

        $table = ($version === 'KJV') ? 'main.verses' : 'versions.verses';

        $stmt = $this->db->prepare("
            SELECT v.id, v.verse, v.text,
                (SELECT GROUP_CONCAT(c.abbreviation) 
                 FROM commentaries.commentary_entries ce 
                 JOIN commentaries.commentaries c ON ce.commentary_id = c.id 
                 WHERE ce.verse_id = (
                    SELECT v2.id FROM main.verses v2 
                    WHERE v2.book_id = v.book_id AND v2.chapter = v.chapter AND v2.verse = v.verse
                    LIMIT 1
                 )) as modules
            FROM $table v 
            JOIN main.books b ON v.book_id = b.id 
            WHERE b.name = :book AND v.chapter = :chapter AND v.version = :version
        ");
        $stmt->execute([':book' => $book, ':chapter' => $chapter, ':version' => $version]);
        $verses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($verses)) throw new Exception("Chapter not found.");

        return $this->processInterlinear($verses, $interlinear);
    }

    private function processInterlinear($verses, $interlinear) {
        foreach ($verses as &$v) {
            if ($interlinear === 'true') {
                $stmtW = $this->db->prepare("
                    SELECT vw.word, vw.strongs_id, l.transliteration 
                    FROM extras.verse_words vw
                    LEFT JOIN extras.lexicon l ON vw.strongs_id = l.id
                    WHERE vw.verse_id = ?
                    ORDER BY vw.position ASC
                ");
                $stmtW->execute([$v['id']]);
                $words = $stmtW->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($words)) {
                    $html = "";
                    foreach($words as $w) {
                        $wordText = TextService::sanitizeHTML($w['word']);
                        if ($w['strongs_id']) {
                            $tag = $w['transliteration'] ?: $w['strongs_id'];
                            $lexType = (strpos($w['strongs_id'], 'G') === 0) ? 'strong_greek' : 'strong_hebrew';
                            $html .= "$wordText <span class='strongs-tag' onclick="event.stopPropagation(); showDef('{$w['strongs_id']}', '$lexType')">&lt;$tag&gt;</span> ";
                        } else { $html .= "$wordText "; }
                    }
                    $v['text'] = trim($html);
                }
            } else {
                $v['text'] = TextService::sanitizeHTML($v['text']);
            }
        }
        return $verses;
    }

    public function getVersions() {
        $v1 = $this->db->query("SELECT DISTINCT version FROM main.verses")->fetchAll(PDO::FETCH_COLUMN);
        $v2 = $this->db->query("SELECT DISTINCT version FROM versions.verses")->fetchAll(PDO::FETCH_COLUMN);
        return array_merge($v1, $v2);
    }
}
