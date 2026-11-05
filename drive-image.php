<?php
ob_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/vendor/autoload.php';

    $fileId = $_GET['id'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $fileId)) {
        throw new RuntimeException('Invalid Google Drive file ID.');
    }

    $client = new Google\Client();

    $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
    $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));

    $refreshToken = getenv('GOOGLE_REFRESH_TOKEN');

    if (!$refreshToken) {
        throw new RuntimeException('Missing Google refresh token.');
    }

    $client->refreshToken($refreshToken);

    $service = new Google\Service\Drive($client);

    $file = $service->files->get($fileId, [
        'fields' => 'id,name,mimeType'
    ]);

    $mimeType = $file->getMimeType();

    if (!in_array($mimeType, [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ], true)) {
        throw new RuntimeException('Unsupported image type.');
    }

    $response = $service->files->get($fileId, [
        'alt' => 'media'
    ]);

    $imageData = $response->getBody()->getContents();

    if (empty($imageData)) {
        throw new RuntimeException('Downloaded image is empty.');
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . strlen($imageData));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');

    echo $imageData;
    exit;

} catch (Throwable $e) {

    error_log('Drive image error: ' . $e->getMessage());

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');

    echo 'Unable to load product image.';
    exit;
}
