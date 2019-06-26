<?php

namespace ESHDaVinci\API\Helpers;

/**
*  Hash Creator for Lassie
*
*  @author Christiaan Goossens
*/
class HashCreator {
  /**
   * Creates hash from key and secret
   */
  public static function createHash($key, $secret) {
    $content = self::seed();
    $hash = base64_encode(hash_hmac('sha256', $key . ':' . $content, $secret));

    return [
      "api_key" => $key,
      "api_hash" => $hash,
      "api_hash_content" => $content
    ];
  }

  /**
   * Non-cryptographically safe seeder
   */
  private static function seed() {
      $unique = uniqid();
      $timestamp = microtime();
      return crc32($unique . $timestamp);
  }
}
