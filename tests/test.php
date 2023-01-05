<?php

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload
use ESHDaVinci\API\Client;

/**
 * Keep in mind that this is not a full test suite.
 * That is unncessary for this project
 */

// IMPORTANT: If you want to test this, you need to create a credentials.php file that has $token in the tests dir.
// This is not included in GIT!
include "credentials.php";

$client = new Client(
    $token,
);

echo "getListOfNames() - expecting only active\n";
var_dump($client->getListOfNames(true));

echo "============\n";
echo "createPerson\n";
$member = $client->createPerson([
    "address_street" => "Amazonenlaan",
    "address_number" => "4",
    "address_zip" => "5631KW",
    "address_city" => "Eindhoven",
    "address_country" => "The Netherlands",
    "first_name" => "A.dtmin",
    "infix" => "von",
    "last_name" => "Lassie",
    "phone_home" => "000000",
    "email_primary" => "admin@eshdavinci.nl",
    "birthdatebug" => "2000-01-01",
    "department_id" => "tue",
    "study" => "None",
]);
var_dump("Created " . $member["id"]);
assert(intval($meember['id']) > 0, "Member ID not valid");
echo "============\n";
echo "getMember(ID)\n";
var_dump($client->getMember($member["id"]));
echo "============\n";
echo "setNewPassword(ID, 12345)\n";
$set_pin = $client->setNewPassword($member["id"], "12345");
if ($set_pin !== true) {
    var_dump("Pin should be set correctly");
    die();
}
echo "============\n";
echo "authenticate(ID, 12345)\n";
$result = $client->authenticate($member["id"], "12345") === true;
if (!$result) {
    var_dump("Cannot login using set pin, check authenticate function");
    die();
}
echo "============\n";
echo "hasToSetPassword\n";
$result = $client->hasToSetPassword($member["id"]) === false;
if (!$result) {
    var_dump("Password should be set.");
    die();
}
echo "============\n";
echo "getMemberList()\n";
var_dump("All member count: ", count($client->getMemberList(false)));
var_dump("Active member count: ", count($client->getMemberList(true)));
echo "============\n";
echo "getNameByID\n";
var_dump($client->getNameByID($member["id"]));
echo "=============\n";
var_dump("All done, don't forget to delete test users");
