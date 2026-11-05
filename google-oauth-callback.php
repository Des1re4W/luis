<?php
require_once __DIR__ . '/config/google_drive.php';

if (!isset($_GET['code'])) {
    exit('Authorization code is missing.');
}

try {
    $client = getGoogleDriveClient();

    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        throw new Exception($token['error_description'] ?? $token['error']);
    }

    if (empty($token['refresh_token'])) {
        exit('No refresh token received. Please restart authorization and grant consent.');
    }

    $refreshToken = $token['refresh_token'];

    echo '<h2>Google Drive Connected Successfully!</h2>';
    echo '<p>Copy this refresh token and add it to your Render environment variables.</p>';
    echo '<textarea rows="6" style="width:100%;">'
        . htmlspecialchars($refreshToken, ENT_QUOTES, 'UTF-8')
        . '</textarea>';

    echo '<p>Variable name: GOOGLE_REFRESH_TOKEN</p>';

} catch (Throwable $e) {
    http_response_code(500);
    echo 'Google authorization failed: '
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
