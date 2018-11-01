<?php

namespace App\Helpers\Notifications;

use App\Helpers\EmailHelper;
use App\Helpers\EmailLocalizationHelper;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Comment;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\User;
use Latte;
use Nette\Utils\Arrays;

/**
 * Sending emails on solution commentary.
 */
class SolutionCommentsEmailsSender {

  /** @var EmailHelper */
  private $emailHelper;
  /** @var string */
  private $sender;
  /** @var string */
  private $assignmentSolutionCommentPrefix;
  /** @var string */
  private $referenceSolutionCommentPrefix;
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
    $this->assignmentSolutionCommentPrefix = Arrays::get($params, ["emails", "assignmentSolutionCommentPrefix"], "Assignment Solution Comment - ");
    $this->referenceSolutionCommentPrefix = Arrays::get($params, ["emails", "referenceSolutionCommentPrefix"], "Reference Solution Comment - ");
  }

  /**
   * Internal solution send comment method.
   * @param AssignmentSolution|ReferenceExerciseSolution $solution
   * @param Comment $comment
   * @return bool
   */
  private function sendSolutionComment($solution, Comment $comment): bool {
    if ($comment->isPrivate()) {
      // comment was private, therefore do not send email to others
      return true;
    }

    $baseSolution = $solution->getSolution();
    $subject = $this->assignmentSolutionCommentPrefix . $baseSolution->getAuthor()->getName();

    $recipients = [];
    $recipients[$baseSolution->getAuthor()->getEmail()] = $baseSolution->getAuthor();
    foreach ($comment->getThread()->findAllPublic() as $pComment) {
      $user = $pComment->getUser();
      $recipients[$user->getEmail()] = $user;
    }

    // filter out the author of the comment, it is pointless to send email to that user
    unset($recipients[$comment->getUser()->getEmail()]);

    // filter out users which do not have allowed sending these kind of emails
    $recipients = array_filter($recipients, function (User $user) {
      return $user->getSettings()->getSolutionCommentsEmails();
    });

    if (count($recipients) === 0) {
      return true;
    }

    if ($solution instanceof AssignmentSolution) {
      $body = $this->createAssignmentSolutionCommentBody($solution, $comment);
    } else {
      $body = $this->createReferenceSolutionCommentBody($solution, $comment);
    }

    // Send the mail
    return $this->emailHelper->send(
      $this->sender,
      [],
      $subject,
      $body,
      array_keys($recipients)
    );
  }

  /**
   * Comment was added to the assignment solution.
   * @param AssignmentSolution $solution
   * @param Comment $comment
   * @return boolean
   */
  public function assignmentSolutionComment(AssignmentSolution $solution, Comment $comment): bool {
    return $this->sendSolutionComment($solution, $comment);
  }

  /**
   * Prepare and format body of the assignment solution comment.
   * @param AssignmentSolution $solution
   * @param Comment $comment
   * @return string Formatted mail body to be sent
   */
  private function createAssignmentSolutionCommentBody(AssignmentSolution $solution, Comment $comment): string {
    // render the HTML to string using Latte engine
    $latte = new Latte\Engine();
    return $latte->renderToString(__DIR__ . "/assignmentSolutionComment.latte", [
      "assignment" => $this->localizationHelper->getLocalization($solution->getAssignment()->getLocalizedTexts())->getName(),
      "solutionAuthor" => $solution->getSolution()->getAuthor()->getName(),
      "author" => $comment->getUser()->getName(),
      "date" => $comment->getPostedAt(),
      "comment" => $comment->getText()
    ]);
  }

  /**
   * Comment was added to the reference solution.
   * @param ReferenceExerciseSolution $solution
   * @param Comment $comment
   * @return bool
   */
  public function referenceSolutionComment(ReferenceExerciseSolution $solution, Comment $comment): bool {
    return $this->sendSolutionComment($solution, $comment);
  }

  /**
   * Prepare and format body of the reference solution comment.
   * @param ReferenceExerciseSolution $solution
   * @param Comment $comment
   * @return string Formatted mail body to be sent
   */
  private function createReferenceSolutionCommentBody(ReferenceExerciseSolution $solution, Comment $comment): string {
    // render the HTML to string using Latte engine
    $latte = new Latte\Engine();
    return $latte->renderToString(__DIR__ . "/referenceSolutionComment.latte", [
      "exercise" => $this->localizationHelper->getLocalization($solution->getExercise()->getLocalizedTexts())->getName(),
      "solutionAuthor" => $solution->getSolution()->getAuthor()->getName(),
      "author" => $comment->getUser()->getName(),
      "date" => $comment->getPostedAt(),
      "comment" => $comment->getText()
    ]);
  }
}
