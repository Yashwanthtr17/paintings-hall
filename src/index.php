<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$rows = '';
$total = 0;
$artistCount = 0;
$yearRange = '—';

try {
    $pdo = getDb();
    $stmt = $pdo->query("SELECT id, title, artist, year, image_url, description, created_at FROM paintings ORDER BY created_at DESC");
    $paintings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($paintings);

    $artists = array_filter(array_map(
        static fn(array $painting): string => trim((string) ($painting['artist'] ?? '')),
        $paintings
    ));
    $artistCount = count(array_unique($artists));

    $years = array_filter(array_map(
        static fn(array $painting): int => (int) ($painting['year'] ?? 0),
        $paintings
    ), static fn(int $year): bool => $year > 0);
    $yearRange = $years ? min($years) . '–' . max($years) : '—';

    if (!$paintings) {
        $rows = '<div class="empty-state"><strong>No paintings yet</strong>Add the first painting using the form.</div>';
    }

    foreach ($paintings as $painting) {
        $id = (int) $painting['id'];
        $title = htmlspecialchars($painting['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $artist = htmlspecialchars($painting['artist'] ?? '', ENT_QUOTES, 'UTF-8');
        $year = !empty($painting['year']) ? htmlspecialchars((string) $painting['year'], ENT_QUOTES, 'UTF-8') : '';
        $imageUrl = !empty($painting['image_url']) ? htmlspecialchars($painting['image_url'], ENT_QUOTES, 'UTF-8') : '';
        $description = !empty($painting['description'])
            ? nl2br(htmlspecialchars($painting['description'], ENT_QUOTES, 'UTF-8'))
            : '';

        $imageTag = $imageUrl
            ? '<img src="' . $imageUrl . '" alt="' . $title . '" class="painting-image" loading="lazy">'
            : '<div class="painting-image-placeholder">No image</div>';
        $yearBadge = $year ? '<span class="painting-year-badge">' . $year . '</span>' : '';
        $descriptionHtml = $description ? '<p class="painting-description">' . $description . '</p>' : '';

        $rows .= <<<HTML
<article class="painting-card" data-id="{$id}">
    <div class="painting-media">
        {$imageTag}
        {$yearBadge}
    </div>
    <div class="painting-body">
        <p class="painting-artist">{$artist}</p>
        <h3 class="painting-title">{$title}</h3>
        {$descriptionHtml}
    </div>
</article>
HTML;
    }
} catch (Throwable $e) {
    error_log('Failed to load paintings: ' . $e->getMessage());
    http_response_code(500);
    $rows = '<div class="empty-state empty-state-error"><strong>Unable to load paintings</strong>Please try again later.</div>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Paintings Hall — a simple painting gallery built with Docker, PostgreSQL, Jenkins, and Grafana.">
    <title>Paintings Hall</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="/">
                <span class="brand-mark">PH</span>
                <span class="brand-copy">
                    <span class="brand-name">Paintings Hall</span>
                    <span class="brand-subtitle">Local DevOps gallery</span>
                </span>
            </a>
            <span class="header-badge">Docker · Jenkins · Grafana</span>
        </div>
    </header>

    <main>
        <section class="hero container">
            <div class="hero-copy">
                <p class="eyebrow">The collection</p>
                <h1>A quiet home for <em>remarkable</em> paintings.</h1>
                <p class="hero-text">
                    A small gallery powered by PHP, PostgreSQL, Docker, Jenkins, and Grafana.
                </p>
            </div>

            <div class="stats" aria-label="Collection statistics">
                <div class="stat">
                    <span class="stat-value" id="stat-total"><?php echo $total; ?></span>
                    <span class="stat-label">Paintings</span>
                </div>
                <div class="stat">
                    <span class="stat-value" id="stat-artists"><?php echo $artistCount; ?></span>
                    <span class="stat-label">Artists</span>
                </div>
                <div class="stat">
                    <span class="stat-value" id="stat-years"><?php echo htmlspecialchars($yearRange, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="stat-label">Years</span>
                </div>
            </div>
        </section>

        <div class="container content-grid">
            <section class="gallery-section" aria-labelledby="gallery-heading">
                <div class="section-heading">
                    <h2 id="gallery-heading">Gallery</h2>
                    <span class="section-meta" id="gallery-count"><?php echo $total; ?> <?php echo $total === 1 ? 'piece' : 'pieces'; ?></span>
                </div>
                <div class="gallery" id="gallery">
                    <?php echo $rows; ?>
                </div>
            </section>

            <aside class="add-form-card" aria-labelledby="form-heading">
                <p class="eyebrow">Add to the collection</p>
                <h2 id="form-heading">New painting</h2>
                <p class="form-intro">A title and artist are all you need to get started.</p>

                <form id="add-painting-form" novalidate>
                    <div class="form-group">
                        <label for="title">Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" placeholder="The Starry Night" required maxlength="255">
                    </div>

                    <div class="form-group">
                        <label for="artist">Artist <span class="required">*</span></label>
                        <input type="text" id="artist" name="artist" placeholder="Vincent van Gogh" required maxlength="255">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="year">Year</label>
                            <input type="number" id="year" name="year" placeholder="1889" min="0" max="9999">
                        </div>
                        <div class="form-group">
                            <label for="image_url">Image URL</label>
                            <input type="url" id="image_url" name="image_url" placeholder="https://...">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4" placeholder="A short note about the painting..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" id="submit-button">Add painting</button>
                </form>

                <div id="form-message" role="alert" aria-live="polite"></div>
            </aside>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <span>Paintings Hall</span>
            <span>Docker · PostgreSQL · Grafana · Jenkins</span>
        </div>
    </footer>

    <script src="public/js/app.js"></script>
</body>
</html>
