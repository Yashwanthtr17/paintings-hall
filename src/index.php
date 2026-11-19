<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$rows = '';

try {
    $pdo = getDb();
    $stmt = $pdo->query("SELECT id, title, artist, year, image_url, description, created_at FROM paintings ORDER BY created_at DESC");
    $paintings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($paintings as $p) {
        $title = htmlspecialchars($p['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $artist = htmlspecialchars($p['artist'] ?? '', ENT_QUOTES, 'UTF-8');
        $year = !empty($p['year']) ? htmlspecialchars((string) $p['year'], ENT_QUOTES, 'UTF-8') : '';
        $image_url = !empty($p['image_url']) ? htmlspecialchars($p['image_url'], ENT_QUOTES, 'UTF-8') : '';
        $description = !empty($p['description']) ? nl2br(htmlspecialchars($p['description'], ENT_QUOTES, 'UTF-8')) : '';
        $imageTag = $image_url
            ? '<img src="' . $image_url . '" alt="' . $title . '" class="painting-image">'
            : '<div class="painting-image-placeholder">No image</div>';
        $yearHtml = $year ? '<p class="painting-year">' . $year . '</p>' : '';
        $descriptionHtml = $description ? '<p class="painting-description">' . $description . '</p>' : '';
        $rows .= <<<HTML
<article class="painting-card" data-id="{$p['id']}">
    {$imageTag}
    <div class="painting-info">
        <h3 class="painting-title">{$title}</h3>
        <p class="painting-artist">{$artist}</p>
        {$yearHtml}
        {$descriptionHtml}
    </div>
</article>
HTML;
    }
} catch (Throwable $e) {
    error_log('Failed to load paintings: ' . $e->getMessage());
    http_response_code(500);
    $rows = '<p class="error-message">Unable to load paintings. Please try again later.</p>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paintings Hall</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <h1 class="site-title">Paintings Hall</h1>
        </div>
    </header>

    <main class="container">
        <section class="gallery" id="gallery">
            <?php echo $rows; ?>
        </section>

        <section class="add-form-card">
            <h2>Add a Painting</h2>
            <form id="add-painting-form" novalidate>
                <div class="form-group">
                    <label for="title">Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" required maxlength="255">
                </div>
                <div class="form-group">
                    <label for="artist">Artist <span class="required">*</span></label>
                    <input type="text" id="artist" name="artist" required maxlength="255">
                </div>
                <div class="form-group">
                    <label for="year">Year</label>
                    <input type="number" id="year" name="year" min="0" max="9999">
                </div>
                <div class="form-group">
                    <label for="image_url">Image URL</label>
                    <input type="url" id="image_url" name="image_url">
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Add Painting</button>
            </form>
            <div id="form-message" role="alert"></div>
        </section>
    </main>

    <script src="public/js/app.js"></script>
</body>
</html>
