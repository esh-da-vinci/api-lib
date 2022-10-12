<?php

namespace ESHDaVinci\API;

use Exception;
use GuzzleHttp;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Client as GuzzleClient;
use ESHDaVinci\API\Exceptions\PermissionDeniedException;
use ESHDaVinci\API\Exceptions\NotFoundException;

/**
 *  Main API Class
 *
 *  Create an instance of this class with your API credentials, obtained from the CommunicaCie,
 *  and use the methods on the obtained client object to call the required functions.
 *
 * @author E.S.H. Da Vinci - CommunicaCie
 */
class Client
{
    private $guzzleClient;

    /**
     * Creates a new client instance with an API Key and API Secret
     * You can obtain these from the Communicacie, who will register your app in Lassie
     *
     * @param string $token static access token for Directus
     */
    public function __construct(string $token, string $base_url = "https://admin.eshdavinci.nl")
    {
        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler());
        $this->guzzleClient = new GuzzleClient([
            "base_uri" => $base_url,
            "timeout" => 4.0,
            "headers" => [
                "Authorization" => "Bearer $token",
                "Content-Type" => "application/json",
            ]
        ]);
    }

    /** Checks the returned body for an error code */
    private function checkForError($response): void
    {
        $status_code = $response->getStatusCode();
        if ($status_code % 200 < 100)
            return;
        $data = json_decode($response->getBody(), true);
        var_dump($data);
        if($status_code === 404)
            throw new NotFoundException($data["error"]);
        elseif($status_code == 403)
            throw new PermissionDeniedException($data["error"]);
        else
            throw new Exception($status_code, $data["error"]);
    }

    public function request(string $method, string $url, array $data = []) {
        if ($method === "POST")
            $data = [GuzzleHttp\RequestOptions::JSON => $data];
        $data["http_errors"] = false;
        var_dump($data);
        $response = $this->guzzleClient->request($method, $url, $data);
        $this->checkForError($response);
        $data = json_decode($response->getBody(), true)["data"];
        return $data;
    }

    /** Get name corresponding to an ID in Lassie */
    public function getNameByID($id) {
        $member = $this->request("GET", "items/Members/$id");
        return $this->formatMemberName($member);
    }

    /** Get list of formatted names from Lassie */
    public function getListOfNames($active = false): array
    {
        $memberList = $this->getMemberList($active);
        $r = [];
        foreach ($memberList as $member) {
            $r[$member["id"]] = $this->formatMemberName($member);
        }
        return $r;
    }

    private static function formatMemberName(array $member) : string{
        if ($member["infix"] !== "" && $member["infix"] !== null)
            return $member["first_name"] . " " . $member["infix"] . " " . $member["last_name"];
        else
            return $member["first_name"] . " " . $member["last_name"];
    }

    private function getSecurityHash(string $member_id): string
    {
        $arr = $this->request("SEARCH", "items/PinHashes",
            ["query" => ["filter" => ["member" => ["_eq" => $member_id]]]]
        );
        if (!array_key_exists("data", $arr) || count($arr) === 0)
            return "";
        assert(count($arr) === 1);
        return $arr[0]["hash"];
    }

    /** Authenticates a member with their ID and password, as registered in Lassie */
    public function authenticate($id, $pass): bool
    {
        $hash = $this->getSecurityHash($id);
        return password_verify($pass, $hash);
    }

    /** Checks if a password has been registered for this user */
    public function hasToSetPassword($id): bool
    {
        return $this->getSecurityHash($id) === "";
    }

    /** Sets new password for user */
    public function setNewPassword($id, $password) : array
    {
        $data = ["member" => $id, "hash" => password_hash($password, PASSWORD_DEFAULT)];
        var_dump($data);
        return $this->request("POST", "items/PinHashes", $data);
    }

    /** @brief Get a member list from the server
     *
     * TODO: Optionally return active members only
     */
    public function getMemberList($active = false) : array
    {
        if ($active === true)
            return $this->getActiveMemberList();
        $data = $this->request("GET", "items/Members");
        return $this->mapMembersToArray($data);
    }

    private function today() : string {
        return date("Y-m-d", time());
    }

    private function getActiveMemberList() : array {
        $data = $this->request("SEARCH", "items/Memberships", [
            "query" => ["filter" => [
                // "type.end" => ["_gte" => $this->today()]
                "id" => ["_eq" => 1]
            ]]
        ]);
        var_dump($data);
        $ids = array();
        foreach ($data as $membership)
            $ids[] = $membership["member"];
        $data = $this->request("GET", "items/Members", ["data" => $ids]);
        return $this->mapMembersToArray($data);
    }

    private function mapMembersToArray($members) : array {
        $result = array();
        foreach ($members as $person) {
            $result[] = [
                "id" => $person["id"],
                "active" => true,  // TODO: This is no longer present in Directus
                "first_name" => $person["first_name"],
                "infix" => $person["infix"],
                "last_name" => $person["last_name"],
                "initials" => "", // TODO: Add this to Directus
                "ssc_number" => $person["dms_id"]
            ];
        }
        return $result;
    }

    public function createPerson($values) : array
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

    private static function convertInstitution($raw): string
    {
        $institutions = [
            "fontys" => "Fontys Hogeschool",
            "tue" => "Eindhoven University of Technology",
            "other" => "Other SSCE Recognised Organisation"
        ];
        if (array_key_exists($raw, $institutions))
            return $institutions[$raw];
        return "Unknown";
    }

    public function getMember($id)
    {
        $member = $this->request("GET", "items/Members/$id");
        $address = $this->request("GET", "items/MemberAddresses/${member['address']}");
        $boards = $this->request("SEARCH", "items/Committees", [
            "query" => ["filter" => ["name" => ["_contains" => "Board"]]]
        ]);
        foreach ($boards as $board)
            $board_ids[] = $board["id"];
        $board = $this->request("SEARCH", "items/CommitteeMembers", [
            "query" => [ "filter" => [
                    "committee" => ["_in" => $board_ids],
                    "end" => ["_gte" => $this->today()],
                    "member" => ["_eq" => $member["id"]]
                ]]
        ]);
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

}
