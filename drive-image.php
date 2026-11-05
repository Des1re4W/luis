
<?php
require_once __DIR__ . '/vendor/autoload.php';

$fileId = $_GET['id'] ?? '';

if (!preg_match('/^[a-zA-Z0-9_-]+$/', $fileId)) {
    http_response_code(400);
    exit('Invalid Google Drive file ID.');
}

$client = new Google\Client();

$client->setClientId(getenv('GOOGLE_CLIENT_ID'));
$client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
$client->setAccessType('offline');

$refreshToken = getenv('GOOGLE_REFRESH_TOKEN');

if (!$refreshToken) {
    http_response_code(500);
    exit('Missing Google refresh token.');
}

$client->refreshToken($refreshToken);

$service = new Google\Service\Drive($client);

try {
    $response = $service->files->get($fileId, [
        'alt' => 'media'
    ]);

    $imageData = $response->getBody()->getContents();

    $mimeType = $response->getHeaders()['Content-Type'][0]
        ?? 'application/octet-stream';

    if (strpos($mimeType, 'image/') !== 0) {
        http_response_code(415);
        exit('File is not an image.');
    }

    header('Content-Type: ' . $mimeType);
    header('Cache-Control: private, max-age=3600');

    echo $imageData;

} catch (Exception $e) {
    error_log('Google Drive image error: ' . $e->getMessage());
    http_response_code(502);
    exit('Unable to load Google Drive image.');
}
