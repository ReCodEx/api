<?php

namespace App\Model\Repository;

use App\Model\Entity\ExternalLogin;
use App\Model\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<ExternalLogin>
 */
class ExternalLogins extends BaseRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, ExternalLogin::class);
    }

    /**
     * @param string $authService
     * @param string $externalId
     * @return User|null
     */
    public function getUser($authService, $externalId): ?User
    {
        $login = $this->findOneBy(
            [
                "authService" => $authService,
                "externalId" => $externalId
            ]
        );

        if ($login) {
            return $login->getUser();
        }

        return null;
    }

    /**
     * @param User $user
     * @param string $authService
     * @return ExternalLogin|null
     */
    public function findByUser(User $user, string $authService): ?ExternalLogin
    {
        return $this->findOneBy(
            [
                'authService' => $authService,
                'user' => $user
            ]
        );
    }

    /**
     * Connect the user account with the data received from the authentication service server.
     * @param string $authName
     * @param User $user
     * @param string $externalId
     * @return ExternalLogin
     */
    public function connect(string $authName, User $user, string $externalId): ExternalLogin
    {
        $externalLogin = new ExternalLogin($user, $authName, $externalId);
        $this->persist($externalLogin);
        return $externalLogin;
    }
}
