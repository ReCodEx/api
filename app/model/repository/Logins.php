<?php

namespace App\Model\Repository;

use App\Model\Entity\Login;
use App\Model\Entity\User;
use App\Exceptions\NotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Security\Passwords;

/**
 * @extends BaseRepository<Login>
 */
class Logins extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, Login::class);
    }

    /**
     * Find user's login
     * @param string $userId ID of the user
     * @return  Login|null
     */
    public function findByUserId($userId): ?Login
    {
        return $this->findOneBy(["user" => $userId]);
    }

    /**
     * Find one login entity by the username column, if not found, raise an exception.
     * @param string $username
     * @return Login
     * @throws NotFoundException
     */
    public function findByUsernameOrThrow(string $username): Login
    {
        $login = $this->findOneBy(["username" => $username]);
        if (!$login) {
            throw new NotFoundException("Login with username '$username' does not exist.");
        }

        return $login;
    }

    /**
     * Find one login entity by the username column.
     * @param string $username
     * @return Login|null
     */
    public function getByUsername(string $username): ?Login
    {
        return $this->findOneBy(["username" => $username]);
    }

    /**
     *
     * @param string $username
     * @param string $password
     * @param Passwords $passwordsService injection of a service (we do not want to inject directly into entities)
     * @return User|null
     */
    public function getUser(string $username, string $password, Passwords $passwordsService): ?User
    {
        /** @var Login|null $login */
        $login = $this->findOneBy(["username" => $username]);
        if ($login) {
            $oldPwdHash = $login->getPasswordHash();
            if ($login->passwordsMatch($password, $passwordsService)) {
                if ($login->getPasswordHash() !== $oldPwdHash) {
                    // the password has been rehashed - persist the information
                    $this->persist($login);
                    $this->em->flush();
                }

                return $login->getUser();
            }
        }

        return null;
    }

    /**
     * Clear password of given user.
     * @param User $user
     */
    public function clearUserPassword(User $user): void
    {
        $login = $this->findByUserId($user->getId());
        if ($login) {
            $login->clearPassword();
            $this->flush();
        }
    }
}
