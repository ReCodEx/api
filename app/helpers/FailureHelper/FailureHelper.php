<?php

namespace App\Helpers;

use Nette\Mail\IMailer;
use Nette\Mail\Message;
use Nette\Utils\Arrays;
use Latte;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\ReportedErrors;

/**
 * Sending error reports to administrator by email.
 */
class FailureHelper {

  const TYPE_BACKEND_ERROR = "BACKEND ERROR";
  const TYPE_API_ERROR = "API ERROR";

  /** @var EmailHelper Emails sending component */
  private $emailHelper;

  /** @var array List of email addresses which will receive the reports */
  private $receivers;

  /** @var string Sender address of all mails, something like "noreply@recodex.cz" */
  private $sender;

  /** @var string Prefix of mail subject to be used */
  private $subjectPrefix;

  /** @var EntityManager Database entity manager */
  private $em;

  /**
   * Constructor
   * @param EntityManager $em          Database entity manager
   * @param EmailHelper   $emailHelper Instance of object which is able to sending mails
   * @param array         $params      Array of configurable options like destination addresses etc.
   */
  public function __construct(EntityManager $em, EmailHelper $emailHelper, array $params) {
    $this->em = $em;
    $this->emailHelper = $emailHelper;
    $this->receivers = Arrays::get($params, ["emails", "to"], [ "admin@recodex.org" ]);
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.org");
    $this->subjectPrefix = Arrays::get($params, ["emails", "subjectPrefix"], "ReCodEx Failure Report - ");

    if (!is_array($this->receivers)) {
      $this->receivers = [ $this->receivers ];
    }
  }

  /**
   * Report an issue in system to administrator
   * @param string $type Type of the error like backend error or api error
   * @param string $message Text of the error message
   * @return bool
   */
  public function report(string $type, string $message) {
    $subject = $this->formatSubject($type);
    $recipients = implode(",", $this->receivers);

    // Save the report to the database
    $entry = new ReportedErrors($type, $recipients, $subject, $message);
    $this->em->persist($entry);
    $this->em->flush();

    // Send the mail
    return $this->emailHelper->send(
      $this->sender,
      $this->receivers,
      $subject,
      $this->formatBody($message)
    );
  }

  /**
   * Prepare mail subject for each error type
   * @param string $type Type of error
   * @return string Mail subject for this type of error
   */
  private function formatSubject(string $type): string {
    return $this->subjectPrefix . $type;
  }

  /**
   * Prepare and format body of the mail
   * @param string $message Error message to be reported
   * @return string Formatted mail body to be sent
   */
  private function formatBody(string $message): string {
    return $message; // @todo
  }

}
