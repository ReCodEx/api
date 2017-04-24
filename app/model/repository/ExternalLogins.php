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
   * Find external login based on external id
   * @param   string $externalId ID of the user
   * @return  User|NULL
   */
  public function findByExternalId($externalId) {
    return $this->findOneBy([ "externalId" => $externalId ]);
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
   * Connect the user account with the data received from the authentication service server.
   * @param IExternalLoginService $service
   * @param User $user
   * @param string $externalId
   * @return bool
   */
  public function connect(IExternalLoginService $service, User $user, string $externalId): bool {
    $externalLogin = new ExternalLogin($user, $service->getServiceId(), $externalId);
    $this->persist($externalLogin);
    return TRUE;
  }

}
