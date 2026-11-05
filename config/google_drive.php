<?php

require_once __DIR__ . '/../vendor/autoload.php';

function getGoogleDriveClient(): Google\Client
{
    $clientId = getenv('GOOGLE_CLIENT_ID');
    $clientSecret = getenv('GOOGLE_CLIENT_SECRET');
    $refreshToken = getenv('GOOGLE_REFRESH_TOKEN');

    if (!$clientId || !$clientSecret || !$refreshToken) {
        throw new RuntimeException(
            'Missing Google OAuth environment variables.'
        );
    }

    $client = new Google\Client();

    $client->setClientId($clientId);
    $client->setClientSecret($clientSecret);
    $client->setAccessType('offline');
    $client->setScopes([
        Google\Service\Drive::DRIVE
    ]);

    $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);

    if (isset($token['error'])) {
        error_log(
            'Google OAuth error: ' .
            ($token['error_description'] ?? $token['error'])
        );

        throw new RuntimeException(
            'Unable to authenticate with Google Drive. Check your OAuth credentials.'
        );
    }

    if (empty($token['access_token'])) {
        throw new RuntimeException(
            'Google did not return an access token.'
        );
    }

    return $client;
}
