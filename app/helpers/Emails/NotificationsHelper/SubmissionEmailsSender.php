<?php

namespace App\Helpers\Notifications;

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

  /**
   * Constructor.
   * @param EmailHelper $emailHelper
   * @param array $params
   */
  public function __construct(EmailHelper $emailHelper, array $params) {
    $this->emailHelper = $emailHelper;
    $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex.cz");
    $this->submissionEvaluatedPrefix = Arrays::get($params, ["emails", "submissionEvaluatedPrefix"], "Submission Evaluated - ");
  }

  /**
   * Submission was evaluated and we have to let the user know it.
   * @param AssignmentSolutionSubmission $submission
   * @return bool
   */
  public function submissionEvaluated(AssignmentSolutionSubmission $submission): bool {
    $subject = $this->submissionEvaluatedPrefix . $submission->getAssignmentSolution()->getAssignment()->getLocalizedTexts()->first()->getName(); // TODO

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
   */
  private function createSubmissionEvaluatedBody(AssignmentSolutionSubmission $submission): string {
    $assignment = $submission->getAssignmentSolution()->getAssignment();

    // render the HTML to string using Latte engine
    $latte = new Latte\Engine();
    return $latte->renderToString(__DIR__ . "/submissionEvaluated.latte", [
      "assignment" => $assignment->getLocalizedTexts()->first()->getName(), // TODO
      "group" => $assignment->getGroup()->getLocalizedTexts()->first()->getName(), // TODO
      "date" => $submission->getEvaluation()->getEvaluatedAt(),
      "status" => $submission->isCorrect() === true ? "was successful" : "failed",
      "points" => $submission->getEvaluation()->getPoints(),
      "maxPoints" => $assignment->getMaxPoints($submission->getEvaluation()->getEvaluatedAt())
    ]);
  }

}
