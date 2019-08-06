<?php

namespace ESHDaVinci\API;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Client as GuzzleClient;
use ESHDaVinci\API\Helpers\KeyMiddleware;
use ESHDaVinci\API\Exceptions\PermissionDeniedException;
use ESHDaVinci\API\Exceptions\NotFoundException;

/**
*  Main API Class
*
*  Create an instance of this class with your API credentials, obtained from the Communicacie,
*  and use the methods on the obtained client object to call the required functions.
*
*  @author Christiaan Goossens
*/
class Client
{
    private $guzzleClient;

    /**
     * Creates a new client instance with an API Key and API Secret
     * You can obtain these from the Communicacie, who will register your app in Lassie
     *
     * @param string $key
     * @param string $secret
     */
    public function __construct(string $key, string $secret)
    {
        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler());
        $stack->push(KeyMiddleware::addData($key, $secret));
        $this->guzzleClient = new GuzzleClient([
        'base_uri' => 'https://davinci.lassie.cloud/',
        'timeout'  => 30.0,
        'handler' => $stack
    ]);
    }

    /**
     * Checks the returned body for an error code
     */
    private function checkForError($body)
    {
        if (isset($body['status_code'])) {
            // An error is detected
            $status = $body['status_code'];
            if ($status === 403) {
                throw new PermissionDeniedException($body['error']);
            } elseif ($status === 404) {
                throw new NotFoundException($body['error']);
            }
        }
    }

    /**
     * Get name corresponding to an ID in Lassie
     */
    public function getNameByID($id)
    {
        $names = $this->getListOfNames();
        return $names[$id];
    }

    /**
     * Get list of formatted names from Lassie
     */
    public function getListOfNames($active = false)
    {
        $memberList = $this->getMemberList($active);
        $r = [];
        foreach ($memberList as $member) {
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
    public function authenticate($id, $pass)
    {
        $response = $this->guzzleClient->request('GET', 'api/v2/model', [
        'query' => ['model_name' => 'person_model', 'method_name' => "get_populated_person", "person_id" => (string) $id]
    ]);

        $arr = json_decode($response->getBody(), true);
        $this->checkForError($arr);
        $hash = $arr['security_hash'];
        return password_verify($pass, $hash);
    }

    /**
     * Gets a member list from the server,
     * optionally specifiy active members only
     */
    public function getMemberList($active = false)
    {
        $response = $this->guzzleClient->request('GET', 'api/v2/model', [
            'query' => ['model_name' => 'person_model', 'method_name' => "get_persons"]
        ]);

        $array = json_decode($response->getBody(), true);
        $this->checkForError($array);
        $rArr = [];

        foreach ($array as $person) {
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
              "initials" => $person['initials'],
              "ssc_number" => $person['external_id']
            ];
        }

        return $rArr;
    }

    private function getFieldsForPersonTable()
    {
        $response = $this->guzzleClient->request('GET', 'api/v2/model', [
          'query' => ['model_name' => 'definition_model', 'method_name' => "get_definitions", "table_name" => "pool_options"]
      ]);

        $resp = json_decode($response->getBody(), true);
        $this->checkForError($resp);
        $ret = [];
        foreach ($resp as $key => $entry) {
            $ret[$key] = $entry['value'];
        }
        return $ret;
    }

    public function getMembershipsByID($id)
    {
        $response = $this->guzzleClient->request('GET', 'api/v2/model', [
          'query' => ['model_name' => 'person_model', 'method_name' => "get_valid_memberships_by_person_id", "person_id" => (string) $id]
      ]);
        $body = json_decode($response->getBody(), true);
        $this->checkForError($body);
        $ret = [];
        foreach ($body as $membership) {
            $ret[] = [
            "active" => $membership['active'],
            "name" => $membership['name'],
            "fee" => $membership['fee'],
            "issue_date" => $membership['issue_date'],
            "expiry_date" => $membership['expiry_date'],
            "is_general_membership" => $membership['is_general_membership']
          ];
        }
        return $ret;
    }

    public function getPayableMembershipsByID($id)
    {
        $response = $this->guzzleClient->request('GET', 'api/v2/model', [
          'query' => ['model_name' => 'person_model', 'method_name' => "get_payable_memberships", "person_id" => (string) $id]
      ]);
        $body = json_decode($response->getBody(), true);
        $this->checkForError($body);

        $ret = [];
        foreach ($body as $membership) {
            $ret[] = [
            "active" => $membership['active'],
            "name" => $membership['name'],
            "fee" => $membership['fee'],
            "issue_date" => $membership['issue_date'],
            "expiry_date" => $membership['expiry_date'],
            "is_general_membership" => $membership['is_general_membership']
          ];
        }
        return $ret;
    }

    public function getMember($id)
    {
        $response = $this->guzzleClient->request('GET', 'api/v2/model', [
            'query' => ['model_name' => 'person_model', 'method_name' => "get_populated_person", "person_id" => (string) $id]
        ]);

        $dropdown = $this->getFieldsForPersonTable();

        $body = json_decode($response->getBody(), true);
        $this->checkForError($body);
        $member = array(
          "id" => $body['id'],
          "address" => [
            "street" => $body['address_street'],
            "number" => $body['address_number'],
            "city" => $body['city'] ?? null,
            "country" => $body['country'] ?? null
            ],
            "phone" => $body['phone_mobile'],
            "email" => $body['email_primary'],
            "birthdate" => $body['birthdate'],
            "preferences" => [
              "mail" => $body['pref_mail'],
              "newsletter" => $body['pref_newsletter']
              ],
              "study"  => $body['subdepartement_id'] ?? null,
              "institution" => $body['departement_id'] ?? null,
              "generation" => $body['generation_id'],
            "nick_name" => $body['nick_name'],
            "active" => (bool) $body['active'],
            "first_name" => $body['first_name'],
            "last_name" => $body['last_name'],
            "initials" => $body['initials'],
            "infix" => $body['infix'],
            "ssc_number" => $body['external_id'],
            "is_board" => $body['is_board'],
            "meta" => [
              "ssc_status" => $body['SSC_status'],
              "nhb_number" => $body['NHB_number'],
              "external_NHB" => $dropdown[$body['external_NHB']],
              "bar_certificate" => $dropdown[$body['bar_certificate']],
              "EHBO_certificate" => $dropdown[$body['EHBO_certificate']],
              "bhv_certificate" => $dropdown[$body['bhv_certificate']],
              "honorary_member" => $dropdown[$body['honorary_member']]
              ]
        );

        return $member;
    }
}
