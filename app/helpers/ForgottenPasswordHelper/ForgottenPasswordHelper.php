<?php

namespace App\Helpers;

use Nette\Utils\Arrays;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Login;
use App\Model\Entity\ForgottenPassword;
<<<<<<< 93e03221d111374794cf77777978438e514dcbe1
use App\Security\AccessManager;
=======
>>>>>>> WIP: ForgottenPassword

/**
 * Sending error reports to administrator by email.
 */
class ForgottenPasswordHelper {

<<<<<<< 93e03221d111374794cf77777978438e514dcbe1
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
   * @var AccessManager
   */
  private $accessManager;

  /**
   * Constructor
   * @param \App\Helpers\EmailHelper $emailHelper
   */
  public function __construct(EntityManager $em, EmailHelper $emailHelper, AccessManager $accessManager , array $params) {
    $this->em = $em;
    $this->emailHelper = $emailHelper;
    $this->accessManager = $accessManager;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.cz");
    $this->subjectPrefix = Arrays::get($params, ["emails", "subjectPrefix"], "ReCodEx Forgotten Password Request - ");
    $this->redirectUrl = Arrays::get($params, ["redirectUrl"], "https://recodex.cz");
=======
  /** @var EntityManager Database entity manager */
  private $em;

  /** @var EmailHelper Emails sending component */
  private $emailHelper;

  /** @var string Sender address of all mails, something like "noreply@recodex.cz" */
  private $sender;

  /** @var string Prefix of mail subject to be used */
  private $subjectPrefix;

  /**
   * Constructor
   * @param \App\Helpers\EmailHelper $emailHelper
   */
  public function __construct(EntityManager $em, EmailHelper $emailHelper, array $params) {
    $this->em = $em;
    $this->emailHelper = $emailHelper;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.cz");
    $this->subjectPrefix = Arrays::get($params, ["emails", "subjectPrefix"], "ReCodEx Forgotten Password Request - ");
  }

  /**
   * Generate access token and send it to the given email.
   * @param Login $login
   */
  public function process(Login $login) {
    // Stalk forgotten password requests a little bit and store them to database
    $entry = new ForgottenPassword($login->user, $login->user->email, $this->redirectUrl);
    $this->em->persist($entry);
    $this->em->flush();

    // prepare all necessary things
    $token = $this->accessManager->issueToken($login->user, [ "modify-password" ]); // TODO: token has to be valid for only few minutes
    $subject = $this->createSubject($login);
    $message = $this->createBody($login, $token);

    // Send the mail
    return $this->emailHelper->send(
      $this->sender,
      [ $login->user->email ],
      $subject,
      $message
    );
  }

  private function createSubject(Login $login): string {
    return $this->subjectPrefix . " " . $login->username;
  }

  private function createBody(Login $login, string $token): string {
    $msg = "User " . $login->username . " requested password renewal.<br>";
    $msg .= "Password can be changed after clicking on this ";
    $msg .= "<a href=\"" . $this->redirectUrl . "#" . $token . "\">link</a>";
    return $msg;
  }

}
