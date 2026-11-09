
<?php
ob_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    // Load Composer
    require_once __DIR__ . '/vendor/autoload.php';

    // Load environment variables
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();

    // Get Google credentials
    $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
    $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
    $refreshToken = $_ENV['GOOGLE_REFRESH_TOKEN'] ?? '';

    if (!$clientId || !$clientSecret || !$refreshToken) {
        throw new RuntimeException('Missing Google OAuth credentials.');
    }

    // Validate Google Drive file ID
    $fileId = $_GET['id'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $fileId)) {
        throw new RuntimeException('Invalid Google Drive file ID.');
    }

    // Initialize Google Client
    $client = new Google\Client();

    $client->setClientId($clientId);
    $client->setClientSecret($clientSecret);

    // Refresh access token
    $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);

    if (isset($token['error'])) {
        throw new RuntimeException(
            'Google OAuth error: ' . ($token['error_description'] ?? $token['error'])
        );
    }

    if (empty($token['access_token'])) {
        throw new RuntimeException('Unable to obtain Google access token.');
    }

    // Initialize Google Drive
    $service = new Google\Service\Drive($client);

    // Get image metadata
    $file = $service->files->get($fileId, [
        'fields' => 'id,name,mimeType'
    ]);

    $mimeType = $file->getMimeType();

    // Validate image type
    $allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    if (!in_array($mimeType, $allowedTypes, true)) {
        throw new RuntimeException('Unsupported image type.');
    }

    // Download image
    $response = $service->files->get($fileId, [
        'alt' => 'media'
    ]);

    $imageData = $response->getBody()->getContents();

    if (empty($imageData)) {
        throw new RuntimeException('Downloaded image is empty.');
    }

    // Clear output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Return image
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
