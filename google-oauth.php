<?php

session_start();

require_once __DIR__ . '/config/google_drive.php';

$client = getGoogleDriveClient();

$authUrl = $client->createAuthUrl();

header('Location: ' . $authUrl);
exit;
