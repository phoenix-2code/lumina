<?php
// Simple PHP Bible Reader for the extracted SQLite DB
// Run with: php -S localhost:8000

$db = new PDO('sqlite:bible_app.db');

$book = $_GET['book'] ?? 'Genesis';
$chapter = $_GET['chapter'] ?? 1;

// Get Books for dropdown
$books = $db->query("SELECT name FROM books")->fetchAll(PDO::FETCH_COLUMN);

// Get Verses
$stmt = $db->prepare("SELECT v.verse, v.text FROM verses v JOIN books b ON v.book_id = b.id WHERE b.name = :book AND v.chapter = :chapter");
$stmt->execute([':book' => $book, ':chapter' => $chapter]);
$verses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Navigation logic (simplified)
$next_chapter = $chapter + 1;
$prev_chapter = $chapter > 1 ? $chapter - 1 : 1;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bible App (PHP)</title>
    <style>
        body { font-family: 'Georgia', serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; color: #333; }
        header { border-bottom: 2px solid #eee; margin-bottom: 20px; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        h1 { margin: 0; font-size: 1.5rem; }
        .nav { margin-bottom: 20px; display: flex; gap: 10px; }
        .verse { margin-bottom: 10px; }
        .verse-num { font-weight: bold; font-size: 0.8em; color: #888; vertical-align: super; margin-right: 5px; }
        select, button { padding: 5px; }
        a { text-decoration: none; color: #007bff; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<header>
    <h1>Bible Reader (Extracted Data)</h1>
    <form method="GET" style="display:inline;">
        <select name="book" onchange="this.form.submit()">
            <?php foreach ($books as $b): ?>
                <option value="<?= $b ?>" <?= $b === $book ? 'selected' : '' ?>><?= $b ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="chapter" value="<?= $chapter ?>" style="width: 50px;" min="1">
        <button type="submit">Go</button>
    </form>
</header>

<div class="nav">
    <?php if($chapter > 1): ?>
        <a href="?book=<?= urlencode($book) ?>&chapter=<?= $prev_chapter ?>">« Previous Chapter</a>
    <?php endif; ?>
    <span style="flex-grow:1;"></span>
    <a href="?book=<?= urlencode($book) ?>&chapter=<?= $next_chapter ?>">Next Chapter »</a>
</div>

<main>
    <?php if (empty($verses)): ?>
        <p>No verses found for <?= htmlspecialchars($book) ?> Chapter <?= htmlspecialchars($chapter) ?>.</p>
    <?php else: ?>
        <?php foreach ($verses as $v): ?>
            <div class="verse">
                <span class="verse-num"><?= $v['verse'] ?></span>
                <?= htmlspecialchars($v['text']) ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

</body>
</html>
