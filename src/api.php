<?php
require_once 'api/DatabaseManager.php';
require_once 'api/Helper.php';
require_once 'api/TextService.php';

header('Content-Type: application/json');

// --- Security: Basic Origin Check ---
$allowed_origins = ['http://localhost:8000', 'http://127.0.0.1:8000'];
if (isset($_SERVER['HTTP_ORIGIN']) && !in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    http_response_code(403);
    echo json_encode(["error" => "Forbidden: Cross-origin request blocked."]);
    exit;
}

try {
    $db = DatabaseManager::getInstance()->getConnection();
    $action = $_GET['action'] ?? '';

    if (!$action) {
        throw new Exception("Missing 'action' parameter.");
    }

    // --- BIBLE TEXT ---
    if ($action == 'text') {
        $book = $_GET['book'] ?? 'Genesis';
        $chapter = (int)($_GET['chapter'] ?? 1);
        $version = $_GET['version'] ?? 'KJV';
        $interlinear = $_GET['interlinear'] ?? 'false';
        
        if ($chapter <= 0) throw new Exception("Invalid chapter number.");

        // Table source depends on version
        $table = ($version === 'KJV') ? 'main.verses' : 'versions.verses';

        $stmt = $db->prepare("
            SELECT 
                v.id, 
                v.verse, 
                v.text,
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
        
        if (empty($verses)) {
            throw new Exception("No verses found for '$book $chapter' ($version).");
        }

        // Process interlinear/strongs
        foreach ($verses as &$v) {
            if ($interlinear === 'true') {
                $stmtW = $db->prepare("
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
                            $html .= "$wordText <span class='strongs-tag' onclick=\"event.stopPropagation(); showDef('{$w['strongs_id']}', '$lexType')\">&lt;$tag&gt;</span> ";
                        } else {
                            $html .= "$wordText ";
                        }
                    }
                    $v['text'] = trim($html);
                }
            } else {
                $v['text'] = TextService::sanitizeHTML($v['text']);
            }
        }
        
        echo json_encode(["verses" => $verses]);

    // --- COMMENTARY ---
    } elseif ($action == 'commentary') {
        $book = $_GET['book'] ?? '';
        $chapter = (int)($_GET['chapter'] ?? 0);
        $verse = (int)($_GET['verse'] ?? 0);
        $module = strtolower($_GET['module'] ?? 'mhc');
        
        if (!$book || $chapter <= 0 || $verse <= 0) {
            throw new Exception("Invalid reference for commentary lookup.");
        }

        $stmtId = $db->prepare("
            SELECT v.id FROM main.verses v 
            JOIN main.books b ON v.book_id = b.id 
            WHERE b.name = ? AND v.chapter = ? AND v.verse = ?
            LIMIT 1
        ");
        $stmtId->execute([$book, $chapter, $verse]);
        $vid = $stmtId->fetchColumn();

        if (!$vid) throw new Exception("Verse not found.");

        $stmt = $db->prepare("
            SELECT ce.text
            FROM commentaries.commentary_entries ce
            JOIN commentaries.commentaries c ON ce.commentary_id = c.id
            WHERE c.abbreviation = ? AND ce.verse_id = ?
        ");
        $stmt->execute([$module, $vid]);
        $text = $stmt->fetchColumn();
        
        echo json_encode(["text" => TextService::formatCommentary($text) ?: "No commentary found."]);

    // --- CROSS REFS ---
    } elseif ($action == 'xrefs') {
        $book = $_GET['book'] ?? '';
        $chapter = (int)($_GET['chapter'] ?? 0);
        $verse = (int)($_GET['verse'] ?? 0);
        
        $stmtId = $db->prepare("SELECT v.id FROM main.verses v JOIN main.books b ON v.book_id = b.id WHERE b.name = ? AND v.chapter = ? AND v.verse = ? LIMIT 1");
        $stmtId->execute([$book, $chapter, $verse]);
        $vid = $stmtId->fetchColumn();

        if (!$vid) throw new Exception("Reference not found.");

        $stmt = $db->prepare("
            SELECT b.name as book, v.chapter, v.verse 
            FROM extras.cross_references xr
            JOIN main.verses v ON xr.to_verse_id = v.id
            JOIN main.books b ON v.book_id = b.id
            WHERE xr.from_verse_id = ?
        ");
        $stmt->execute([$vid]);
        echo json_encode(["xrefs" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    // --- DICTIONARY ---
    } elseif ($action == 'definition') {
        $term = $_GET['term'] ?? '';
        $type = $_GET['type'] ?? 'dictionary';
        $module = $_GET['module'] ?? 'EASTON';
        
        if (!$term) throw new Exception("Missing term.");

        if ($type == 'strong_hebrew' || $type == 'strong_greek') {
            $stmt = $db->prepare("SELECT definition FROM extras.lexicon WHERE id = ?");
            $stmt->execute([$term]);
        } else {
            $stmt = $db->prepare("SELECT definition FROM extras.dictionaries WHERE topic = ? AND module = ? COLLATE NOCASE");
            $stmt->execute([$term, strtoupper($module)]);
        }
        echo json_encode(["definition" => $stmt->fetchColumn() ? TextService::formatCommentary($stmt->fetchColumn()) : "Not found."]);

    // --- LISTS ---
    } elseif ($action == 'version_list') {
        $v1 = $db->query("SELECT DISTINCT version FROM main.verses")->fetchAll(PDO::FETCH_COLUMN);
        $v2 = $db->query("SELECT DISTINCT version FROM versions.verses")->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(["versions" => array_merge($v1, $v2)]);

    } elseif ($action == 'commentary_list') {
        $mods = $db->query("SELECT abbreviation FROM commentaries.commentaries ORDER BY abbreviation")->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(["modules" => array_map('strtoupper', $mods)]);

    // --- SEARCH ---
    } elseif ($action == 'search') {
        // Search remains on core for now, or we'd need to re-index.
        // For v1.3.0, we'll keep the monolithic FTS or split it.
        // Assuming search is in main for KJV and versions for others.
        // This part needs careful handling of FTS across attached DBs.
        $q = $_GET['q'] ?? '';
        $version = $_GET['version'] ?? 'KJV';
        $dbAlias = ($version === 'KJV') ? 'main' : 'versions';
        
        $clean = preg_replace('/[^a-zA-Z0-9 ]/', '', $q);
        if (!$clean) { echo json_encode(["results" => [], "count" => 0]); exit; }

        // FTS must be in the same DB as the verses.
        $count = $db->prepare("SELECT COUNT(*) FROM $dbAlias.verses_fts WHERE verses_fts MATCH :q AND version = :v");
        $count->execute([':q' => $clean, ':v' => $version]);
        $total = $count->fetchColumn();

        $stmt = $db->prepare("
            SELECT b.name as book_name, v.chapter, v.verse, highlight($dbAlias.verses_fts, 0, '[[MARK]]', '[[/MARK]]') as text 
            FROM $dbAlias.verses_fts v 
            JOIN main.books b ON v.book_id = b.id 
            WHERE v.verses_fts MATCH :q AND v.version = :v 
            LIMIT 200 OFFSET " . (int)($_GET['offset'] ?? 0)
        );
        $stmt->execute([':q' => $clean, ':v' => $version]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$r) {
            $r['text'] = TextService::sanitizeHTML($r['text']);
            $r['text'] = str_replace(['[[MARK]]', '[[/MARK]]'], ['<mark>', '</mark>'], $r['text']);
        }
        echo json_encode(["results" => $results, "count" => $total]);
    } else {
        throw new Exception("Unknown action: $action");
    }

} catch (Exception $e) {
    http_response_code(400); // Bad Request or Server Error
    echo json_encode(["error" => $e->getMessage()]);
}