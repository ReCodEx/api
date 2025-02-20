<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VFloat;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\ForbiddenRequestException;
use App\Helpers\EmailVerificationHelper;
use App\Security\Identity;

/**
 * Verify user's email addresses.
 */
class EmailVerificationPresenter extends BasePresenter
{

    /**
     * @var EmailVerificationHelper
     * @inject
     */
    public $emailVerificationHelper;

    /**
     * Resend the email for the current user to verify his/her email address.
     * @POST
     * @LoggedIn
     * @throws ForbiddenRequestException
     */
    public function actionResendVerificationEmail()
    {
        $user = $this->getCurrentUser();
        if (!$this->emailVerificationHelper->process($user)) {
            throw new ForbiddenRequestException("Email cannot be sent, please try it later.");
        }

        $this->sendSuccessResponse("OK");
    }

    /**
     * Verify users email.
     * @POST
     * @LoggedIn
     * @throws ForbiddenRequestException
     */
    public function actionEmailVerification()
    {
        $user = $this->getCurrentUser();

        /** @var Identity $identity */
        $identity = $this->getUser()->getIdentity();
        $token = $identity->getToken();

        if ($this->emailVerificationHelper->verify($user, $token)) {
            $user->setVerified();
            $this->users->flush();
        } else {
            throw new ForbiddenRequestException("The email was not verified.");
        }

        $this->sendSuccessResponse("OK");
    }
}
