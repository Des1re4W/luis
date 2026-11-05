```php
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

/**
 * Upload product image to Google Drive.
 */
function uploadProductImageToDrive(string $filePath, string $fileName): string
{
    $client = getGoogleDriveClient();

    // Retrieve saved OAuth access token
    $token = json_decode(
        file_get_contents(__DIR__ . '/google_token.json'),
        true
    );

    if (!$token) {
        throw new Exception('Google Drive token not found.');
    }

    $client->setAccessToken($token);

    if ($client->isAccessTokenExpired()) {
        if (!$client->getRefreshToken()) {
            throw new Exception('Google Drive refresh token missing. Reauthorize Google.');
        }

        $client->fetchAccessTokenWithRefreshToken(
            $client->getRefreshToken()
        );

        file_put_contents(
            __DIR__ . '/google_token.json',
            json_encode($client->getAccessToken())
        );
    }

    $drive = new Google\Service\Drive($client);

    $fileMetadata = new Google\Service\Drive\DriveFile([
        'name' => $fileName
    ]);

    $uploadedFile = $drive->files->create($fileMetadata, [
        'data' => file_get_contents($filePath),
        'mimeType' => mime_content_type($filePath),
        'uploadType' => 'multipart',
        'fields' => 'id,name,webViewLink'
    ]);

    return $uploadedFile->id;
}
```