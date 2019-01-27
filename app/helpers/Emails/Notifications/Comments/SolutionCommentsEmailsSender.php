<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\EmailHelper;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLinkHelper;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Comment;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\User;
use Nette\Utils\Arrays;

/**
 * Sending emails on solution commentary.
 */
class SolutionCommentsEmailsSender {

  /** @var EmailHelper */
  private $emailHelper;
  /** @var EmailLocalizationHelper */
  private $localizationHelper;

  /** @var string */
  private $sender;
  /** @var string */
  private $assignmentSolutionCommentPrefix;
  /** @var string */
  private $referenceSolutionCommentPrefix;
  /** @var string */
  private $assignmentSolutionRedirectUrl;
  /** @var string */
  private $referenceSolutionRedirectUrl;

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
    $this->assignmentSolutionRedirectUrl = Arrays::get($params, ["assignmentSolutionRedirectUrl"], "https://recodex.mff.cuni.cz");
    $this->referenceSolutionRedirectUrl = Arrays::get($params, ["referenceSolutionRedirectUrl"], "https://recodex.mff.cuni.cz");
  }

  /**
   * Internal solution send comment method.
   * @param AssignmentSolution|ReferenceExerciseSolution $solution
   * @param Comment $comment
   * @return bool
   * @throws InvalidStateException
   */
  private function sendSolutionComment($solution, Comment $comment): bool {
    if ($comment->isPrivate()) {
      // comment was private, therefore do not send email to others
      return true;
    }

    $baseSolution = $solution->getSolution();
    $recipients = [];
    if ($baseSolution->getAuthor() !== null) {
      $recipients[$baseSolution->getAuthor()->getEmail()] = $baseSolution->getAuthor();
    }

    foreach ($comment->getThread()->findAllPublic() as $pComment) {
      $user = $pComment->getUser();
      if ($user !== null) {
        $recipients[$user->getEmail()] = $user;
      }
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

    return $this->localizationHelper->sendLocalizedEmail(
      $recipients,
      function ($toUsers, $emails, $locale) use ($solution, $baseSolution, $comment) {
        if ($solution instanceof AssignmentSolution) {
          $subject = $this->assignmentSolutionCommentPrefix . $baseSolution->getAuthor()->getName();
          $body = $this->createAssignmentSolutionCommentBody($solution, $comment, $locale);
        } else {
          $subject = $this->referenceSolutionCommentPrefix . $baseSolution->getAuthor()->getName();
          $body = $this->createReferenceSolutionCommentBody($solution, $comment, $locale);
        }

        // Send the mail
        return $this->emailHelper->send(
          $this->sender,
          [],
          $locale,
          $subject,
          $body,
          $emails
        );
      }
    );
  }

  /**
   * Comment was added to the assignment solution.
   * @param AssignmentSolution $solution
   * @param Comment $comment
   * @return boolean
   * @throws InvalidStateException
   */
  public function assignmentSolutionComment(AssignmentSolution $solution, Comment $comment): bool {
    if ($solution->getAssignment() === null) {
      // assignment was deleted, do not send emails
      return false;
    }

    return $this->sendSolutionComment($solution, $comment);
  }

  /**
   * Prepare and format body of the assignment solution comment.
   * @param AssignmentSolution $solution
   * @param Comment $comment
   * @param string $locale
   * @return string Formatted mail body to be sent
   * @throws InvalidStateException
   */
  private function createAssignmentSolutionCommentBody(AssignmentSolution $solution, Comment $comment, string $locale): string {
    // render the HTML to string using Latte engine
    $latte = EmailLatteFactory::latte();
    $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/assignmentSolutionComment_{locale}.latte");
    return $latte->renderToString($template, [
      "assignment" => EmailLocalizationHelper::getLocalization($locale, $solution->getAssignment()->getLocalizedTexts())->getName(),
      "solutionAuthor" => $solution->getSolution()->getAuthor() ? $solution->getSolution()->getAuthor()->getName() : "",
      "author" => $comment->getUser() ? $comment->getUser()->getName() : "",
      "date" => $comment->getPostedAt(),
      "comment" => $comment->getText(),
      "link" => EmailLinkHelper::getLink($this->assignmentSolutionRedirectUrl, [
        "assignmentId" => $solution->getAssignment()->getId(),
        "solutionId" => $solution->getId()
      ])
    ]);
  }

  /**
   * Comment was added to the reference solution.
   * @param ReferenceExerciseSolution $solution
   * @param Comment $comment
   * @return bool
   * @throws InvalidStateException
   */
  public function referenceSolutionComment(ReferenceExerciseSolution $solution, Comment $comment): bool {
    if ($solution->getExercise() === null) {
      // exercise was deleted, do not send emails
      return false;
    }

    return $this->sendSolutionComment($solution, $comment);
  }

  /**
   * Prepare and format body of the reference solution comment.
   * @param ReferenceExerciseSolution $solution
   * @param Comment $comment
   * @param string $locale
   * @return string Formatted mail body to be sent
   * @throws InvalidStateException
   */
  private function createReferenceSolutionCommentBody(ReferenceExerciseSolution $solution, Comment $comment, string $locale): string {
    // render the HTML to string using Latte engine
    $latte = EmailLatteFactory::latte();
    $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/referenceSolutionComment_{locale}.latte");
    return $latte->renderToString($template, [
      "exercise" => EmailLocalizationHelper::getLocalization($locale, $solution->getExercise()->getLocalizedTexts())->getName(),
      "solutionAuthor" => $solution->getSolution()->getAuthor() ? $solution->getSolution()->getAuthor()->getName() : "",
      "author" => $comment->getUser() ? $comment->getUser()->getName() : "",
      "date" => $comment->getPostedAt(),
      "comment" => $comment->getText(),
      "link" => EmailLinkHelper::getLink($this->referenceSolutionRedirectUrl, [
        "exerciseId" => $solution->getExercise()->getId(),
        "solutionId" => $solution->getId()
      ])
    ]);
  }
}
