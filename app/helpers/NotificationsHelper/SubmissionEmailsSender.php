<?php

namespace App\Helpers\Notifications;

use App\Helpers\EmailsConfig;
use App\Helpers\EmailHelper;
use App\Model\Entity\Submission;

/**
 * Sending emails on submission evaluation.
 */
class SubmissionEmailsSender {

  /** @var EmailHelper */
  private $emailHelper;

  /** @var EmailsConfig */
  private $emailsConfig;

  /**
   * Constructor.
   * @param EmailHelper $emailHelper
   * @param EmailsConfig $emailsConfig
   */
  public function __construct(EmailHelper $emailHelper, EmailsConfig $emailsConfig) {
    $this->emailHelper = $emailHelper;
    $this->emailsConfig = $emailsConfig;
  }

  /**
   * Submission was evaluated and we have to let the user know it.
   * @param Submission $submission
   * @return boolean
   */
  public function assignmentCreated(Submission $submission) {
    $subject = $this->formatSubject($submission->getAssignment()->getName());
    $message = ""; // TODO

    $recipients = array();
    $recipients[] = $submission->getUser()->getEmail();

    // Send the mail
    return $this->emailHelper->send(
      $this->emailsConfig->getFrom(),
      $recipients,
      $subject,
      $this->formatBody($message)
    );
  }

  /**
   * Prepare mail subject for submission and evaluation
   * @param string $name Name of the assignment
   * @return string Mail subject
   */
  private function formatSubject(string $name): string {
    return $this->subjectPrefix . $name; // TODO
  }

  /**
   * Prepare and format body of the mail
   * @param string $message Message of the email notification
   * @return string Formatted mail body to be sent
   */
  private function formatBody(string $message): string {
    return $message; // @todo
  }

}
