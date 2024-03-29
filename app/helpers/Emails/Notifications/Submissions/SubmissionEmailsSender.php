<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Emails\EmailRenderResult;
use App\Helpers\EmailHelper;
use App\Helpers\WebappLinks;
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

    /** @var WebappLinks */
    private $webappLinks;

    /** @var string */
    private $sender;

    /** @var string */
    private $submissionNotificationThreshold;


    /**
     * Constructor.
     * @param array $notificationsConfig
     * @param EmailHelper $emailHelper
     * @param EmailLocalizationHelper $localizationHelper
     * @param AssignmentSolutions $assignmentSolutions
     * @param WebappLinks $webappLinks
     */
    public function __construct(
        array $notificationsConfig,
        EmailHelper $emailHelper,
        EmailLocalizationHelper $localizationHelper,
        AssignmentSolutions $assignmentSolutions,
        WebappLinks $webappLinks,
    ) {
        $this->emailHelper = $emailHelper;
        $this->localizationHelper = $localizationHelper;
        $this->assignmentSolutions = $assignmentSolutions;
        $this->webappLinks = $webappLinks;
        $this->sender = Arrays::get($notificationsConfig, ["emails", "from"], "noreply@recodex");
        $this->submissionNotificationThreshold = Arrays::get(
            $notificationsConfig,
            ["submissionNotificationThreshold"],
            "-5 minutes"
        );
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

        if (!$solution->getLastSubmission() || $solution->getLastSubmission()->getId() !== $submission->getId()) {
            // another submission (reevaluation) of the same solution has already been made
            return false;
        }

        $isResubmit = count($solution->getSubmissions()) > 1;

        // Handle evaluation notifications...
        $resultSubmissionEvaluated = $this->handleSubmissionEvaluated($solution, $submission, $user, $isResubmit);

        if ($isResubmit) {
            // no other notifications in case of resubmits
            return $resultSubmissionEvaluated;
        } else {
            // review-related notifications are actually bound with solution
            // (i.e., relevant only when it is evaluated for the first time)
            $resultSubmissionAfterAcceptance = $this->handleSubmissionAfterAcceptance($solution, $submission, $user);
            $resultSubmissionAfterReview = $this->handleSubmissionAfterReview($solution, $submission, $user);
            return $resultSubmissionEvaluated && $resultSubmissionAfterAcceptance && $resultSubmissionAfterReview;
        }
    }

    /**
     * @throws InvalidStateException
     */
    private function handleSubmissionEvaluated(
        AssignmentSolution $solution,
        AssignmentSolutionSubmission $submission,
        User $user,
        bool $isResubmit
    ): bool {
        $threshold = (new DateTime())->modify($this->submissionNotificationThreshold);
        $createdAt = $solution->getSolution()->getCreatedAt();
        if (
            $user->isVerified() &&
            $user->getSettings()->getSubmissionEvaluatedEmails() &&
            ($createdAt < $threshold || $isResubmit)
        ) {
            $locale = $user->getSettings()->getDefaultLanguage();
            $result = $this->createSubmissionEvaluated(
                $solution->getAssignment(),
                $submission,
                $isResubmit,
                $locale
            );

            // Send the mail
            return $this->emailHelper->setShowSettingsInfo()->send(
                $this->sender,
                [$user->getEmail()],
                $locale,
                $result->getSubject(),
                $result->getText()
            );
        }

        return true;
    }

    private function handleSubmissionAfterAcceptance(
        AssignmentSolution $solution,
        AssignmentSolutionSubmission $submission,
        User $user
    ): bool {
        // Already approved solution notification
        $best = $this->assignmentSolutions->findBestSolution($solution->getAssignment(), $user);
        $recipients = $this->getGroupSupervisorsRecipients(
            $solution->getAssignment()->getGroup(),
            "assignmentSubmitAfterAcceptedEmails"
        );
        if ($best->isAccepted() && count($recipients) > 0) {
            return $this->localizationHelper->sendLocalizedEmail(
                $recipients,
                function ($toUsers, $emails, $locale) use ($solution, $submission) {
                    $result = $this->createNewSubmissionAfterAcceptance(
                        $solution->getAssignment(),
                        $solution,
                        $submission,
                        $locale
                    );

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

        return true;
    }

    private function handleSubmissionAfterReview(
        AssignmentSolution $solution,
        AssignmentSolutionSubmission $submission,
        User $user
    ): bool {
        // Already reviewed solutions notification
        $recipients = $this->getGroupSupervisorsRecipients(
            $solution->getAssignment()->getGroup(),
            "assignmentSubmitAfterReviewedEmails"
        );
        if ($this->shouldSendAfterReview($solution, $user) && count($recipients) > 0) {
            return $this->localizationHelper->sendLocalizedEmail(
                $recipients,
                function ($toUsers, $emails, $locale) use ($solution, $submission) {
                    $result = $this->createNewSubmissionAfterReview(
                        $solution->getAssignment(),
                        $solution,
                        $submission,
                        $locale
                    );

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

        return true;
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
            if ($supervisor->isVerified() && $supervisor->getSettings()->getFlag($flag)) {
                $recipients[$supervisor->getId()] = $supervisor;
            }
        }

        foreach ($group->getPrimaryAdmins() as $admin) {
            if ($admin->isVerified() && $admin->getSettings()->getFlag($flag)) {
                $recipients[$admin->getId()] = $admin;
            }
        }

        return array_values($recipients);
    }

    private function shouldSendAfterReview(AssignmentSolution $solution, User $user): bool
    {
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
     * @param bool $isResubmit
     * @param string $locale
     * @return EmailRenderResult
     * @throws InvalidStateException
     */
    private function createSubmissionEvaluated(
        Assignment $assignment,
        AssignmentSolutionSubmission $submission,
        bool $isResubmit,
        string $locale
    ): EmailRenderResult {
        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/submissionEvaluated_{locale}.latte");
        $submittedBy = $submission->getSubmittedBy();
        return $latte->renderEmail(
            $template,
            [
                "assignment" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $assignment->getLocalizedTexts()
                )->getName(),
                "group" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $assignment->getGroup()->getLocalizedTexts()
                )->getName(),
                "date" => $submission->getEvaluation()->getEvaluatedAt(),
                "status" => $submission->isCorrect() === true ? "success" : "failure",
                "points" => $submission->getEvaluation()->getPoints(),
                "maxPoints" => $submission->getAssignmentSolution()->getMaxPoints(),
                "link" => $this->webappLinks->getSolutionPageUrl(
                    $assignment->getId(),
                    $submission->getAssignmentSolution()->getId()
                ),
                "isResubmit" => $isResubmit,
                "submittedBy" => $submittedBy ? $submittedBy->getName() : "",
            ]
        );
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
    private function createNewSubmissionAfterAcceptance(
        Assignment $assignment,
        AssignmentSolution $solution,
        AssignmentSolutionSubmission $submission,
        string $locale
    ): EmailRenderResult {
        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $user = $solution->getSolution()->getAuthor();
        $template = EmailLocalizationHelper::getTemplate(
            $locale,
            __DIR__ . "/newSubmissionAfterAcceptance_{locale}.latte"
        );
        return $latte->renderEmail(
            $template,
            [
                "assignment" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $assignment->getLocalizedTexts()
                )->getName(),
                "user" => $user ? $user->getName() : "",
                "score" => (int)($submission->getEvaluation()->getScore() * 100),
                "link" => $this->webappLinks->getSolutionPageUrl(
                    $assignment->getId(),
                    $solution->getId()
                ),
            ]
        );
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
    private function createNewSubmissionAfterReview(
        Assignment $assignment,
        AssignmentSolution $solution,
        AssignmentSolutionSubmission $submission,
        string $locale
    ): EmailRenderResult {
        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $user = $solution->getSolution()->getAuthor();
        $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/newSubmissionAfterReview_{locale}.latte");
        return $latte->renderEmail(
            $template,
            [
                "assignment" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $assignment->getLocalizedTexts()
                )->getName(),
                "user" => $user ? $user->getName() : "",
                "score" => (int)($submission->getEvaluation()->getScore() * 100),
                "link" => $this->webappLinks->getSolutionPageUrl(
                    $assignment->getId(),
                    $solution->getId(),
                ),
            ]
        );
    }
}
