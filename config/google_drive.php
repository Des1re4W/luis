
<?php

require_once __DIR__ . '/../vendor/autoload.php';

function getGoogleDriveClient(): Google\Client
{
    $client = new Google\Client();

    $client->setClientId(getenv('GOOGLE_CLIENT_ID'));
    $client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET'));
    $client->setRedirectUri(getenv('GOOGLE_REDIRECT_URI'));

    $client->setAccessType('offline');
    $client->setPrompt('consent');
    $client->setScopes([
        Google\Service\Drive::DRIVE_FILE
    ]);

    return $client;
}
