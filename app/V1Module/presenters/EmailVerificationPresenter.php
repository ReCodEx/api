<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Helpers\EmailVerificationHelper;
use App\Security\Identity;

/**
 * Verify user's email addresses.
 */
class EmailVerificationPresenter extends BasePresenter {

  /**
   * @var EmailVerificationHelper
   * @inject
   */
  public $emailVerificationHelper;

  /**
   * Resend the email for the current user to verify his/her email address.
   * @POST
   * @LoggedIn
   */
  public function actionResendVerificationEmail() {
    $user = $this->getCurrentUser();
    if (!$this->emailVerificationHelper->process($user)) {
      throw new ForbiddenRequestException("You cannot request another verification email.");
    }

    $this->sendSuccessResponse("OK");
  }

  /**
   * Verify users email.
   * @POST
   * @LoggedIn
   */
  public function actionEmailVerification() {
    $user = $this->getCurrentUser();

    /** @var Identity $identity */
    $identity = $this->getUser()->getIdentity();
    $token = $identity->getToken();

    if ($this->emailVerificationHelper->verify($user, $token)) {
      $user->setVerified();
      $this->users->flush();
    } else {
      throw new ForbiddenRequestException("You cannot verify email with this access token.");
    }

    $this->sendSuccessResponse("OK");
  }

}
