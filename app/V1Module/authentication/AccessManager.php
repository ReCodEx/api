<?php

namespace App\Authentication;

use App\Model\Entity\User;
use App\Model\Repository\Users;

use App\Exception\InvalidAccessTokenException;
use App\Exception\NoAccessTokenException;
use App\Exception\ForbiddenRequestException;

use Nette\Http\Request;
use Nette\Utils\Strings;

class AccessManager {

  /** @var Users  Users repository */
  protected $users;

  public function __construct(Users $users) {
    $this->users = $users;
  }

  /**
   * @param   Request
   * @return  User
   */
  public function getUserFromRequestOrThrow(Request $req) {
    $token = $this->getGivenAccessToken($req);
    if ($token === NULL) {
      throw new NoAccessTokenException;
    }

    // @todo validate the token 
    if (FALSE) {
      throw new InvalidAccessTokenException($token);
    }

    // @todo find the user in the repository and return him, if any
    $user = NULL;
    if (!$user) {
      throw new ForbiddenRequestException;
    }

    return $user;
  }

  /**
   * @param   User
   * @return  string
   */
  public function issueToken(User $user) {
    // @todo create a token for this user
    return '';
  }

  /**
   * Extract the access token from the request.
   * @return string|null  The access token parsed from the HTTP request, or FALSE if there is no access token.
   */
  public function getGivenAccessToken(Request $request) {
    $accessToken = $request->getQuery("access_token");
    if($accessToken !== NULL) return $accessToken; // the token is specified in the URL

    // if the token is not in the URL, try to find the 'Authorization' header with the bearer token
    $authorizationHeader = $request->getHeader('Authorization', NULL);
    $parts = Strings::split($authorizationHeader, "/ /");
    if(count($parts) === 2) {
      list($bearer, $accessToken) = $parts;
      if($bearer === "Bearer" && !Strings::contains($accessToken, " ")) {
        return $accessToken;
      }
    }

    return NULL; // there is no access token or it could not be parsed
  }

}
