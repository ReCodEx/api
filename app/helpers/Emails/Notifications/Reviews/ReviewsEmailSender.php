<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\EmailHelper;
use App\Helpers\WebappLinks;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Emails\EmailRenderResult;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\ReviewComment;
use App\Model\Entity\User;
use Nette\Utils\Arrays;
use DateTime;

/**
 * Sending email notifications related to solution code reviews.
 */
class ReviewsEmailsSender
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
     * Perform solution checks and return the author (recipient of the email).
     * @param AssignmentSolution $solution
     * @return User|null (null is returned if verifications fail or the user does not wish the notifications)
     */
    private function getAuthor(AssignmentSolution $solution): ?User
    {
        if (!$solution->getAssignment() || !$solution->getAssignment()->getGroup()) {
            return null; // safety check, avoid reporting on deleted stuff
        }

        $author = $solution->getSolution()->getAuthor();
        if (!$author || !$author->isVerified() || !$author->getSettings()->getSolutionReviewsEmails()) {
            return null;
        }

        return $author;
    }

    /**
     * Helper that assembles common email template arguments.
     * @param string $locale
     * @param AssignmentSolution $solution,
     * @param DateTime|null $closed if not null, overrides value of solution->reviewedAt
     * @return array containing email template arguments
     */
    private function prepareSolutionEmailParams(
        string $locale,
        AssignmentSolution $solution,
        ?DateTime $closed = null
    ): array {
        $assignment = $solution->getAssignment();
        $group = $assignment->getGroup();
        return [
            "assignment" => EmailLocalizationHelper::getLocalization(
                $locale,
                $assignment->getLocalizedTexts()
            )->getName(),
            "group" => EmailLocalizationHelper::getLocalization(
                $locale,
                $group->getLocalizedTexts()
            )->getName(),
            "attempt" => $solution->getAttemptIndex(),
            "submitted" => $solution->getSolution()->getCreatedAt(),
            "closed" => $closed ? $closed : $solution->getReviewedAt(),
            "codeUrl" => $this->webappLinks->getSolutionSourceFilesUrl($assignment->getId(), $solution->getId()),
            "detailUrl" => $this->webappLinks->getSolutionPageUrl($assignment->getId(), $solution->getId()),
        ];
    }

    /**
     * Prepare and format body of a review-related notification.
     * @param AssignmentSolution $solution
     * @param DateTime|null $closed
     * @param string $notificationType
     * @param string $locale
     * @return EmailRenderResult
     */
    private function createReviewNotification(
        AssignmentSolution $solution,
        ?DateTime $closed,
        string $notificationType,
        string $locale
    ): EmailRenderResult {
        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate(
            $locale,
            __DIR__ . "/review{$notificationType}_{locale}.latte"
        );

        $params = $this->prepareSolutionEmailParams($locale, $solution, $closed);

        if ($notificationType === "Closed") {
            // sort out and include the comments
            $comments = $solution->getReviewComments()->toArray();
            usort($comments, function ($a, $b) {
                $res = strcmp($a->getFile(), $b->getFile());
                return $res === 0 ? ($a->getLine() - $b->getLine()) : $res;
            });
            $params["summary"] = array_filter($comments, function ($c) {
                return !$c->getFile();
            });
            $params["issues"] = array_filter($comments, function ($c) {
                return $c->getFile() && $c->isIssue();
            });
            $params["comments"] = array_filter($comments, function ($c) {
                return $c->getFile() && !$c->isIssue();
            });
        }

        return $latte->renderEmail($template, $params);
    }

    /**
     * Notify the author of the solution that the code has been reviewed.
     * @param AssignmentSolution $solution for which a review was just closed
     * @return bool false in case the email could not have been sent
     */
    public function solutionReviewClosed(AssignmentSolution $solution): bool
    {
        $author = $this->getAuthor($solution);
        if (!$author) {
            return true;
        }

        return $this->localizationHelper->sendLocalizedEmail(
            [ $author ],
            function ($toUsers, $emails, $locale) use ($solution) {
                $result = $this->createReviewNotification($solution, null, "Closed", $locale);
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
     * Notify the author of the solution that the code review has been re-opened.
     * @param AssignmentSolution $solution
     * @param DateTime $closed when the solution review was previously closed
     * @return bool false in case the email could not have been sent
     */
    public function solutionReviewReopened(AssignmentSolution $solution, DateTime $closed): bool
    {
        $author = $this->getAuthor($solution);
        if (!$author) {
            return true;
        }

        return $this->localizationHelper->sendLocalizedEmail(
            [ $author ],
            function ($toUsers, $emails, $locale) use ($solution, $closed) {
                $result = $this->createReviewNotification($solution, $closed, "Reopened", $locale);
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
     * Notify the author of the solution that the code review has been removed.
     * @param AssignmentSolution $solution
     * @param DateTime $closed when the solution review was previously closed
     * @return bool false in case the email could not have been sent
     */
    public function solutionReviewRemoved(AssignmentSolution $solution, DateTime $closed): bool
    {
        $author = $this->getAuthor($solution);
        if (!$author) {
            return true;
        }

        return $this->localizationHelper->sendLocalizedEmail(
            [ $author ],
            function ($toUsers, $emails, $locale) use ($solution, $closed) {
                $result = $this->createReviewNotification($solution, $closed, "Removed", $locale);
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
     * Prepare and format body of a comment-related notification.
     * @param AssignmentSolution $solution
     * @param ReviewComment $comment
     * @param string $notificationType
     * @param string $locale
     * @return EmailRenderResult
     */
    private function createCommentNotification(
        AssignmentSolution $solution,
        ReviewComment $comment,
        string $notificationType,
        string $locale
    ): EmailRenderResult {
        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate(
            $locale,
            __DIR__ . "/comment{$notificationType}_{locale}.latte"
        );

        $params = $this->prepareSolutionEmailParams($locale, $solution);
        $params["comment"] = $comment;
        return $latte->renderEmail($template, $params);
    }

    /**
     * Notify the author of the solution that a new comment was appended to already closed review.
     * @param AssignmentSolution $solution
     * @param ReviewComment $comment that was added
     * @return bool false in case the email could not have been sent
     */
    public function newReviewComment(AssignmentSolution $solution, ReviewComment $comment): bool
    {
        $author = $this->getAuthor($solution);
        if (!$author) {
            return true;
        }

        return $this->localizationHelper->sendLocalizedEmail(
            [ $author ],
            function ($toUsers, $emails, $locale) use ($solution, $comment) {
                $result = $this->createCommentNotification($solution, $comment, "New", $locale);
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
     * Notify the author of the solution that a comment was removed from an already closed review.
     * @param AssignmentSolution $solution
     * @param ReviewComment $comment that was added
     * @return bool false in case the email could not have been sent
     */
    public function removedReviewComment(AssignmentSolution $solution, ReviewComment $comment): bool
    {
        $author = $this->getAuthor($solution);
        if (!$author) {
            return true;
        }

        return $this->localizationHelper->sendLocalizedEmail(
            [ $author ],
            function ($toUsers, $emails, $locale) use ($solution, $comment) {
                $result = $this->createCommentNotification($solution, $comment, "Removed", $locale);
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
     * Prepare and format body of a comment-updated notification.
     * @param AssignmentSolution $solution
     * @param ReviewComment $comment
     * @param string $oldText before modification
     * @param bool $issueChanged in the modification
     * @param string $locale
     * @return EmailRenderResult
     */
    private function createCommentUpdateNotification(
        AssignmentSolution $solution,
        ReviewComment $comment,
        string $oldText,
        bool $issueChanged,
        string $locale
    ): EmailRenderResult {
        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate(
            $locale,
            __DIR__ . "/commentUpdated_{locale}.latte"
        );

        $params = $this->prepareSolutionEmailParams($locale, $solution);
        $params["comment"] = $comment;
        $params["oldText"] = $oldText;
        $params["issueChanged"] = $issueChanged;
        return $latte->renderEmail($template, $params);
    }

    /**
     * Notify the author of the solution that a comment in a closed review was editted.
     * @param AssignmentSolution $solution
     * @param ReviewComment $comment that was added
     * @param string $oldText before modification
     * @param bool $issueChanged in the modification
     * @return bool false in case the email could not have been sent
     */
    public function changedReviewComment(
        AssignmentSolution $solution,
        ReviewComment $comment,
        string $oldText,
        bool $issueChanged
    ): bool {
        $author = $this->getAuthor($solution);
        if (!$author) {
            return true;
        }

        return $this->localizationHelper->sendLocalizedEmail(
            [ $author ],
            function ($toUsers, $emails, $locale) use ($solution, $comment, $oldText, $issueChanged) {
                $result = $this->createCommentUpdateNotification($solution, $comment, $oldText, $issueChanged, $locale);
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
     * Prepare and format body of a comment-updated notification.
     * @param AssignmentSolution[] $solutions with open reviews
     * @param string $locale
     * @return EmailRenderResult
     */
    private function createPendingReviewsNotification(
        array $solutions,
        string $locale
    ): EmailRenderResult {
        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate(
            $locale,
            __DIR__ . "/pendingReviews_{locale}.latte"
        );

        $params = [
            "solutions" => array_map(function ($solution) use ($locale) {
                return (object)[
                    "assignment" => EmailLocalizationHelper::getLocalization(
                        $locale,
                        $solution->getAssignment()->getLocalizedTexts()
                    )->getName(),
                    "group" => EmailLocalizationHelper::getLocalization(
                        $locale,
                        $solution->getAssignment()->getGroup()->getLocalizedTexts()
                    )->getName(),
                    "attempt" => $solution->getAttemptIndex(),
                    "submitted" => $solution->getSolution()->getCreatedAt(),
                    "solutionUrl" => $this->webappLinks
                        ->getSolutionSourceFilesUrl($solution->getAssignment()->getId(), $solution->getId()),
                    "assignmentUrl" => $this->webappLinks->getAssignmentPageUrl($solution->getAssignment()->getId()),
                ];
            }, $solutions),
        ];
        return $latte->renderEmail($template, $params);
    }

    /**
     * Notify given user that there are solutions with not-yet-closed reviews in some of the groups
     * under hir administration.
     * @param User $recipient
     * @param AssignmentSolution[] $solutions with open reviews
     * @return bool false in case the email could not have been sent
     */
    public function notifyPendingReviews(User $recipient, array $solutions): bool
    {
        return $this->localizationHelper->sendLocalizedEmail(
            [ $recipient ],
            function ($toUsers, $emails, $locale) use ($solutions) {
                $result = $this->createPendingReviewsNotification($solutions, $locale);
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
}
