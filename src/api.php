<?php
require_once 'api/Database.php';
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
    $db = Database::connect();
    $action = $_GET['action'] ?? '';

    // --- BIBLE TEXT ---
    if ($action == 'text') {
        $book = $_GET['book'] ?? 'Genesis';
        $chapter = (int)($_GET['chapter'] ?? 1);
        $version = $_GET['version'] ?? 'KJV';
        $interlinear = $_GET['interlinear'] ?? 'false';
        
        // New Query: Includes dynamic commentary availability check
        $stmt = $db->prepare("
            SELECT 
                v.id, 
                v.verse, 
                v.text,
                (SELECT GROUP_CONCAT(c.abbreviation) 
                 FROM commentary_entries ce 
                 JOIN commentaries c ON ce.commentary_id = c.id 
                 WHERE ce.verse_id = v.id) as modules
            FROM verses v 
            JOIN books b ON v.book_id = b.id 
            WHERE b.name = :book AND v.chapter = :chapter AND v.version = :version
        ");
        $stmt->execute([':book' => $book, ':chapter' => $chapter, ':version' => $version]);
        $verses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process interlinear/strongs from the new verse_words table if needed
        foreach ($verses as &$v) {
            if ($interlinear === 'true') {
                $stmtW = $db->prepare("
                    SELECT vw.strongs_id, l.transliteration 
                    FROM verse_words vw
                    JOIN lexicon l ON vw.strongs_id = l.id
                    WHERE vw.verse_id = ?
                    ORDER BY vw.position ASC
                ");
                $stmtW->execute([$v['id']]);
                $words = $stmtW->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($words)) {
                    $html = $v['text'];
                    // NOTE: Robust interlinear requires word-by-word mapping. 
                    // For now, we'll append the Strong's tags to the text as a quick fix 
                    // or highlight specific key words if the database supports it.
                    // Given the current structure, we'll append the tags at the end of the verse 
                    // or use a placeholder approach if preferred.
                    foreach($words as $w) {
                        $tag = $w['transliteration'] ?: $w['strongs_id'];
                        $lexType = (strpos($w['strongs_id'], 'G') === 0) ? 'strong_greek' : 'strong_hebrew';
                        $html .= " <span class='strongs-tag' onclick=\"event.stopPropagation(); showDef('{$w['strongs_id']}', '$lexType')\">&lt;$tag&gt;</span>";
                    }
                    $v['text'] = $html;
                }
            }
            $v['text'] = TextService::sanitizeHTML($v['text']);
        }
        
        echo json_encode(["verses" => $verses]);

    // --- COMMENTARY ---
    } elseif ($action == 'commentary') {
        $book = $_GET['book'];
        $chapter = (int)$_GET['chapter'];
        $verse = (int)$_GET['verse'];
        $module = strtolower($_GET['module'] ?? 'mhc');
        
        // 1. Get the sequential ID for this verse
        $stmtId = $db->prepare("
            SELECT v.id 
            FROM verses v 
            JOIN books b ON v.book_id = b.id 
            WHERE b.name = ? AND v.chapter = ? AND v.verse = ? AND v.version = 'KJV'
            LIMIT 1
        ");
        $stmtId->execute([$book, $chapter, $verse]);
        $vid = $stmtId->fetchColumn();

        if (!$vid) {
            echo json_encode(["text" => "Verse not found."]);
            exit;
        }

        // 2. Fetch from unified commentary table
        $stmt = $db->prepare("
            SELECT ce.text
            FROM commentary_entries ce
            JOIN commentaries c ON ce.commentary_id = c.id
            WHERE c.abbreviation = ? AND ce.verse_id = ?
        ");
        $stmt->execute([$module, $vid]);
        $text = $stmt->fetchColumn();
        
        echo json_encode(["text" => TextService::formatCommentary($text) ?: "No commentary found for this specific verse."]);

    // --- CROSS REFS ---
    } elseif ($action == 'xrefs') {
        $book = $_GET['book'];
        $chapter = (int)$_GET['chapter'];
        $verse = (int)$_GET['verse'];
        
        // Get global ID
        $stmtId = $db->prepare("SELECT v.id FROM verses v JOIN books b ON v.book_id = b.id WHERE b.name = ? AND v.chapter = ? AND v.verse = ? LIMIT 1");
        $stmtId->execute([$book, $chapter, $verse]);
        $vid = $stmtId->fetchColumn();

        $stmt = $db->prepare("
            SELECT b.name as book, v.chapter, v.verse 
            FROM cross_references xr
            JOIN verses v ON xr.to_verse_id = v.id
            JOIN books b ON v.book_id = b.id
            WHERE xr.from_verse_id = ?
        ");
        $stmt->execute([$vid]);
        echo json_encode(["xrefs" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    // --- DICTIONARY ---
    } elseif ($action == 'definition') {
        $term = $_GET['term'] ?? '';
        $type = $_GET['type'] ?? 'dictionary';
        $module = $_GET['module'] ?? 'EASTON';
        
        if ($type == 'strong_hebrew' || $type == 'strong_greek') {
            $stmt = $db->prepare("SELECT definition FROM lexicon WHERE id = ?");
            $stmt->execute([$term]);
        } else {
            $stmt = $db->prepare("SELECT definition FROM dictionaries WHERE topic = ? AND module = ? COLLATE NOCASE");
            $stmt->execute([$term, strtoupper($module)]);
        }
        $text = $stmt->fetchColumn();
        echo json_encode(["definition" => $text ? TextService::formatCommentary($text) : "Not found."]);

    // --- TOPICS ---
    } elseif ($action == 'topics') {
        $m = strtoupper($_GET['module'] ?? 'EASTON');
        if ($m == 'HEBREW' || $m == 'GREEK') {
            $stmt = $db->query("SELECT id, transliteration FROM lexicon WHERE id LIKE '".($m=='HEBREW'?'H':'G')."%' ORDER BY length(id), id");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $formatted = [];
            foreach($results as $r) {
                $label = $r['id'] . ($r['transliteration'] ? " - " . $r['transliteration'] : "");
                $formatted[] = ["id" => $r['id'], "label" => $label];
            }
            echo json_encode(["topics" => $formatted]);
        } else {
            $stmt = $db->prepare("SELECT topic FROM dictionaries WHERE module = ? ORDER BY topic ASC");
            $stmt->execute([$m]);
            $topics = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $formatted = [];
            foreach($topics as $t) $formatted[] = ["id" => $t, "label" => $t];
            echo json_encode(["topics" => $formatted]);
        }

    // --- LISTS ---
    } elseif ($action == 'version_list') {
        echo json_encode(["versions" => $db->query("SELECT DISTINCT version FROM verses ORDER BY version")->fetchAll(PDO::FETCH_COLUMN)]);

    } elseif ($action == 'commentary_list') {
        $mods = $db->query("SELECT abbreviation FROM commentaries ORDER BY abbreviation")->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(["modules" => array_map('strtoupper', $mods)]);

    // --- SEARCH ---
    } elseif ($action == 'search') {
        $q = $_GET['q'] ?? '';
        $version = $_GET['version'] ?? 'KJV';
        $scope = $_GET['scope'] ?? 'ALL';
        
        $clean = preg_replace('/[^a-zA-Z0-9 ]/', '', $q);
        if (!$clean) { echo json_encode(["results" => [], "count" => 0]); exit; }

        $scopeSql = ""; $params = [':q' => $clean, ':v' => $version];
        if ($scope == 'OT') $scopeSql = "AND book_id <= 39";
        elseif ($scope == 'NT') $scopeSql = "AND book_id >= 40";
        elseif ($scope != 'ALL') {
            $bid = $db->prepare("SELECT id FROM books WHERE name = ?"); $bid->execute([$scope]);
            $bid_val = $bid->fetchColumn();
            if ($bid_val) { $scopeSql = "AND book_id = :bid"; $params[':bid'] = $bid_val; }
        }

        $count = $db->prepare("SELECT COUNT(*) FROM verses_fts WHERE verses_fts MATCH :q AND version = :v $scopeSql");
        $count->execute($params);
        $total = $count->fetchColumn();

        $stmt = $db->prepare("
            SELECT b.name as book_name, v.chapter, v.verse, highlight(verses_fts, 0, '[[MARK]]', '[[/MARK]]') as text 
            FROM verses_fts v 
            JOIN books b ON v.book_id = b.id 
            WHERE verses_fts MATCH :q AND version = :v $scopeSql 
            ORDER BY v.book_id, v.chapter, v.verse 
            LIMIT 200
        ");
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$r) {
            $r['text'] = TextService::sanitizeHTML($r['text']);
            $r['text'] = str_replace('[[MARK]]', '<mark>', $r['text']);
            $r['text'] = str_replace('[[/MARK]]', '</mark>', $r['text']);
        }
        
        echo json_encode(["results" => $results, "count" => $total]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}