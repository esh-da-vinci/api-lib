### Da Vinci API
At the TU/e and Fontys we have a lot of technical knowledge. Also at Da Vinci, we have some apps that we have developed ourselves. To link these apps to our member administration system, this library has been developed.

##### Install
You should import the `Client` class through Composer into your application. For Composer, you import this git repository with the following code:

```
"require": {
	"e-s-h-da-vinci/api-lib": "dev-master"
},
"repositories": [
    {
        "type": "vcs",
    	  "url": "https://github.com/e-s-h-da-vinci/api-lib"
	}
]
```

##### Create a Client
You can use the following code to get an example member list:

```
use ESHDaVinci\API\Client;
$client = new Client(
  "your-api-key",
  "your-api-secret"
);

var_dump($client->getListOfNames(true));
```

This will give you a list of all active members. You can replace the function with any of those specified below.
You should replace `your-api-key` and `your-api-secret` with the key and secret that you got from the Communicatcie!


##### Available functions
###### getListOfNames($active = false)
Gets a list of the names of all members of Da Vinci. The key represents an ID and the value is the full name. You can optionally give a parameter $active, representing if you only want active members, or all members (true is only active).

###### authenticate(int $id, string $pass)
Checks a password against the stored password for user $id. Returns boolean.

###### getNameByID(int $id)
Gets name corresponding to given ID

###### getMemberList($active = false)
Gets a member list, similar to getListOfNames, but returns array of arrays with more info, such as the initials.

###### getMember(int $id)
Gets data about this member from the system. Will include meta fields, such as nhb_number, honorary_member etc.

###### getMembershipsByID(int $id)
Lists all memberships registered in the system for this user. Can also include memberships that have not been paid yet.

###### getPayableMembershipsByID(int $id)
List all memberships that have yet to be paid for this member.

###### hasToSetPassword(int $id)
Checks if the given member has a password registered.

###### setNewPassword(int $id, string $pass)
Register a new password for this member. Extra privileges at Lassie are required for your application to do this.

##### Exceptions
Exceptions will be thrown on requests that your app lacks permission for, or when the server cannot be reached. Exceptions include `NotFoundException`, `PermissionDeniedException`.
