<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\ForgottenPasswordHelper;
use App\Model\Repository\Logins;
use App\Model\Repository\SecurityEvents;
use App\Model\Entity\Login;
use App\Model\Entity\SecurityEvent;
use App\Security\AccessToken;
use App\Security\TokenScope;
use Nette\Security\Passwords;
use ZxcvbnPhp\Zxcvbn;
use DateTime;

/**
 * Endpoints associated with resetting forgotten passwords
 */
class ForgottenPasswordPresenter extends BasePresenter
{
    /**
     * @var Logins
     * @inject
     */
    public $logins;

    /**
     * @var SecurityEvents
     * @inject
     */
    public $securityEvents;

    /**
     * @var ForgottenPasswordHelper
     * @inject
     */
    public $forgottenPasswordHelper;

    /**
     * @var Passwords
     * @inject
     */
    public $passwordsService;

    /**
     * Request a password reset (user will receive an e-mail that prompts them to reset their password)
     * @POST
     * @Param(type="post", name="username", validation="string:2..",
     *        description="An identifier of the user whose password should be reset")
     * @throws NotFoundException
     */
    public function actionDefault()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Change the user's password
     * @POST
     * @Param(type="post", name="password", validation="string:2..", description="The new password")
     * @LoggedIn
     * @throws ForbiddenRequestException
     */
    public function actionChange()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Check if a password is strong enough
     * @POST
     * @Param(type="post", name="password", description="The password to be checked")
     */
    public function actionValidatePasswordStrength()
    {
        $this->sendSuccessResponse("OK");
    }
}
