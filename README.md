### Da Vinci API
At the TU/e and Fontys we have a lot of technical knowledge. Also at Da Vinci, we have some apps that we have developed ourselves. To link these apps to our member administration system, this library has been developed.

##### Create a Client
You should import the `Client` class through Composer into your application. You can then use the following code to get an example member list:

```
use ESHDaVinci\API\Client;
$client = new Client(
  "your-api-key",
  "your-api-secret"
);

var_dump($client->getListOfNames(true));
```

This will give you a list of all active members.
You should replace `your-api-key` and `your-api-secret` with the key and secret that you got from the Communicacie!
