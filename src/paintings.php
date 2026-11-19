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

        $title = trim((string) ($input['title'] ?? ''));
        $artist = trim((string) ($input['artist'] ?? ''));
        $year = $input['year'] ?? null;
        $image_url = $input['image_url'] ?? null;
        $description = $input['description'] ?? null;

        if ($title === '' || $artist === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Title and artist are required']);
            exit;
        }

        if ($year === null || $year === '') {
            $year = null;
        } elseif (!is_numeric($year)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Year must be a number']);
            exit;
        } else {
            $year = (int) $year;
        }

        if ($image_url === null || $image_url === '') {
            $image_url = null;
        } elseif (!is_string($image_url) || filter_var($image_url, FILTER_VALIDATE_URL) === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Image URL must be a valid URL']);
            exit;
        }

        if ($description === null) {
            $description = null;
        } else {
            $description = trim((string) $description);
            if ($description === '') {
                $description = null;
            }
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

    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        $id = is_string($id) ? trim($id) : '';

        if ($id === '' || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'A valid painting id is required']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM paintings WHERE id = :id');
        $stmt->execute([':id' => (int) $id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Painting not found']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Painting deleted']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
} catch (Throwable $e) {
    error_log('Paintings API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
