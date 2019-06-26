<?php

namespace ESHDaVinci\API;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Client as GuzzleClient;
use ESHDaVinci\API\Helpers\KeyMiddleware;

/**
*  Main API Class
*
*  Create an instance of this class with your API credentials, obtained from the Communicacie,
*  and use the methods on the obtained client object to call the required functions.
*
*  @author Christiaan Goossens
*/
class Client {
  private $guzzleClient;

  /**
   * Creates a new client instance with an API Key and API Secret
   * You can obtain these from the Communicacie, who will register your app in Lassie
   *
   * @param string $key
   * @param string $secret
   */
  public function __construct(string $key, string $secret) {
    $stack = new HandlerStack();
    $stack->setHandler(new CurlHandler());
    $stack->push(KeyMiddleware::addData($key, $secret));
    $this->guzzleClient = new GuzzleClient([
        'base_uri' => 'https://davinci.lassie.cloud/',
        'timeout'  => 5.0,
        'handler' => $stack
    ]);
  }

  /**
   * Get name corresponding to an ID in Lassie
   */
  public function getNameByID($id) {
    $names = $this->getListOfNames();
    return $names[$id];
  }

  /**
   * Get list of formatted names from Lassie
   */
  public function getListOfNames($active = false) {
    $memberList = $this->getMemberList($active);
    $r = [];
    foreach($memberList as $member) {
      if ($member['infix'] !== "") {
        $r[$member['id']] = $member['first_name'] . " " . $member['infix'] . " " . $member['last_name'];
      } else {
        $r[$member['id']] = $member['first_name'] . " " . $member['last_name'];
      }
    }
    return $r;
  }

  /**
   * Authenticates a member with their ID and password, as registered in Lassie
   */
  public function authenticate($id, $pass) {
    $response = $this->guzzleClient->request('GET', 'api/v2/model', [
        'query' => ['model_name' => 'person_model', 'method_name' => "get_populated_person", "person_id" => (string) $id]
    ]);

    $arr = json_decode($response->getBody(), true);
    $hash = $arr['security_hash'];
    return password_verify($pass, $hash);
  }

  /**
   * Gets a member list from the server,
   * optionally specifiy active members only
   */
  public function getMemberList($active = false) {
    $response = $this->guzzleClient->request('GET', 'api/v2/model', [
        'query' => ['model_name' => 'person_model', 'method_name' => "get_persons"]
    ]);

    $array = json_decode($response->getBody(), true);
    $rArr = [];

    foreach($array as $person) {
      // Filters
      if ($active && $person['active'] === "0") {
        // We only want active members
        continue;
      }

      if ($person['first_name'] === "" && $person['last_name'] === "") {
        // User deleted
        continue;
      }

      $rArr[] = [
        "id" => $person['id'],
        "active" => $person['active'],
        "first_name" => $person['first_name'],
        "infix" => $person['infix'],
        "last_name" => $person['last_name'],
        "initials" => $person['initials']
      ];
    }

    return $rArr;
  }


  public function getMember($id) {
    $response = $this->guzzleClient->request('GET', 'api/v2/model', [
        'query' => ['model_name' => 'person_model', 'method_name' => "get_populated_person", "person_id" => (string) $id]
    ]);

    var_dump(json_decode($response->getBody(), true));
  }
}
