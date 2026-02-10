<?php
/**
 * Lumina Router - v1.3.7
 * Central entry point for all API requests.
 */

require_once 'api/DatabaseManager.php';
require_once 'api/Helper.php';
require_once 'api/TextService.php';
require_once 'api/BibleService.php';
require_once 'api/StudyService.php';
require_once 'api/SearchService.php';

header('Content-Type: application/json');

// --- Security Check ---
$allowed_origins = ['http://localhost:8000', 'http://127.0.0.1:8000'];
if (isset($_SERVER['HTTP_ORIGIN']) && !in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    http_response_code(403);
    echo json_encode(["error" => "Forbidden."]);
    exit;
}

try {
    $db = DatabaseManager::getInstance()->getConnection();
    $action = $_GET['action'] ?? '';

    // Initialize Services
    $bible = new BibleService($db);
    $study = new StudyService($db);
    $search = new SearchService($db);

    switch ($action) {
        case 'text':
            echo json_encode(["verses" => $bible->getChapter($_GET)]);
            break;

        case 'commentary':
            echo json_encode(["text" => $study->getCommentary($_GET)]);
            break;

        case 'xrefs':
            echo json_encode(["xrefs" => $study->getCrossReferences($_GET)]);
            break;

        case 'definition':
            echo json_encode(["definition" => $study->getDefinition($_GET)]);
            break;

        case 'topics':
            // Logic for topics is still simple, kept here for now
            $m = strtoupper($_GET['module'] ?? 'EASTON');
            $table = ($m == 'HEBREW' || $m == 'GREEK') ? 'extras.lexicon' : 'extras.dictionaries';
            $field = ($m == 'HEBREW' || $m == 'GREEK') ? 'id' : 'topic';
            $stmt = $db->query("SELECT $field FROM $table " . ($m == 'HEBREW' || $m == 'GREEK' ? "WHERE id LIKE '".($m=='HEBREW'?'H':'G')."%'" : "WHERE module='$m'") . " ORDER BY $field ASC");
            $results = [];
            foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $t) $results[] = ["id" => $t, "label" => $t];
            echo json_encode(["topics" => $results]);
            break;

        case 'version_list':
            echo json_encode(["versions" => $bible->getVersions()]);
            break;

        case 'commentary_list':
            echo json_encode(["modules" => $study->getCommentaryList()]);
            break;

        case 'search':
            echo json_encode($search->search($_GET));
            break;

        default:
            throw new Exception("Invalid action: $action");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["error" => $e->getMessage()]);
}
