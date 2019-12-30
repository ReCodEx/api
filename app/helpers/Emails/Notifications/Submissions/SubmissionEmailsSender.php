<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Emails\EmailLinkHelper;
use App\Helpers\Emails\EmailRenderResult;
use App\Helpers\EmailHelper;
use App\Model\Entity\Assignment;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\Group;
use App\Model\Entity\User;
use App\Model\Repository\AssignmentSolutions;
use Nette\Utils\Arrays;
use DateTime;

/**
 * Sending emails on submission evaluation.
 */
class SubmissionEmailsSender
{

  /** @var EmailHelper */
  private $emailHelper;
  /** @var EmailLocalizationHelper */
  private $localizationHelper;
  /** @var AssignmentSolutions */
  private $assignmentSolutions;

  /** @var string */
  private $sender;
  /** @var string */
  private $solutionRedirectUrl;
  /** @var string */
  private $submissionNotificationThreshold;


  /**
   * Constructor.
   * @param EmailHelper $emailHelper
   * @param array $params
   */
  public function __construct(EmailHelper $emailHelper, EmailLocalizationHelper $localizationHelper,
    AssignmentSolutions $assignmentSolutions, array $params)
  {
    $this->emailHelper = $emailHelper;
    $this->localizationHelper = $localizationHelper;
    $this->assignmentSolutions = $assignmentSolutions;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.mff.cuni.cz");
    $this->solutionRedirectUrl = Arrays::get($params, ["solutionRedirectUrl"], "https://recodex.mff.cuni.cz");
    $this->submissionNotificationThreshold = Arrays::get($params, ["submissionNotificationThreshold"], "-5 minutes");
  }

  /**
   * Submission was evaluated and we have to let the user know it.
   * @param AssignmentSolutionSubmission $submission
   * @return bool
   * @throws InvalidStateException
   */
  public function submissionEvaluated(AssignmentSolutionSubmission $submission): bool
  {
    $solution = $submission->getAssignmentSolution();
    $assignment = $solution->getAssignment();
    $user = $solution->getSolution()->getAuthor();
    if ($user === null || $assignment === null || $assignment->getGroup() === null) {
      // group, assignment, or user was deleted => do not send emails
      return false;
    }

    // Handle evaluation notifications...
    return $this->handleEvaluationNotifications($solution, $submission, $user) &&
      $this->handleTeacherNotifications($solution, $submission, $user);
  }

  /**
   * @throws InvalidStateException
   */
  private function handleEvaluationNotifications(AssignmentSolution $solution, AssignmentSolutionSubmission $submission, User $user): bool {
    $threshold = (new DateTime())->modify($this->submissionNotificationThreshold);
    if ($user->getSettings()->getSubmissionEvaluatedEmails() && $solution->getSolution()->getCreatedAt() < $threshold) {
      $locale = $user->getSettings()->getDefaultLanguage();
      $result = $this->createSubmissionEvaluated($solution->getAssignment(), $submission, $locale);

      // Send the mail
      return $this->emailHelper->send(
        $this->sender,
        [$user->getEmail()],
        $locale,
        $result->getSubject(),
        $result->getText()
      );
    }

    return true;
  }

  private function handleTeacherNotifications(AssignmentSolution $solution, AssignmentSolutionSubmission $submission, User $user): bool {
    $success = true;

    // Already approved solution notification
    $best = $this->assignmentSolutions->findBestSolution($solution->getAssignment(), $user);
    $recipients = $this->getGroupSupervisorsRecipients($solution->getAssignment()->getGroup(), "assignmentSubmitAfterAcceptedEmails");
    if ($best->getAccepted() && count($recipients) > 0) {
      $success = $success && $this->localizationHelper->sendLocalizedEmail(
        $recipients,
        function ($toUsers, $emails, $locale) use ($solution, $submission) {
          $result = $this->createNewSubmissionAfterAcceptance($solution->getAssignment(), $solution, $submission, $locale);

          // Send the mail
          return $this->emailHelper->send(
            $this->sender,
            [],
            $locale,
            $result->getSubject(),
            $result->getText(),
            $emails
          );
        }
      );
    }

    // Already reviewed solutions notification
    $recipients = $this->getGroupSupervisorsRecipients($solution->getAssignment()->getGroup(), "assignmentSubmitAfterReviewedEmails");
    if ($this->shouldSendAfterReview($solution, $user) && count($recipients) > 0) {
      $success = $success && $this->localizationHelper->sendLocalizedEmail(
          $recipients,
          function ($toUsers, $emails, $locale) use ($solution, $submission) {
            $result = $this->createNewSubmissionAfterReview($solution->getAssignment(), $solution, $submission, $locale);

            // Send the mail
            return $this->emailHelper->send(
              $this->sender,
              [],
              $locale,
              $result->getSubject(),
              $result->getText(),
              $emails
            );
          }
        );
    }

    return $success;
  }

  /**
   * Return all teachers (supervisors and admins) which are willing to receive these notifications
   * @param Group $group
   * @param string $flag flag from user-settings which decides if email should be sent
   * @return array
   */
  private function getGroupSupervisorsRecipients(Group $group, string $flag): array
  {
    $recipients = [];

    foreach ($group->getSupervisors() as $supervisor) {
      if ($supervisor->getSettings()->getFlag($flag)) {
        $recipients[$supervisor->getId()] = $supervisor;
      }
    }

    foreach ($group->getPrimaryAdmins() as $admin) {
      if ($admin->getSettings()->getFlag($flag)) {
        $recipients[$admin->getId()] = $admin;
      }
    }

    return array_values($recipients);
  }

  private function shouldSendAfterReview(AssignmentSolution $solution, User $user): bool {
    $solutions = $this->assignmentSolutions->findValidSolutions($solution->getAssignment(), $user);
    $anyReviewed = false;
    $anyAccepted = false;
    foreach ($solutions as $oSolution) {
      $anyReviewed = $anyReviewed || $oSolution->isReviewed();
      $anyAccepted = $anyAccepted || $oSolution->isAccepted();
    }

    return $anyReviewed && !$anyAccepted;
  }

  /**
   * Prepare and format body of the mail
   * @param Assignment $assignment
   * @param AssignmentSolutionSubmission $submission
   * @param string $locale
   * @return EmailRenderResult
   * @throws InvalidStateException
   */
  private function createSubmissionEvaluated(Assignment $assignment, AssignmentSolutionSubmission $submission, string $locale): EmailRenderResult
  {
    // render the HTML to string using Latte engine
    $latte = EmailLatteFactory::latte();
    $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/submissionEvaluated_{locale}.latte");
    return $latte->renderEmail($template, [
      "assignment" => EmailLocalizationHelper::getLocalization($locale, $assignment->getLocalizedTexts())->getName(),
      "group" => EmailLocalizationHelper::getLocalization($locale, $assignment->getGroup()->getLocalizedTexts())->getName(),
      "date" => $submission->getEvaluation()->getEvaluatedAt(),
      "status" => $submission->isCorrect() === true ? "success" : "failure",
      "points" => $submission->getEvaluation()->getPoints(),
      "maxPoints" => $assignment->getMaxPoints($submission->getEvaluation()->getEvaluatedAt()),
      "link" => EmailLinkHelper::getLink($this->solutionRedirectUrl, [
        "assignmentId" => $assignment->getId(),
        "solutionId" => $submission->getAssignmentSolution()->getId(),
      ]),
    ]);
  }

  /**
   * Prepare and format body of the "new submission after the acceptance" mail
   * @param Assignment $assignment
   * @param AssignmentSolution $solution
   * @param AssignmentSolutionSubmission $submission
   * @param string $locale
   * @return EmailRenderResult
   * @throws InvalidStateException
   */
  private function createNewSubmissionAfterAcceptance(Assignment $assignment, AssignmentSolution $solution,
    AssignmentSolutionSubmission $submission, string $locale): EmailRenderResult
  {
    // render the HTML to string using Latte engine
    $latte = EmailLatteFactory::latte();
    $user = $solution->getSolution()->getAuthor();
    $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/newSubmissionAfterAcceptance_{locale}.latte");
    return $latte->renderEmail($template, [
      "assignment" => EmailLocalizationHelper::getLocalization($locale, $assignment->getLocalizedTexts())->getName(),
      "user" => $user ? $user->getName() : "",
      "score" => (int)($submission->getEvaluation()->getScore()*100),
      "link" => EmailLinkHelper::getLink($this->solutionRedirectUrl, [
        "assignmentId" => $assignment->getId(),
        "solutionId" => $solution->getId(),
      ]),
    ]);
  }

  /**
   * Prepare and format body of the "new submission after the review" mail
   * @param Assignment $assignment
   * @param AssignmentSolution $solution
   * @param AssignmentSolutionSubmission $submission
   * @param string $locale
   * @return EmailRenderResult
   * @throws InvalidStateException
   */
  private function createNewSubmissionAfterReview(Assignment $assignment, AssignmentSolution $solution,
                                                  AssignmentSolutionSubmission $submission, string $locale): EmailRenderResult
  {
    // render the HTML to string using Latte engine
    $latte = EmailLatteFactory::latte();
    $user = $solution->getSolution()->getAuthor();
    $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/newSubmissionAfterReview_{locale}.latte");
    return $latte->renderEmail($template, [
      "assignment" => EmailLocalizationHelper::getLocalization($locale, $assignment->getLocalizedTexts())->getName(),
      "user" => $user ? $user->getName() : "",
      "score" => (int)($submission->getEvaluation()->getScore()*100),
      "link" => EmailLinkHelper::getLink($this->solutionRedirectUrl, [
        "assignmentId" => $assignment->getId(),
        "solutionId" => $solution->getId(),
      ]),
    ]);
  }
}
