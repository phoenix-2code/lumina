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

        

        $stmt = $db->prepare("

            SELECT v.id, v.verse, v.text, v.strongs, vm.modules 

            FROM verses v 

            JOIN books b ON v.book_id = b.id 

            LEFT JOIN verse_modules vm ON v.id = vm.verse_id

            WHERE b.name = :book AND v.chapter = :chapter AND v.version = :version

        ");

        $stmt->execute([':book' => $book, ':chapter' => $chapter, ':version' => $version]);

        $verses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        

        if ($interlinear === 'true') {

            foreach ($verses as &$v) {

                if (!empty($v['strongs'])) {

                    $html = "";

                    foreach (explode(' ', $v['strongs']) as $w) {

                        $p = explode('|', $w);

                        $word = TextService::sanitizeHTML($p[0]); // Sanitize word

                        $code = $p[1] ?? '';

                        if ($code && $code !== 'H0' && $code !== 'G0') {

                            $cleanCode = preg_replace('/[^HG0-9]/', '', $code);

                            $punctuation = TextService::sanitizeHTML(str_replace($cleanCode, '', $code));

                            $lexType = (strpos($cleanCode, 'G') === 0) ? 'strong_greek' : 'strong_hebrew';

                            

                            $stmtL = $db->prepare("SELECT transliteration FROM lexicons WHERE id = ?");

                            $stmtL->execute([$cleanCode]);

                            $translit = $stmtL->fetchColumn();

                            $tag = TextService::sanitizeHTML($translit ?: $cleanCode);

                            $html .= "$word <span class='strongs-tag' onclick=\"event.stopPropagation(); showDef('$cleanCode', '$lexType')\">&lt;$tag&gt;</span>$punctuation ";

                        } else { $html .= "$word "; }

                    }

                    $v['text'] = trim($html);

                } else {

                    $v['text'] = TextService::sanitizeHTML($v['text']);

                }

            }

        } else {

            foreach ($verses as &$v) {

                $v['text'] = TextService::sanitizeHTML($v['text']);

            }

        }

        echo json_encode(["verses" => $verses]);

    // --- COMMENTARY ---
    } elseif ($action == 'commentary') {
        $book = $_GET['book'];
        $chapter = (int)$_GET['chapter'];
        $verse = (int)$_GET['verse'];
        $module = strtolower($_GET['module'] ?? 'mhc');
        
        // 1. Safety Check: Does the table exist?
        $table = "commentary_{$module}";
        $check = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
        $check->execute([$table]);
        if (!$check->fetch()) {
            echo json_encode(["text" => "Commentary module '$module' is not installed or available."]);
            exit;
        }

        // 2. Source of Truth: Get Global ID from the verses table itself (much more reliable than Helper.php)
        $stmtVid = $db->prepare("SELECT v.id, b.id as book_id FROM verses v JOIN books b ON v.book_id = b.id WHERE b.name = ? AND v.chapter = ? AND v.verse = ? LIMIT 1");
        $stmtVid->execute([$book, $chapter, $verse]);
        $row = $stmtVid->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            echo json_encode(["text" => "Reference not found in database."]);
            exit;
        }
        
        $vid = (int)$row['id'];
        $bookId = (int)$row['book_id'];

        // 3. Find the closest commentary entry at or before this verse, but within the same book
        // Note: We use book_id to bound the search so we don't bleed into other books
        $stmt = $db->prepare("
            SELECT c.text 
            FROM {$table} c
            JOIN verses v ON c.verse_id = v.id
            WHERE c.verse_id <= ? AND v.book_id = ?
            ORDER BY c.verse_id DESC 
            LIMIT 1
        ");
        $stmt->execute([$vid, $bookId]);
        
        $text = $stmt->fetchColumn();
        echo json_encode(["text" => TextService::formatCommentary($text) ?: "No commentary found for this section."]);

    // --- CROSS REFS ---
    } elseif ($action == 'xrefs') {
        $book = $_GET['book'];
        $chapter = (int)$_GET['chapter'];
        $verse = (int)$_GET['verse'];
        $vid = Helper::getGlobalVerseID($book, $chapter, $verse);
        
        $stmt = $db->prepare("SELECT to_id FROM cross_references WHERE from_id = ?");
        $stmt->execute([$vid]);
        $refs = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $r = Helper::getVerseRefFromID($id);
            if ($r) $refs[] = $r;
        }
        echo json_encode(["xrefs" => $refs]);

    // --- DICTIONARY ---
    } elseif ($action == 'definition') {
        $term = $_GET['term'] ?? '';
        $type = $_GET['type'] ?? 'dictionary';
        $module = $_GET['module'] ?? 'EASTON';
        
        if ($type == 'strong_hebrew' || $type == 'strong_greek') {
            $stmt = $db->prepare("SELECT definition FROM lexicons WHERE id = ?");
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
            $stmt = $db->query("SELECT id, transliteration FROM lexicons WHERE id LIKE '".($m=='HEBREW'?'H':'G')."%' ORDER BY length(id), id");
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
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'commentary_%'")->fetchAll(PDO::FETCH_COLUMN);
        $mods = array_map(function($t){ return strtoupper(str_replace('commentary_', '', $t)); }, $tables);
        sort($mods); echo json_encode(["modules" => $mods]);

    // --- SEARCH ---
    } elseif ($action == 'search') {
        $q = $_GET['q'] ?? '';
        $version = $_GET['version'] ?? 'KJV';
        $scope = $_GET['scope'] ?? 'ALL';
        
        $clean = preg_replace('/[^a-zA-Z0-9 ]/', '', $q);
        if (!$clean) { echo json_encode(["results" => [], "count" => 0]); exit; }

        $scopeSql = ""; $params = [':q' => $clean, ':v' => $version];
        if ($scope == 'OT') $scopeSql = "AND v.book_id <= 39";
        elseif ($scope == 'NT') $scopeSql = "AND v.book_id >= 40";
        elseif ($scope != 'ALL') {
            $bid = $db->prepare("SELECT id FROM books WHERE name = ?"); $bid->execute([$scope]);
            $bid_val = $bid->fetchColumn();
            if ($bid_val) { $scopeSql = "AND v.book_id = :bid"; $params[':bid'] = $bid_val; }
        }

        $count = $db->prepare("SELECT COUNT(*) FROM verses_fts v WHERE v.verses_fts MATCH :q AND version = :v $scopeSql");
        $count->execute($params);
        $total = $count->fetchColumn();

        $stmt = $db->prepare("SELECT b.name as book_name, v.chapter, v.verse, highlight(verses_fts, 0, '[[MARK]]', '[[/MARK]]') as text FROM verses_fts v JOIN books b ON v.book_id = b.id WHERE v.verses_fts MATCH :q AND version = :v $scopeSql ORDER BY v.book_id, v.chapter, v.verse LIMIT 200");
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$r) {
            // 1. Sanitize everything
            $r['text'] = TextService::sanitizeHTML($r['text']);
            // 2. Convert our unique markers back to HTML <mark>
            $r['text'] = str_replace('[[MARK]]', '<mark>', $r['text']);
            $r['text'] = str_replace('[[/MARK]]', '</mark>', $r['text']);
        }
        
        echo json_encode(["results" => $results, "count" => $total]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
