<?php

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload
use ESHDaVinci\API\Client;

/**
 * Keep in mind that this is not a full test suite.
 * That is unncessary for this project
*/

// IMPORTANT: If you want to test this, you need to create a credentials.php file that has $key and $secret in the tests dir.
// This is not included in GIT!

$client = new Client(
  $key,
  $secret
);
