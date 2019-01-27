<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\EmailHelper;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Emails\EmailLinkHelper;
use App\Model\Entity\Assignment;
use App\Model\Entity\AssignmentBase;
use App\Model\Entity\ShadowAssignment;
use App\Model\Entity\User;
use App\Model\Repository\AssignmentSolutions;
use Nette\Utils\Arrays;

/**
 * Sending emails on assignment creation.
 */
class AssignmentEmailsSender {

  /** @var EmailHelper */
  private $emailHelper;
  /** @var AssignmentSolutions */
  private $assignmentSolutions;
  /** @var EmailLocalizationHelper */
  private $localizationHelper;

  /** @var string */
  private $sender;
  /** @var string */
  private $newAssignmentPrefix;
  /** @var string */
  private $assignmentDeadlinePrefix;
  /** @var string */
  private $assignmentRedirectUrl;
  /** @var string */
  private $shadowRedirectUrl;


  /**
   * Constructor.
   * @param EmailHelper $emailHelper
   * @param AssignmentSolutions $assignmentSolutions
   * @param EmailLocalizationHelper $localizationHelper
   * @param array $params
   */
  public function __construct(EmailHelper $emailHelper, AssignmentSolutions $assignmentSolutions, EmailLocalizationHelper $localizationHelper, array $params) {
    $this->emailHelper = $emailHelper;
    $this->assignmentSolutions = $assignmentSolutions;
    $this->localizationHelper = $localizationHelper;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.mff.cuni.cz");
    $this->newAssignmentPrefix = Arrays::get($params, ["emails", "newAssignmentPrefix"], "New Assignment - ");
    $this->assignmentDeadlinePrefix = Arrays::get($params, ["emails", "assignmentDeadlinePrefix"], "Assignment Deadline Is Around the Corner - ");
    $this->assignmentRedirectUrl = Arrays::get($params, ["assignmentRedirectUrl"], "https://recodex.mff.cuni.cz");
    $this->shadowRedirectUrl = Arrays::get($params, ["shadowRedirectUrl"], "https://recodex.mff.cuni.cz");
  }

  /**
   * Assignment was created, send emails to users who wanted to know this
   * situation.
   * @param AssignmentBase $assignment
   * @return boolean
   */
  public function assignmentCreated(AssignmentBase $assignment): bool {
    if ($assignment->getGroup() === null) {
      // group was deleted, do not send emails
      return false;
    }

    $recipients = array();
    foreach ($assignment->getGroup()->getStudents() as $student) {
      if (!$student->getSettings()->getNewAssignmentEmails()) {
        continue;
      }
      $recipients[] = $student;
    }

    if (count($recipients) === 0) {
      return true;
    }

    return $this->localizationHelper->sendLocalizedEmail(
      $recipients,
      function ($toUsers, $emails, $locale) use ($assignment) {
        $subject = $this->newAssignmentPrefix .
          EmailLocalizationHelper::getLocalization($locale, $assignment->getLocalizedTexts())->getName();

        // Send the mail
        return $this->emailHelper->send(
          $this->sender,
          [],
          $locale,
          $subject,
          $this->createNewAssignmentBody($assignment, $locale),
          $emails
        );
      }
    );
  }

  /**
   * Prepare and format body of the new assignment mail
   * @param AssignmentBase $assignment
   * @param string $locale
   * @return string Formatted mail body to be sent
   * @throws InvalidStateException
   */
  private function createNewAssignmentBody(AssignmentBase $assignment, string $locale): string {
    // render the HTML to string using Latte engine
    $latte = EmailLatteFactory::latte();
    if ($assignment instanceof Assignment) {
      $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/newAssignmentEmail_{locale}.latte");
      return $latte->renderToString($template, [
        "assignment" => EmailLocalizationHelper::getLocalization($locale, $assignment->getLocalizedTexts())->getName(),
        "group" => EmailLocalizationHelper::getLocalization($locale, $assignment->getGroup()->getLocalizedTexts())->getName(),
        "firstDeadline" => $assignment->getFirstDeadline(),
        "allowSecondDeadline" => $assignment->getAllowSecondDeadline(),
        "secondDeadline" => $assignment->getSecondDeadline(),
        "attempts" => $assignment->getSubmissionsCountLimit(),
        "points" => $assignment->getMaxPointsBeforeFirstDeadline(),
        "link" => EmailLinkHelper::getLink($this->assignmentRedirectUrl, ["id" => $assignment->getId()])
      ]);
    } else if ($assignment instanceof ShadowAssignment) {
      $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/newShadowAssignmentEmail_{locale}.latte");
      return $latte->renderToString($template, [
        "name" => EmailLocalizationHelper::getLocalization($locale, $assignment->getLocalizedTexts())->getName(),
        "group" => EmailLocalizationHelper::getLocalization($locale, $assignment->getGroup()->getLocalizedTexts())->getName(),
        "maxPoints" => $assignment->getMaxPoints(),
        "link" => EmailLinkHelper::getLink($this->shadowRedirectUrl, ["id" => $assignment->getId()])
      ]);
    } else {
      throw new InvalidStateException("Unknown type of assignment");
    }
  }

  /**
   * Deadline of assignment is nearby so users who did not submit any solution
   * should be alerted.
   * @param Assignment $assignment
   * @return bool
   * @throws InvalidStateException
   */
  public function assignmentDeadline(Assignment $assignment): bool {
    if ($assignment->getGroup() === null) {
      // group was deleted, do not send emails
      return false;
    }

    $recipients = array();
    /** @var User $student */
    foreach ($assignment->getGroup()->getStudents() as $student) {
      if (count($this->assignmentSolutions->findSolutions($assignment, $student)) > 0 ||
          !$student->getSettings()->getAssignmentDeadlineEmails()) {
        // student already submitted solution to this assignment or
        // disabled sending emails
        continue;
      }

      $recipients[] = $student;
    }

    if (count($recipients) === 0) {
      return true;
    }

    return $this->localizationHelper->sendLocalizedEmail(
      $recipients,
      function ($toUsers, $emails, $locale) use ($assignment) {
        $subject = $this->assignmentDeadlinePrefix .
          EmailLocalizationHelper::getLocalization($locale, $assignment->getLocalizedTexts())->getName();

        // Send the mail
        return $this->emailHelper->send(
          $this->sender,
          [],
          $locale,
          $subject,
          $this->createAssignmentDeadlineBody($assignment, $locale),
          $emails
        );
      }
    );
  }

  /**
   * Prepare and format body of the assignment deadline mail
   * @param Assignment $assignment
   * @param string $locale
   * @return string Formatted mail body to be sent
   * @throws InvalidStateException
   */
  private function createAssignmentDeadlineBody(Assignment $assignment, string $locale): string {
    // render the HTML to string using Latte engine
    $latte = EmailLatteFactory::latte();
    $localizedGroup = EmailLocalizationHelper::getLocalization($locale, $assignment->getGroup()->getLocalizedTexts());
    $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/assignmentDeadline_{locale}.latte");
    return $latte->renderToString($template, [
      "assignment" => EmailLocalizationHelper::getLocalization($locale, $assignment->getLocalizedTexts())->getName(),
      "group" => $localizedGroup ? $localizedGroup->getName() : "",
      "firstDeadline" => $assignment->getFirstDeadline(),
      "allowSecondDeadline" => $assignment->getAllowSecondDeadline(),
      "secondDeadline" => $assignment->getSecondDeadline(),
      "link" => EmailLinkHelper::getLink($this->assignmentRedirectUrl, ["id" => $assignment->getId()])
    ]);
  }

}
