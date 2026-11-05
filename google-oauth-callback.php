<?php

require_once __DIR__ . '/config/google_drive.php';

if (isset($_GET['error'])) {
    exit('Google authorization error: ' .
        htmlspecialchars($_GET['error']));
}

if (empty($_GET['code'])) {
    exit('No authorization code received. Please start again at google-oauth.php');
}

try {
    $client = getGoogleDriveClient();

    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        throw new Exception($token['error_description'] ?? $token['error']);
    }

    if (empty($token['refresh_token'])) {
        exit('Refresh token missing. Please authorize again.');
    }

    echo 'Google Drive Connected Successfully!';

    echo '<p>Add this token to Render as GOOGLE_REFRESH_TOKEN.</p>';

    echo '<textarea style="width:100%;height:100px;">' .
        htmlspecialchars($token['refresh_token']) .
        '</textarea>';

} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . htmlspecialchars($e->getMessage());
}