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
    $token,
);

echo "getListOfNames()\n";
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
echo "============\n";
echo "getMember\n";
var_dump($client->getMember($member["id"]));
echo "============\n";
echo "setNewPassword(2, 12345)\n";
$pin_hash = $client->setNewPassword($member["id"], "12345");
var_dump($pin_hash);
echo "============\n";
echo "authenticate(2, 12345)\n";
var_dump($client->authenticate($member["id"], "12345"));
echo "============\n";
echo "hasToSetPassword\n";
var_dump($client->hasToSetPassword($member["id"]));
echo "============\n";
echo "getMemberList(false)\n";
var_dump($client->getMemberList(false));
echo "============\n";
echo "getNameByID\n";
var_dump($client->getNameByID($member["id"]));
echo "=============\n";
$pin_hash_id = $pin_hash['id'];
$client->request("DELETE", "items/PinHashes/$pin_hash_id");
$member_id = $member["id"];
$client->request("DELETE", "items/Members/$member_id");
$address_id = $member["address"];
$client->request("DELETE", "items/MemberAddresses/$address_id");