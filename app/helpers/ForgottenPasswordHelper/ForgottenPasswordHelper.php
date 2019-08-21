<?php

namespace App\Helpers;

use App\Exceptions\InvalidStateException;
use App\Helpers\Emails\EmailLinkHelper;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Security\TokenScope;
use Exception;
use Latte;
use Nette\Utils\Arrays;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Login;
use App\Model\Entity\ForgottenPassword;
use App\Security\AccessManager;
use DateTime;
use DateInterval;

/**
 * Provides all necessary things which are needed on forgotten password request.
 */
class ForgottenPasswordHelper {

  /**
   * Database entity manager
   * @var EntityManager
   */
  private $em;

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
   * Expiration period of the change-password token in seconds
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
  public function __construct(EntityManager $em, EmailHelper $emailHelper, AccessManager $accessManager, array $params) {
    $this->em = $em;
    $this->emailHelper = $emailHelper;
    $this->accessManager = $accessManager;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.mff.cuni.cz");
    $this->subjectPrefix = Arrays::get($params, ["emails", "subjectPrefix"], "Password Recovery Request - ");
    $this->redirectUrl = Arrays::get($params, ["redirectUrl"], "https://recodex.mff.cuni.cz");
    $this->tokenExpiration = Arrays::get($params, ["tokenExpiration"], 10 * 60); // default value: 10 minutes
  }

  /**
   * Generate access token and send it to the given email.
   * @param Login $login
   * @param string $IPaddress IP address of change request client (from request headers)
   * @return bool If sending was successful or not
   * @throws Exception
   */
  public function process(Login $login, string $IPaddress) {
    // Stalk forgotten password requests a little bit and store them to database
    $entry = new ForgottenPassword($login->getUser(), $login->getUser()->getEmail(), $this->redirectUrl, $IPaddress);
    $this->em->persist($entry);
    $this->em->flush();

    // prepare all necessary things
    $token = $this->accessManager->issueToken($login->getUser(), null,
      [TokenScope::CHANGE_PASSWORD], $this->tokenExpiration);

    $locale = $login->getUser()->getSettings()->getDefaultLanguage();
    $subject = $this->createSubject($login);
    $message = $this->createBody($login, $locale, $token);

    // Send the mail
    return $this->emailHelper->send(
      $this->sender,
      [ $login->getUser()->getEmail() ],
      $locale,
      $subject,
      $message
    );
  }

  /**
   * Creates and returns subject of email message.
   * @param Login $login
   * @return string
   */
  private function createSubject(Login $login): string {
    return $this->subjectPrefix . $login->getUsername();
  }

  /**
   * Creates and return body of email message.
   * @param Login $login
   * @param string $locale
   * @param string $token
   * @return string
   * @throws InvalidStateException
   */
  private function createBody(Login $login, string $locale, string $token): string {
    // show to user a minute less, so he doesn't waste time ;-)
    $exp = $this->tokenExpiration - 60;
    $expiresAfter = (new DateTime())->add(new DateInterval("PT{$exp}S"));

    // render the HTML to string using Latte engine
    $latte = new Latte\Engine();
    $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/resetPasswordEmail_{locale}.latte");
    return $latte->renderToString($template, [
      "username" => $login->getUsername(),
      "link" => EmailLinkHelper::getLink($this->redirectUrl, ["token" => $token]),
      "expiresAfter" => $expiresAfter->format("H:i")
    ]);
  }

}
