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
    $client->setScopes([
        Google\Service\Drive::DRIVE_FILE
    ]);

    return $client;
}

function uploadProductImageToDrive(string $filePath, string $fileName): string
{
    $refreshToken = getenv('GOOGLE_REFRESH_TOKEN');

    if (!$refreshToken) {
        throw new Exception('GOOGLE_REFRESH_TOKEN is missing in Render.');
    }

    if (!file_exists($filePath)) {
        throw new Exception('Temporary image file does not exist.');
    }

    $client = getGoogleDriveClient();

    $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);

    if (isset($token['error'])) {
        throw new Exception(
            $token['error_description'] ?? $token['error']
        );
    }

    $client->setAccessToken($token);

    $drive = new Google\Service\Drive($client);

    $metadata = new Google\Service\Drive\DriveFile([
        'name' => $fileName
    ]);

    // Optional: upload into a specific Drive folder.
    $folderId = getenv('GOOGLE_DRIVE_FOLDER_ID');

    if ($folderId) {
        $metadata->setParents([$folderId]);
    }

    $uploadedFile = $drive->files->create($metadata, [
        'data' => file_get_contents($filePath),
        'mimeType' => mime_content_type($filePath),
        'uploadType' => 'multipart',
        'fields' => 'id,name,webViewLink'
    ]);

    $fileId = $uploadedFile->getId();

    if (!$fileId) {
        throw new Exception('Google Drive did not return a file ID.');
    }

    // Allow the website to display the product image publicly.
    $permission = new Google\Service\Drive\Permission([
        'type' => 'anyone',
        'role' => 'reader'
    ]);

    $drive->permissions->create($fileId, $permission);

    return $fileId;
}