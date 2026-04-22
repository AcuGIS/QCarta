<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid id');
}

$stmt = $pdo->prepare('DELETE FROM maps WHERE id = ?');
$stmt->execute([$id]);

header('Location: index.php');
exit;
