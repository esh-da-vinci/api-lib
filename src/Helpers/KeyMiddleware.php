<?php

namespace ESHDaVinci\API\Helpers;

use Psr\Http\Message\RequestInterface;
use ESHDaVinci\API\Helpers\HashCreator;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Request;

class KeyMiddleware {
  public static function addData($key, $secret)
  {
      return function (callable $handler) use ($key, $secret) {
        return function (
            RequestInterface $request,
            array $options
        ) use ($handler, $key, $secret) {
            $hash = HashCreator::createHash($key, $secret);
            $get = $request->getMethod() === "GET";
            if ($get) {
              // Add it to the query
              foreach($hash as $param => $value) {
                $request = $request->withUri(Uri::withQueryValue(
                    $request->getUri(),
                    $param,
                    $value
                ));
              }
            } else {
              // It must be some post kinda thing
              // Add it to the post parameters
              $request = new Request(
                  $request->getMethod(),
                  $request->getUri(),
                  $request->getHeaders(),
                  http_build_query($hash) . '&' . $request->getBody()
              );
            }

            return $handler($request, $options);
        };
      };
  }
}
