<?php

namespace ESHDaVinci\API;

interface ClientInterface
{
    /**
     * Gets the name corresponding to a certain id
     *
     * @param $id ID of the user
     *
     * @return string
     */
    public function getNameByID($id): string;

    /**
     * Gets an array with names, optionally only active members
     *
     * @param $active Only show active members
     *
     * @return array
     */
    public function getListOfNames($active = false): array;

    /**
     * Checks if the given credentials match for the user
     *
     * @param $id User ID
     * @param $pass PIN code
     *
     * @return bool
     */
    public function authenticate($id, $pass): bool;

    /**
     * Checks if the given user has to set their password
     *
     * @param $id User ID
     *
     * @return bool
     */
    public function hasToSetPassword($id): bool;

    /**
     * Sets a new password for a user
     *
     * @param $id User ID
     * @param $password PIN code
     *
     * @return bool
     */
    public function setNewPassword($id, $password): bool;

    /**
     * Gets the member list, either for all, or only active members
     *
     * @param $active By default fetch all, if requested, fetch only active
     *
     * @return array
     */
    public function getMemberList($active = false): array;

    /**
     * Creates a person within the database
     *
     * @param $values Values to use within person creation
     *
     * @return array
     */
    public function createPerson($values): array;

    /**
     * @deprecated
     * Updates a person with the given values
     *
     * @param $values Values to use for update
     *
     * @return bool
     */
    public function updatePerson($id, $values): bool;

    /**
     * @deprecated
     * Gets memberships for a given user
     *
     * @param $id User ID
     *
     * @return array
     */
    public function getMembershipsByID($id): array;

    /**
     * @deprecated
     * Gets memberships open for payment for a given user
     *
     * @param $id User ID
     *
     * @return array
     */
    public function getPayableMembershipsByID($id): array;

    /**
     * Gets the full information of a member
     *
     * @param $id User ID
     *
     * @return array
     */
    public function getMember($id): array;
}
