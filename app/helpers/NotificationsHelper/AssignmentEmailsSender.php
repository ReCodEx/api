<?php

namespace App\Helpers\Notifications;

use App\Helpers\EmailsConfig;
use App\Helpers\EmailHelper;
use App\Model\Entity\Assignment;

/**
 * Sending emails on assignment creation or change.
 */
class AssignmentEmailsSender {

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
   * Assignment was created, send emails to users who wanted to know this
   * situation.
   * @param Assignment $assignment
   * @return boolean
   */
  public function assignmentCreated(Assignment $assignment) {
    $subject = $this->formatSubject($assignment->getName());
    $message = ""; // TODO

    $recipients = array();
    foreach ($assignment->getGroup()->getStudents() as $student) {
      $recipients[] = $student->getEmail(); // TODO: make sure user want to receive email notifications
    }

    // Send the mail
    return $this->emailHelper->send(
      $this->emailsConfig->getFrom(),
      $recipients,
      $subject,
      $this->formatBody($message)
    );
  }

  /**
   * Prepare mail subject for assignment
   * @param string $name Name of the assignment
   * @return string Mail subject
   */
  private function formatSubject(string $name): string {
    // TODO
    //return $this->subjectPrefix . $name;
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
