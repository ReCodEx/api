<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\EmailHelper;
use App\Helpers\WebappLinks;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Emails\EmailRenderResult;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\ShadowAssignmentPoints;
use App\Model\Entity\User;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\AssignmentSolvers;
use Nette\Utils\Arrays;

/**
 * Sending emails when a flag (like accepted or review-requested) of an assignment solution changes.
 */
class SolutionFlagChangedEmailSender
{
    /** @var EmailHelper */
    private $emailHelper;

    /** @var EmailLocalizationHelper */
    private $localizationHelper;

    /** @var WebappLinks */
    private $webappLinks;

    /** @var AssignmentSolutions */
    private $assignmentSolutions;

    /** @var AssignmentSolvers */
    private $assignmentSolvers;

    /** @var string */
    private $sender;

    /**
     * Constructor.
     * @param array $params
     * @param EmailHelper $emailHelper
     * @param EmailLocalizationHelper $localizationHelper
     * @param WebappLinks $webappLinks
     * @param AssignmentSolutions $assignmentSolutions
     * @param AssignmentSolvers $assignmentSolvers
     */
    public function __construct(
        array $params,
        EmailHelper $emailHelper,
        EmailLocalizationHelper $localizationHelper,
        WebappLinks $webappLinks,
        AssignmentSolutions $assignmentSolutions,
        AssignmentSolvers $assignmentSolvers
    ) {
        $this->emailHelper = $emailHelper;
        $this->localizationHelper = $localizationHelper;
        $this->webappLinks = $webappLinks;
        $this->assignmentSolutions = $assignmentSolutions;
        $this->assignmentSolvers = $assignmentSolvers;
        $this->sender = Arrays::get($params, ["emails", "from"], "noreply@recodex");
    }

    /**
     * Notify solution author that his/her solution was (un)marked as accepted.
     * @param User $currentUser (not used in this method, but we need to keep uniform API)
     * @param AssignmentSolution $solution of which the flag was changed
     * @param bool $newValue of the flag
     * @param AssignmentSolution|null $resetedSolution ref. to another solution that was marked as accepted
     *                                                 and now unmarked due to uniqueness of the accepted flag
     * @return boolean
     * @throws InvalidStateException
     */
    public function acceptedFlagChanged(
        User $currentUser,
        AssignmentSolution $solution,
        bool $newValue,
        ?AssignmentSolution $resetedSolution = null
    ): bool {
        if (
            $solution->getSolution()->getAuthor() === null ||
            $solution->getAssignment() === null ||
            $solution->getAssignment()->getGroup() === null
        ) {
            // group, assignment or user was deleted, do not send emails
            return false;
        }

        $author = $solution->getSolution()->getAuthor();
        if (!$author->isVerified() || !$author->getSettings()->getSolutionAcceptedEmails()) {
            return true;
        }

        $locale = $author->getSettings()->getDefaultLanguage();
        $result = $this->createSolutionAcceptedUpdated($solution, $newValue, $resetedSolution, $locale);

        return $this->emailHelper->setShowSettingsInfo()->send(
            $this->sender,
            [$author->getEmail()],
            $locale,
            $result->getSubject(),
            $result->getText()
        );
    }

    /**
     * Prepare and format body of the accepted flag change mail.
     * @param AssignmentSolution $solution of which the flag was changed
     * @param bool $newValue of the flag
     * @param AssignmentSolution|null $resetedSolution ref. to another solution that was marked as accepted
     *                                                 and now unmarked due to uniqueness of the accepted flag
     * @param string $locale
     * @return EmailRenderResult
     * @throws InvalidStateException
     */
    private function createSolutionAcceptedUpdated(
        AssignmentSolution $solution,
        bool $newValue,
        ?AssignmentSolution $resetedSolution,
        string $locale
    ): EmailRenderResult {
        $assignment = $solution->getAssignment();
        $author = $solution->getSolution()->getAuthor();

        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate($locale, __DIR__
            . "/solutionFlagChangedAccepted_{locale}.latte");

        if ($newValue) {
            $best = $solution; // since it was just accepted
        } else {
            $best = $this->assignmentSolutions->findBestSolution($assignment, $author);
        }

        $points = (string)$best->getPoints();
        if ($best->getBonusPoints()) {
            $points .= ($best->getBonusPoints() > 0 ? '+' : '') . $best->getBonusPoints();
        }

        $solvers = $this->assignmentSolvers->findInAssignment($assignment, $author);
        $solver = reset($solvers);

        return $latte->renderEmail(
            $template,
            [
                "accepted" => $newValue,
                "attempt" => $solution->getAttemptIndex(),
                "attempts" => $solver->getLastAttemptIndex(),
                "submittedAt" => $solution->getSolution()->getCreatedAt(),
                "prevAttempt" => $resetedSolution?->getAttemptIndex(),
                "prevSubmittedAt" => $resetedSolution?->getSolution()->getCreatedAt(),
                "assignment" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $assignment->getLocalizedTexts()
                )->getName(),
                "group" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $assignment->getGroup()->getLocalizedTexts()
                )->getName(),
                "points" => $points,
                "maxPoints" => $solution->getMaxPoints(),
                "link" => $this->webappLinks->getSolutionPageUrl($assignment->getId(), $solution->getId())
            ]
        );
    }

    /**
     * Notify teacher's that a review request state of a solution was changed.
     * @param User $currentUser (who will be excluded from the recipients)
     * @param AssignmentSolution $solution of which the flag was changed
     * @param bool $newValue of the flag
     * @param AssignmentSolution|null $resetedSolution ref. to another solution that was requested for review
     *                                                 and now unmarked due to uniqueness of the request flag
     * @return boolean
     * @throws InvalidStateException
     */
    public function reviewRequestFlagChanged(
        User $currentUser,
        AssignmentSolution $solution,
        bool $newValue,
        ?AssignmentSolution $resetedSolution = null
    ): bool {
        if (
            $solution->getSolution()->getAuthor() === null ||
            $solution->getAssignment() === null ||
            $solution->getAssignment()->getGroup() === null
        ) {
            // group, assignment or user was deleted, do not send emails
            return false;
        }
        $group = $solution->getAssignment()->getGroup();

        // get all the admins and supervisors
        $recipients = [];
        foreach ($group->getSupervisors() as $supervisor) {
            if ($supervisor->isVerified() && $supervisor->getSettings()->getSolutionReviewRequestedEmails()) {
                $recipients[$supervisor->getId()] = $supervisor;
            }
        }
        foreach ($group->getPrimaryAdmins() as $admin) {
            if ($admin->isVerified() && $admin->getSettings()->getSolutionReviewRequestedEmails()) {
                $recipients[$admin->getId()] = $admin;
            }
        }
        if (array_key_exists($currentUser->getId(), $recipients)) {
            // exclude the user, who set the flag (if it is a teacher)
            unset($recipients[$currentUser->getId()]);
        }

        if (!$recipients) {
            return true;
        }

        return $this->localizationHelper->sendLocalizedEmail(
            array_values($recipients),
            function ($toUsers, $emails, $locale) use ($solution, $newValue, $resetedSolution) {
                $result = $this->createSolutionReviewRequestUpdated($solution, $newValue, $resetedSolution, $locale);

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
     * Prepare and format body of the review-request flag change mail.
     * @param AssignmentSolution $solution of which the flag was changed
     * @param bool $newValue of the flag
     * @param AssignmentSolution|null $resetedSolution ref. to another solution that was requested for review
     *                                                 and now unmarked due to uniqueness of the request flag
     * @param string $locale
     * @return EmailRenderResult
     * @throws InvalidStateException
     */
    private function createSolutionReviewRequestUpdated(
        AssignmentSolution $solution,
        bool $newValue,
        ?AssignmentSolution $resetedSolution,
        string $locale
    ): EmailRenderResult {
        $assignment = $solution->getAssignment();
        $author = $solution->getSolution()->getAuthor();

        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate($locale, __DIR__
            . "/solutionFlagChangedReviewRequest_{locale}.latte");

        if ($newValue) {
            $best = $solution; // since it was just accepted
        } else {
            $best = $this->assignmentSolutions->findBestSolution($assignment, $author);
        }

        $points = (string)$best->getPoints();
        if ($best->getBonusPoints()) {
            $points .= ($best->getBonusPoints() > 0 ? '+' : '') . $best->getBonusPoints();
        }

        $solvers = $this->assignmentSolvers->findInAssignment($assignment, $author);
        $solver = reset($solvers);

        return $latte->renderEmail(
            $template,
            [
                "requested" => $newValue,
                "attempt" => $solution->getAttemptIndex(),
                "attempts" => $solver->getLastAttemptIndex(),
                "submittedAt" => $solution->getSolution()->getCreatedAt(),
                "author" => $author->getName(),
                "prevAttempt" => $resetedSolution?->getAttemptIndex(),
                "prevSubmittedAt" => $resetedSolution?->getSolution()->getCreatedAt(),
                "assignment" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $assignment->getLocalizedTexts()
                )->getName(),
                "group" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $assignment->getGroup()->getLocalizedTexts()
                )->getName(),
                "link" => $this->webappLinks->getSolutionPageUrl($assignment->getId(), $solution->getId())
            ]
        );
    }
}
