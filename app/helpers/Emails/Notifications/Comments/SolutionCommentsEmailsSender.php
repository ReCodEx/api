<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\EmailHelper;
use App\Helpers\WebappLinks;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Emails\EmailRenderResult;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Comment;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\User;
use Nette\Utils\Arrays;

/**
 * Sending emails on solution commentary.
 */
class SolutionCommentsEmailsSender
{
    /** @var EmailHelper */
    private $emailHelper;

    /** @var EmailLocalizationHelper */
    private $localizationHelper;

    /** @var WebappLinks */
    private $webappLinks;

    /** @var string */
    private $sender;

    /**
     * Constructor.
     * @param array $notificationsConfig
     * @param EmailHelper $emailHelper
     * @param EmailLocalizationHelper $localizationHelper
     * @param WebappLinks $webappLinks
     */
    public function __construct(
        array $notificationsConfig,
        EmailHelper $emailHelper,
        EmailLocalizationHelper $localizationHelper,
        WebappLinks $webappLinks
    ) {
        $this->emailHelper = $emailHelper;
        $this->localizationHelper = $localizationHelper;
        $this->webappLinks = $webappLinks;
        $this->sender = Arrays::get($notificationsConfig, ["emails", "from"], "noreply@recodex");
    }

    /**
     * Internal solution send comment method.
     * @param AssignmentSolution|ReferenceExerciseSolution $solution
     * @param Comment $comment
     * @return bool
     * @throws InvalidStateException
     */
    private function sendSolutionComment($solution, Comment $comment): bool
    {
        if ($comment->isPrivate()) {
            // comment was private, therefore do not send email to others
            return true;
        }

        $baseSolution = $solution->getSolution();
        $recipients = [];
        $authorId = $comment->getUser()->getId();
        if ($baseSolution->getAuthor() !== null && $baseSolution->getAuthor()->getId() !== $authorId) {
            $recipients[$baseSolution->getAuthor()->getEmail()] = $baseSolution->getAuthor();
        }

        foreach ($comment->getThread()->findAllPublic() as $pComment) {
            $user = $pComment->getUser();
            // filter out the author of the comment, it is pointless to send email to that user
            if (
                $user !== null && $user->isVerified() && $user->getId() !== $authorId
                && $user->getSettings()->getSolutionCommentsEmails()
            ) {
                $recipients[$user->getEmail()] = $user;
            }
        }

        if (count($recipients) === 0) {
            return true;
        }

        return $this->localizationHelper->sendLocalizedEmail(
            $recipients,
            function ($toUsers, $emails, $locale) use ($solution, $comment) {
                if ($solution instanceof AssignmentSolution) {
                    $result = $this->createAssignmentSolutionComment($solution, $comment, $locale);
                } else {
                    $result = $this->createReferenceSolutionComment($solution, $comment, $locale);
                }

                // Send the mail
                return $this->emailHelper->setShowSettingsInfo()->send(
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

    /**
     * Comment was added to the assignment solution.
     * @param AssignmentSolution $solution
     * @param Comment $comment
     * @return boolean
     * @throws InvalidStateException
     */
    public function assignmentSolutionComment(AssignmentSolution $solution, Comment $comment): bool
    {
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
     * @return EmailRenderResult
     * @throws InvalidStateException
     */
    private function createAssignmentSolutionComment(
        AssignmentSolution $solution,
        Comment $comment,
        string $locale
    ): EmailRenderResult {
        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate(
            $locale,
            __DIR__ . "/assignmentSolutionComment_{locale}.latte"
        );
        return $latte->renderEmail(
            $template,
            [
                "assignment" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $solution->getAssignment()->getLocalizedTexts()
                )->getName(),
                "solutionAuthor" => $solution->getSolution()->getAuthor()
                    ? $solution->getSolution()->getAuthor()->getName() : "",
                "author" => $comment->getUser() ? $comment->getUser()->getName() : "",
                "date" => $comment->getPostedAt(),
                "comment" => $comment->getText(),
                "link" => $this->webappLinks->getSolutionPageUrl(
                    $solution->getAssignment()->getId(),
                    $solution->getId()
                )
            ]
        );
    }

    /**
     * Comment was added to the reference solution.
     * @param ReferenceExerciseSolution $solution
     * @param Comment $comment
     * @return bool
     * @throws InvalidStateException
     */
    public function referenceSolutionComment(ReferenceExerciseSolution $solution, Comment $comment): bool
    {
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
     * @return EmailRenderResult
     * @throws InvalidStateException
     */
    private function createReferenceSolutionComment(
        ReferenceExerciseSolution $solution,
        Comment $comment,
        string $locale
    ): EmailRenderResult {
        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/referenceSolutionComment_{locale}.latte");
        return $latte->renderEmail(
            $template,
            [
                "exercise" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $solution->getExercise()->getLocalizedTexts()
                )->getName(),
                "solutionAuthor" => $solution->getSolution()->getAuthor()
                    ? $solution->getSolution()->getAuthor()->getName() : "",
                "author" => $comment->getUser() ? $comment->getUser()->getName() : "",
                "date" => $comment->getPostedAt(),
                "comment" => $comment->getText(),
                "link" => $this->webappLinks->getReferenceSolutionPageUrl(
                    $solution->getExercise()->getId(),
                    $solution->getId()
                )
            ]
        );
    }
}
