<?php

namespace App\V1Module\Presenters;

use App\Exception\WrongCredentialsException;
use App\Exception\BadRequestException;
use App\Helpers\ExternalLogin\CAS;
use App\Helpers\ExternalLogin\IExternalLoginService;
use App\Model\Entity\User;
use App\Model\Repository\Logins;
use App\Model\Repository\ExternalLogins;
use App\Security\AccessManager;

class LoginPresenter extends BasePresenter {

  /** @inject @var Logins */
  public $logins;

  /** @inject @var ExternalLogins */
  public $externalLogins;

  /** @inject @var CAS */
  public $CAS;

  /** @inject @var AccessManager */
  public $accessManager;

  /**
   * Sends response with an access token, if the user exists.
   * @throws WrongCreedentialsException
   */
  private function trySendingLoggedInResponse(User $user) {
    if (!$user) {
      throw new WrongCredentialsException;
    }

    $this->sendSuccessResponse([
      "accessToken" => $this->accessManager->issueToken($user),
      "user" => $user
    ]);
  }

  /**
   * @GET
   */
  public function actionDefault($username, $password) {
    $user = $this->logins->getUser($username, $password);
    $this->trySendingLoggedInResponse($user);
  }

  /**
   * @GET
   */
  public function actionExternal($serviceId, $username, $password) {
    $authService = $this->getAuthService($serviceId);
    $externalData = $authService->getUser($username, $password); // throws if the user cannot be logged in
    $user = $this->externalLogins->getUser($serviceId, $externalData->getId());
    $this->trySendingLoggedInResponse($user);
  }

  private function getAuthService(string $serviceId): IExternalLoginService {
    switch (strtolower($serviceId)) {
      case $this->CAS->getServiceId():
        return $this->CAS;
      default:
        throw new BadRequestException("Authentication service '$serviceId' is not supported.");
    }
  }

  /**
   * @GET
   * @LoggedIn
   */
  public function actionRefresh() {
    $user = $this->users->findCurrentUserOrThrow();
    $this->sendSuccessResponse([
      "accessToken" => $this->accessManager->issueToken($user),
      "user" => $user
    ]);
  }

}
