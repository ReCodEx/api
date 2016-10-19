<?php

namespace App\Helpers;

use Nette\Utils\Arrays;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Login;
use App\Model\Entity\ForgottenPassword;

/**
 * Sending error reports to administrator by email.
 */
class ForgottenPasswordHelper {

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
  public function process(Login $login, string $redirectUrl) {
    $user = $login->user;
    $email = $user->email;

    // Save the report to the database
    $entry = new ForgottenPassword($user, $email);
    $this->em->persist($entry);
    $this->em->flush();

    // Send the mail
    return $this->emailHelper->send(
      $this->sender,
      [ $user->email ],
      $this->subjectPrefix,
      $this->formatBody($message)
    );
  }

}
