<?php

namespace App\Helpers;

use Nette;
use Nette\Utils\Arrays;
use App\Model\Entity\User;
use App\Model\Repository\Users;
use App\Model\Repository\Logins;
use App\Model\Repository\ExternalLogins;

/**
 * This helper aggregates functions for modifying user entity (and credentials) when
 * user needs to be soft-deleted or anonymized for other reasons.
 * Let's cheer for GDPR!
 */
class AnonymizationHelper
{
    use Nette\SmartObject;

    /** @var Users */
    public $users;

    /** @var Logins */
    protected $logins;

    /** @var ExternalLogins */
    protected $externalLogins;

    /**
     * @var string Replacement for anonymized user name.
     */
    protected $anonymizedName;

    /**
     * @var string Suffix appended to an email address of deleted user.
     */
    protected $deletedEmailSuffix;

    public function getDeletedEmailSuffix(): string
    {
        return $this->deletedEmailSuffix;
    }

    /**
     * @param array $params Injected configuration parameters.
     */
    public function __construct(Users $users, Logins $logins, ExternalLogins $externalLogins, array $params)
    {
        $this->users = $users;
        $this->logins = $logins;
        $this->externalLogins = $externalLogins;

        $this->anonymizedName = Arrays::get($params, "anonymizedName", "@anonymized");
        $this->deletedEmailSuffix = Arrays::get($params, "deletedEmailSuffix", "@deleted.recodex");
    }

    /**
     * Since users are only soft-deleted, the record has to be prepared for deletion first.
     * This function augments user's email and removes all credentials.
     */
    public function prepareUserForSoftDelete(User $user)
    {
        $user->setEmail($user->getEmail() . $this->deletedEmailSuffix);
        $this->users->flush();

        // All accounts related to the user are void.
        if ($user->hasLocalAccount()) {
            $login = $user->getLogin();
            $user->setLogin(null);
            $this->logins->remove($login);
        }

        foreach ($user->getExternalLogins() as $extLogin) {
            $this->externalLogins->remove($extLogin);
        }
    }
}
