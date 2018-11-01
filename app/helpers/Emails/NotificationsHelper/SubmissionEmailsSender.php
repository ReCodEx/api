<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\EmailLocalizationHelper;
use App\Model\Entity\AssignmentSolutionSubmission;
use Latte;
use App\Helpers\EmailHelper;
use Nette\Utils\Arrays;

/**
 * Sending emails on submission evaluation.
 */
class SubmissionEmailsSender {

  /** @var EmailHelper */
  private $emailHelper;
  /** @var string */
  private $sender;
  /** @var string */
  private $submissionEvaluatedPrefix;
  /** @var EmailLocalizationHelper */
  private $localizationHelper;

  /**
   * Constructor.
   * @param EmailHelper $emailHelper
   * @param EmailLocalizationHelper $localizationHelper
   * @param array $params
   */
  public function __construct(EmailHelper $emailHelper, EmailLocalizationHelper $localizationHelper, array $params) {
    $this->emailHelper = $emailHelper;
    $this->localizationHelper = $localizationHelper;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.mff.cuni.cz");
    $this->submissionEvaluatedPrefix = Arrays::get($params, ["emails", "submissionEvaluatedPrefix"], "Submission Evaluated - ");
  }

  /**
   * Submission was evaluated and we have to let the user know it.
   * @param AssignmentSolutionSubmission $submission
   * @return bool
   * @throws InvalidStateException
   */
  public function submissionEvaluated(AssignmentSolutionSubmission $submission): bool {
    $assignment = $submission->getAssignmentSolution()->getAssignment();
    $subject = $this->submissionEvaluatedPrefix . $this->localizationHelper->getLocalization($assignment->getLocalizedTexts())->getName();

    $user = $submission->getAssignmentSolution()->getSolution()->getAuthor();
    if (!$user->getSettings()->getSubmissionEvaluatedEmails()) {
      return true;
    }

    // Send the mail
    return $this->emailHelper->send(
      $this->sender,
      [$user->getEmail()],
      $subject,
      $this->createSubmissionEvaluatedBody($submission)
    );
  }

  /**
   * Prepare and format body of the mail
   * @param AssignmentSolutionSubmission $submission
   * @return string Formatted mail body to be sent
   * @throws InvalidStateException
   */
  private function createSubmissionEvaluatedBody(AssignmentSolutionSubmission $submission): string {
    $assignment = $submission->getAssignmentSolution()->getAssignment();

    // render the HTML to string using Latte engine
    $latte = new Latte\Engine();
    $template = $this->localizationHelper->getTemplate(__DIR__ . "/submissionEvaluated_{locale}.latte");
    return $latte->renderToString($template, [
      "assignment" => $this->localizationHelper->getLocalization($assignment->getLocalizedTexts())->getName(),
      "group" => $this->localizationHelper->getLocalization($assignment->getGroup()->getLocalizedTexts())->getName(),
      "date" => $submission->getEvaluation()->getEvaluatedAt(),
      "status" => $submission->isCorrect() === true ? "was successful" : "failed",
      "points" => $submission->getEvaluation()->getPoints(),
      "maxPoints" => $assignment->getMaxPoints($submission->getEvaluation()->getEvaluatedAt())
    ]);
  }

}
