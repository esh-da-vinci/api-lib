<?php

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload
use ESHDaVinci\API\Client;

/**
 * Keep in mind that this is not a full test suite.
 * That is unncessary for this project
*/

// IMPORTANT: If you want to test this, you need to create a credentials.php file that has $key and $secret in the tests dir.
// This is not included in GIT!
include "credentials.php";

$client = new Client(
  $key,
  $secret
);

echo "getListOfNames()\n";
var_dump($client->getListOfNames(true));
echo "============\n";
echo "authenticate(2, 12345)\n";
var_dump($client->authenticate(2, "12345"));
echo "============\n";
echo "getNameByID(2)\n";
var_dump($client->getNameByID(2));
