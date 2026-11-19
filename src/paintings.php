<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $pdo = getDb();

    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT id, title, artist, year, image_url, description, created_at FROM paintings ORDER BY created_at DESC");
        $paintings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $paintings], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
            exit;
        }

        $title = $input['title'] ?? '';
        $artist = $input['artist'] ?? '';
        $year = $input['year'] ?? null;
        $image_url = $input['image_url'] ?? null;
        $description = $input['description'] ?? null;

        if ($title === '' || $artist === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Title and artist are required']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO paintings (title, artist, year, image_url, description) VALUES (:title, :artist, :year, :image_url, :description)");
        $stmt->execute([
            ':title' => $title,
            ':artist' => $artist,
            ':year' => $year,
            ':image_url' => $image_url,
            ':description' => $description,
        ]);

        $id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => (int) $id], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
