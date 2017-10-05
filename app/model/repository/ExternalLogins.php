<?php

namespace App\Model\Repository;

use App\Helpers\ExternalLogin\IExternalLoginService;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\ExternalLogin;
use App\Model\Entity\User;

class ExternalLogins extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ExternalLogin::class);
  }

  /**
   * @param string $authService
   * @param string $externalId
   * @return User|NULL
   */
  public function getUser($authService, $externalId) {
    $login = $this->findOneBy([
      "authService" => $authService,
      "externalId" => $externalId
    ]);

    if ($login) {
      return $login->getUser();
    }

    return NULL;
  }

  /**
   * @param User $user
   * @param string $authService
   * @return ExternalLogin|NULL
   */
  public function findByUser(User $user, string $authService) {
    return $this->findOneBy([
      'authService' => $authService,
      'user' => $user
    ]);
  }

  /**
   * Connect the user account with the data received from the authentication service server.
   * @param IExternalLoginService $service
   * @param User $user
   * @param string $externalId
   * @return ExternalLogin
   */
  public function connect(IExternalLoginService $service, User $user, string $externalId): ExternalLogin {
    $externalLogin = new ExternalLogin($user, $service->getServiceId(), $externalId);
    $this->persist($externalLogin);
    return $externalLogin;
  }

}
