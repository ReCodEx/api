<?php

namespace App\Helpers;

use App\Exceptions\HttpBasicAuthException;
use Nette\Http\Request;
use Nette\Utils\Strings;

/**
 * Handle HTTP Basic Auth headers.
 */
class BasicAuthHelper {

  /**
   * Extracts the username and password from the Authorization header of the HTTP request.
   * @param Request $req    HTTP request
   * @return array          Username and password
   * @throws HttpBasicAuthException if Authorization header is not present or is corrupted
   */
  public static function getCredentials(Request $req) {
    $auth = $req->getHeader("Authorization", NULL);
    if ($auth === NULL || Strings::startsWith($auth, "Basic ") === FALSE) {
      throw new HttpBasicAuthException("The request from broker must contain HTTP Basic authentication.");
    }

    $encodedCredentials = Strings::substring($auth, strlen("Basic "));
    $decodedCredentials = base64_decode($encodedCredentials);
    if (!Strings::contains($decodedCredentials, ":")) {
      throw new HttpBasicAuthException("HTTP 'Authorization' header must be in the format of 'Basic ' + base64(username:password)");
    }

    list($username, $password) = explode(":", $decodedCredentials, 2);
    if (Strings::length($username) === 0 || Strings::length($password) === 0) {
      throw new HttpBasicAuthException("Either username or password encoded in the HTTP Authorization header is empty.");
    }

    return [$username, $password];
  }

}
