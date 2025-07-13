<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\ForgottenPasswordHelper;
use App\Model\Repository\Logins;
use App\Model\Repository\SecurityEvents;
use App\Model\Entity\SecurityEvent;
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
     * @throws NotFoundException
     */
    #[Post("username", new VString(2), "An identifier of the user whose password should be reset")]
    public function actionDefault()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Change the user's password
     * @POST
     * @LoggedIn
     * @throws ForbiddenRequestException
     */
    #[Post("password", new VString(2), "The new password")]
    public function actionChange()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Check if a password is strong enough
     * @POST
     */
    #[Post("password", new VMixed(), "The password to be checked", nullable: true)]
    public function actionValidatePasswordStrength()
    {
        $this->sendSuccessResponse("OK");
    }
}
