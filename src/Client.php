<?php

namespace ESHDaVinci\API;

use Exception;
use GuzzleHttp;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Client as GuzzleClient;
use ESHDaVinci\API\Exceptions\PermissionDeniedException;
use ESHDaVinci\API\Exceptions\NotFoundException;
use ESHDaVinci\API\Exceptions\NotImplementedException;

/**
 *  Main API Class
 *
 *  Create an instance of this class with your API credentials, obtained from the CommunicaCie,
 *  and use the methods on the obtained client object to call the required functions.
 *
 * @author Christiaan Goossens
 * @author E.S.H. Da Vinci - CommunicaCie
 */
class Client implements ClientInterface
{
    private $guzzleClient;

    /**
     * Creates a new client instance with an API Key and API Secret
     * You can obtain these from the Communicacie, who will register your app in Lassie
     *
     * @param string $token   Static access token for Directus
     * @param string $base_url   Optional base URL for Directus
     *
     * @return void
     */
    public function __construct(string $token, string $base_url = "https://admin.eshdavinci.nl")
    {
        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler());
        $this->guzzleClient = new GuzzleClient([
            "base_uri" => $base_url,
            "timeout" => 20.0, // WSL has a bug where it needs long timeouts
            "headers" => [
                "Authorization" => "Bearer $token",
                "Content-Type" => "application/json",
            ]
        ]);
    }

    /**
     * Checks the returned body for an error
     *
     * @param $response Response that was received
     * @return void
     */
    private function checkForError($response): void
    {
        $status_code = $response->getStatusCode();
        if ($status_code > 199 && $status_code < 300) {
            return;
        }

        $data = json_decode($response->getBody(), true);

        if ($status_code === 401) {
            throw new PermissionDeniedException("Token invalid.");
        } elseif ($status_code === 404) {
            throw new NotFoundException($data["error"]);
        } elseif ($status_code == 403) {
            throw new PermissionDeniedException($data["error"]);
        } else {
            throw new Exception($status_code . " - " . json_encode($data), $data["error"]);
        }
    }

    /**
     * Do a request to the server
     * External use in tests only
     *
     * @param $method Method to use on the HTTP request
     * @param $url URL to do the request to, prepended by the base_url
     * @param $data Data to send (for POST)
     *
     * @return mixed
     */
    private function request(string $method, string $url, array $data = [])
    {
        if ($method === "POST") {
            $data = [GuzzleHttp\RequestOptions::JSON => $data];
        }

        // We handle our own errors, don't throw exceptions
        $data["http_errors"] = false;

        $response = $this->guzzleClient->request($method, $url, $data);

        $this->checkForError($response);
        $data = json_decode($response->getBody(), true)["data"];
        return $data;
    }

    /**
     * Helper to do a GET request more easily
     */
    private function requestGET(string $url, array $query = [])
    {
        // Always fetch all
        $query['limit'] = -1;

        // Use GET, this works better with proxies than SEARCH, and we are not using
        // very complicated queries anyway that exceed the allowed URL length
        return $this->request("GET", $url, [
            'query' => array_map(function ($value) {
                return json_encode($value);
            }, $query)
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getNameByID($id): string
    {
        $member = $this->requestGET("items/Members/$id");
        return $this->formatMemberName($member);
    }

    /**
     * @inheritDoc
     */
    public function getListOfNames($active = false): array
    {
        $memberList = $this->getMemberList($active);
        $r = [];

        foreach ($memberList as $member) {
            $r[$member["id"]] = $this->formatMemberName($member);
        }

        return $r;
    }

    /**
     * Formats a member name correctly
     *
     * @param array $member Member array
     *
     * @return string
     */
    private function formatMemberName(array $member): string
    {
        if ($member["infix"] !== "" && $member["infix"] !== null) {
            return $member["first_name"] . " " . $member["infix"] . " " . $member["last_name"];
        } else {
            return $member["first_name"] . " " . $member["last_name"];
        }
    }

    /**
     * Gets a security hash by member_id
     *
     * @param string $member_id Member ID
     *
     * @return string
     */
    private function getSecurityHash(string $member_id): string
    {
        $arr = $this->requestGET(
            "items/PinHashes",
            ["filter" => ["member" => ["_eq" => $member_id]]]
        );

        if (count($arr) !== 1 || !isset($arr[0])) {
            return "";
        }

        return $arr[0]["hash"];
    }

    /**
     * @inheritDoc
     */
    public function authenticate($id, $pass): bool
    {
        $hash = $this->getSecurityHash($id);
        return password_verify($pass, $hash);
    }

    /**
     * @inheritDoc
     */
    public function hasToSetPassword($id): bool
    {
        return $this->getSecurityHash($id) === "";
    }

    /**
     * @inheritDoc
     */
    public function setNewPassword($id, $password): bool
    {
        $data = ["member" => $id, "hash" => password_hash($password, PASSWORD_DEFAULT)];
        $this->request("POST", "items/PinHashes", $data);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMemberList($active = false): array
    {
        if ($active === true) {
            return $this->getActiveMemberList();
        }
        $data = $this->requestGET("items/Members");
        return $this->mapMembersToArray($data);
    }

    /**
     * Get the date of today in ISO notation
     *
     * @return string
     */
    private function today(): string
    {
        return date("Y-m-d", time());
    }

    /**
     * Fetch the active members list
     *
     * @return array
     */
    private function getActiveMemberList(): array
    {
        $data = $this->requestGET(
            "items/Memberships",
            ["filter" => [
                "type" => ['end' => ["_gte" => $this->today()]]
            ]]
        );

        $ids = array_map(function ($membership) {
            return strval($membership["member"]);
        }, $data);

        $data = $this->requestGET(
            "items/Members",
            [
                "filter" => [
                    "id" => [
                        "_in" => $ids
                    ]
                ]
            ]
        );

        return $this->mapMembersToArray($data);
    }

    /**
     * Maps the members fetched to an array with the correct fields
     *
     * @return array
     */
    private function mapMembersToArray($members): array
    {
        $result = array();
        foreach ($members as $person) {
            $result[] = [
                "id" => $person["id"],
                "active" => true,  // TODO: This is no longer present in Directus
                "first_name" => $person["first_name"],
                "infix" => $person["infix"],
                "last_name" => $person["last_name"],
                "initials" => $person['initials'],
                "ssc_number" => $person["dms_id"]
            ];
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function createPerson($values): array
    {
        $address_data = [
            "street" => $values["address_street"],
            "number" => $values["address_number"],
            "post_code" => $values["address_zip"],
            "city" => $values["address_city"],
            "country" => $values["address_country"],
        ];

        $address = $this->request("POST", "items/MemberAddresses", $address_data);
        $member_data = [
            "first_name" => $values["first_name"],
            "infix" => $values["infix"],
            "last_name" => $values["last_name"],
            "phone_number" => $values["phone_home"],
            "email" => $values["email_primary"],
            "birth_date" => $values["birthdatebug"],
            "institution" => $values["department_id"],
            "study_program" => $values["study"],
            "address" => $address["id"],
            "join_date" => $this->today()
        ];

        return $this->request("POST", "items/Members", $member_data);
    }

    /**
     * Changes the saved school field into something that makes more sense
     *
     * @param $raw Raw value
     *
     * @return string
     */
    private function convertInstitution($raw): string
    {
        $institutions = [
            "fontys" => "Fontys Hogeschool",
            "tue" => "Eindhoven University of Technology",
            "other" => "Other SSCE Recognised Organisation"
        ];

        if (array_key_exists($raw, $institutions)) {
            return $institutions[$raw];
        }

        return "Unknown";
    }

    /**
     * @inheritDoc
     */
    public function getMember($id): array
    {
        $member = $this->requestGET("items/Members/$id");
        $address = $this->requestGET("items/MemberAddresses/${member['address']}");
        $boards = $this->requestGET(
            "items/Committees",
            ["filter" => ["name" => ["_contains" => "Board"]]]
        );

        foreach ($boards as $board) {
            $board_ids[] = strval($board["id"]);
        }

        $board = $this->requestGET(
            "items/CommitteeMembers",
            ["filter" => [
                "committee" => ["_in" => $board_ids],
                "end_date" => ["_gte" => $this->today()],
                "member" => ["_eq" => strval($member["id"])]
            ]]
        );

        $is_board = count($board) != 0;
        $result = array(
            "id" => $member["id"],
            "address" => $address,
            "phone" => $member["phone_number"],
            "email" => $member["email"],
            "birthdate" => $member["birth_date"],
            "study" => $member["study_program"] ?? null,
            "institution" => $this->convertInstitution($member["institution"] ?? null),
            "generation" => substr($member["join_date"], 0, 4),
            "active" => true,
            "first_name" => $member["first_name"],
            "last_name" => $member["last_name"],
            "initials" => "",
            "infix" => $member["infix"],
            "ssc_number" => $member["dms_id"],
            "is_board" => $is_board,
            "meta" => [
                "ssc_status" => null,  // Not stored in the database anymore
                "nhb_number" => $member["nhb_id"],
                "external_NHB" => null,
                "bar_certificate" => null,
                "EHBO_certificate" => null,
                "bhv_certificate" => null,
                "honorary_member" => null
            ]
        );

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function updatePerson($id, $values): bool
    {
        throw new NotImplementedException("updatePerson() unused and deprecated");
    }

    /**
     * @inheritDoc
     */
    public function getMembershipsByID($id): array
    {
        throw new NotImplementedException("getMembershipsByID() unused and deprecated");
    }

    /**
     * @inheritDoc
     */
    public function getPayableMembershipsByID($id): array
    {
        throw new NotImplementedException("getPayableMembershipsByID() unused and deprecated");
    }
}
