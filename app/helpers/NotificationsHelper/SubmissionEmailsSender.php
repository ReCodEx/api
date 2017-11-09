<?php

namespace App\Helpers\Notifications;

use Latte;
use App\Helpers\EmailHelper;
use App\Model\Entity\AssignmentSolution;
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
    $this->submissionEvaluatedPrefix = Arrays::get($params, ["emails", "submissionEvaluatedPrefix"], "ReCodEx Submission Evaluated Notification - ");
  }

  /**
   * Submission was evaluated and we have to let the user know it.
   * @param AssignmentSolution $submission
   * @return bool
   */
  public function submissionEvaluated(AssignmentSolution $submission): bool {
    $subject = $this->submissionEvaluatedPrefix . $submission->getAssignment()->getLocalizedTexts()->first()->getName(); // TODO

    $user = $submission->getSolution()->getAuthor();
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
   * @param AssignmentSolution $submission
   * @return string Formatted mail body to be sent
   */
  private function createSubmissionEvaluatedBody(AssignmentSolution $submission): string {
    // render the HTML to string using Latte engine
    $latte = new Latte\Engine;
    return $latte->renderToString(__DIR__ . "/submissionEvaluated.latte", [
      "assignment" => $submission->getAssignment()->getLocalizedTexts()->first()->getName(), // TODO
      "group" => $submission->getAssignment()->getGroup()->getName(),
      "date" => $submission->getEvaluation()->getEvaluatedAt(),
      "status" => $submission->isCorrect() === true ? "was successful" : "failed",
      "points" => $submission->getEvaluation()->getPoints(),
      "maxPoints" => $submission->getAssignment()->getMaxPoints($submission->getEvaluation()->getEvaluatedAt())
    ]);
  }

}
