<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
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
        $this->sendSuccessResponse("OK");
    }
}
