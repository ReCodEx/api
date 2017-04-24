<?php

namespace App\Helpers;

use Latte;
use Nette\Utils\Arrays;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\User;
use App\Security\AccessToken;
use App\Security\AccessManager;
use DateTime;
use DateInterval;

/**
 * Provides all necessary things which are needed on email verification request.
 */
class EmailVerificationHelper {

  /**
   * Emails sending component
   * @var EmailHelper
   */
  private $emailHelper;

  /**
   * Sender address of all mails, something like "noreply@recodex.cz"
   * @var string
   */
  private $sender;

  /**
   * Prefix of mail subject to be used
   * @var string
   */
  private $subjectPrefix;

  /**
   * URL which will be sent to user with token.
   * @var string
   */
  private $redirectUrl;

  /**
   * Expiration period of the token in seconds
   * @var int
   */
  private $tokenExpiration;

  /**
   * @var AccessManager
   */
  private $accessManager;

  /**
   * Constructor
   * @param EntityManager $em
   * @param EmailHelper $emailHelper
   * @param AccessManager $accessManager
   * @param array $params Parameters from configuration file
   */
  public function __construct(EmailHelper $emailHelper, AccessManager $accessManager, array $params) {
    $this->emailHelper = $emailHelper;
    $this->accessManager = $accessManager;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.cz");
    $this->subjectPrefix = Arrays::get($params, ["emails", "subjectPrefix"], "ReCodEx Email Verification Request - ");
    $this->redirectUrl = Arrays::get($params, ["redirectUrl"], "https://recodex.cz");
    $this->tokenExpiration = Arrays::get($params, ["tokenExpiration"], 10 * 60); // default value: 10 minutes
  }

  /**
   * Generate access token and send it to the given email.
   * @param User $user
   * @return bool If sending was successful or not
   */
  public function process(User $user) {
    // prepare all necessary things
    $token = $this->accessManager->issueToken($user, [ AccessToken::SCOPE_EMAIL_VERIFICATION ], $this->tokenExpiration);
    $subject = $this->createSubject($user);
    $message = $this->createBody($user, $token);

    // Send the mail
    return $this->emailHelper->send(
      $this->sender,
      [ $user->getEmail() ],
      $subject,
      $message
    );
  }

  /**
   * Verify email verification token against given user.
   * @param User $user
   * @param string $token
   * @return boolean
   */
  public function verify(User $user, string $token) {
    return FALSE; // TODO
  }

  /**
   * Creates and returns subject of email message.
   * @param User $user
   * @return string
   */
  private function createSubject(User $user): string {
    return $this->subjectPrefix . " " . $user->getEmail();
  }

  /**
   * Creates and return body of email message.
   * @param User $user
   * @param string $token
   * @return string
   */
  private function createBody(User $user, string $token): string {
    // show to user a minute less, so he doesn't waste time ;-)
    $exp = $this->tokenExpiration - 60;
    $expiresAfter = (new DateTime)->add(new DateInterval("PT{$exp}S"));

    // render the HTML to string using Latte engine
    $latte = new Latte\Engine;
    return $latte->renderToString(__DIR__ . "/verificationEmail.latte", [
      "email" => $user->getEmail(),
      "link" => "{$this->redirectUrl}#{$token}",
      "expiresAfter" => $expiresAfter->format("H:i")
    ]);
  }

}
