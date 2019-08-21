<?php

namespace App\Helpers;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidAccessTokenException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\InvalidStateException;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLinkHelper;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Security\TokenScope;
use Exception;
use Latte;
use Nette\Utils\Arrays;
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
   * Sender address of all mails, something like "noreply@recodex.mff.cuni.cz"
   * @var string
   */
  private $sender;

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
   * @param EmailHelper $emailHelper
   * @param AccessManager $accessManager
   * @param array $params Parameters from configuration file
   */
  public function __construct(EmailHelper $emailHelper, AccessManager $accessManager, array $params) {
    $this->emailHelper = $emailHelper;
    $this->accessManager = $accessManager;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.mff.cuni.cz");
    $this->redirectUrl = Arrays::get($params, ["redirectUrl"], "https://recodex.mff.cuni.cz");
    $this->tokenExpiration = Arrays::get($params, ["tokenExpiration"], 10 * 60); // default value: 10 minutes
  }

  /**
   * Generate access token and send it to the given email.
   * @param User $user
   * @param bool $firstTime True if this is the first time the verification is requested (account has just been created).
   * @return bool If sending was successful or not
   * @throws InvalidStateException
   */
  public function process(User $user, bool $firstTime = false) {
    // prepare all necessary things
    $token = $this->accessManager->issueToken(
      $user, null, [TokenScope::EMAIL_VERIFICATION], $this->tokenExpiration, ["email" => $user->getEmail()]
    );

    return $this->sendEmail($user, $token, $firstTime);
  }

  /**
   * Verify email verification token against given user.
   * @param User $user
   * @param AccessToken $token
   * @return bool
   * @throws ForbiddenRequestException
   * @throws InvalidAccessTokenException
   * @throws InvalidArgumentException
   */
  public function verify(User $user, AccessToken $token) {
    // the token is parsed, which means, it has already been validated in terms of exp, iat, ...
    // the only verification steps are:
    // 1] correct scope
    // 2] the IDs and emails of the user and the token are the same

    if (!$token->isInScope(TokenScope::EMAIL_VERIFICATION)) {
      throw new ForbiddenRequestException("You cannot verify email with this access token.");
    }

    return $user->getId() === $token->getUserId() && $user->getEmail() === $token->getPayload("email");
  }

  /**
   * Send an email with the token for the verification of the email address of the user.
   * @param User $user
   * @param string $token
   * @param bool $firstTime
   * @return bool
   * @throws InvalidStateException
   * @throws Exception
   */
  private function sendEmail(User $user, string $token, bool $firstTime = false): bool {
    $locale = $user->getSettings()->getDefaultLanguage();
    list($subject, $message) = $this->createEmail($user, $locale, $token, $firstTime);

    // Send the mail
    return $this->emailHelper->send(
      $this->sender,
      [ $user->getEmail() ],
      $locale,
      $subject,
      $message
    );
  }

  /**
   * Creates and return body of email message.
   * @param User $user
   * @param string $locale
   * @param string $token
   * @param bool $firstTime
   * @return string[]
   * @throws InvalidStateException
   */
  private function createEmail(User $user, string $locale, string $token, bool $firstTime): array {
    // show to user a minute less, so he doesn't waste time ;-)
    $exp = $this->tokenExpiration - 60;
    $expiresAfter = (new DateTime())->add(new DateInterval("PT{$exp}S"));

    // render the HTML to string using Latte engine
    $latte = EmailLatteFactory::latte();
    $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/verificationEmail_{locale}.latte");
    return $latte->renderEmail($template, [
      "email" => $user->getEmail(),
      "link" => EmailLinkHelper::getLink($this->redirectUrl, ["token" => $token]),
      "expiresAfter" => $expiresAfter->format("H:i"),
      "firstTime" => $firstTime,
    ]);
  }

}
