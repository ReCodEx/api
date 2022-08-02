<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\EmailHelper;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Emails\EmailRenderResult;
use App\Helpers\WebappLinks;
use App\Model\Entity\Assignment;
use App\Model\Entity\AssignmentBase;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\ShadowAssignment;
use App\Model\Entity\User;
use App\Model\Repository\AssignmentSolutions;
use Nette\Utils\Arrays;

/**
 * Sending emails on assignment creation.
 */
class AssignmentEmailsSender
{
    /** @var EmailHelper */
    private $emailHelper;

    /** @var AssignmentSolutions */
    private $assignmentSolutions;

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
     * @param AssignmentSolutions $assignmentSolutions
     * @param EmailLocalizationHelper $localizationHelper
     * @param WebappLinks $webappLinks
     */
    public function __construct(
        array $notificationsConfig,
        EmailHelper $emailHelper,
        AssignmentSolutions $assignmentSolutions,
        EmailLocalizationHelper $localizationHelper,
        WebappLinks $webappLinks,
    ) {
        $this->emailHelper = $emailHelper;
        $this->assignmentSolutions = $assignmentSolutions;
        $this->localizationHelper = $localizationHelper;
        $this->webappLinks = $webappLinks;
        $this->sender = Arrays::get($notificationsConfig, ["emails", "from"], "noreply@recodex");
    }

    /**
     * Assignment was created, send emails to users who wanted to know about this.
     * @param AssignmentBase $assignment
     * @return boolean
     */
    public function assignmentCreated(AssignmentBase $assignment): bool
    {
        if ($assignment->getGroup() === null) {
            // group was deleted, do not send emails
            return false;
        }

        $recipients = [];
        foreach ($assignment->getGroup()->getStudents() as $student) {
            if (!$student->isVerified() || !$student->getSettings()->getNewAssignmentEmails()) {
                continue;
            }
            $recipients[] = $student;
        }

        if (count($recipients) === 0) {
            return true;
        }

        return $this->localizationHelper->sendLocalizedEmail(
            $recipients,
            function ($toUsers, $emails, $locale) use ($assignment) {
                $result = $this->renderNewAssignment($assignment, $locale);

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
     * Prepare and format body of the new assignment mail
     * @param AssignmentBase $assignment
     * @param string $locale
     * @return EmailRenderResult
     * @throws InvalidStateException
     */
    private function renderNewAssignment(AssignmentBase $assignment, string $locale): EmailRenderResult
    {
        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        if ($assignment instanceof Assignment) {
            $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/newAssignmentEmail_{locale}.latte");
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
                    "firstDeadline" => $assignment->getFirstDeadline(),
                    "allowSecondDeadline" => $assignment->getAllowSecondDeadline(),
                    "secondDeadline" => $assignment->getSecondDeadline(),
                    "attempts" => $assignment->getSubmissionsCountLimit(),
                    "points" => $assignment->getMaxPointsBeforeFirstDeadline(),
                    "link" => $this->webappLinks->getAssignmentPageUrl($assignment->getId()),
                ]
            );
        } else {
            if ($assignment instanceof ShadowAssignment) {
                $template = EmailLocalizationHelper::getTemplate(
                    $locale,
                    __DIR__ . "/newShadowAssignmentEmail_{locale}.latte"
                );
                return $latte->renderEmail(
                    $template,
                    [
                        "name" => EmailLocalizationHelper::getLocalization(
                            $locale,
                            $assignment->getLocalizedTexts()
                        )->getName(),
                        "group" => EmailLocalizationHelper::getLocalization(
                            $locale,
                            $assignment->getGroup()->getLocalizedTexts()
                        )->getName(),
                        "maxPoints" => $assignment->getMaxPoints(),
                        "link" => $this->webappLinks->getShadowAssignmentPageUrl($assignment->getId()),
                    ]
                );
            } else {
                throw new InvalidStateException("Unknown type of assignment");
            }
        }
    }

    /**
     * Deadline of assignment is nearby so users who did not submit any solution
     * should be alerted.
     * @param Assignment $assignment
     * @return bool
     * @throws InvalidStateException
     */
    public function assignmentDeadline(Assignment $assignment): bool
    {
        if ($assignment->getGroup() === null) {
            // group was deleted, do not send emails
            return false;
        }

        $recipients = [];
        /** @var User $student */
        foreach ($assignment->getGroup()->getStudents() as $student) {
            if (
                count($this->assignmentSolutions->findSolutions($assignment, $student)) > 0 ||
                !$student->isVerified() ||
                !$student->getSettings()->getAssignmentDeadlineEmails()
            ) {
                // student already submitted solution to this assignment or
                // disabled sending emails
                continue;
            }

            $recipients[] = $student;
        }

        if (count($recipients) === 0) {
            return true;
        }

        return $this->localizationHelper->sendLocalizedEmail(
            $recipients,
            function ($toUsers, $emails, $locale) use ($assignment) {
                $result = $this->createAssignmentDeadline($assignment, $locale);

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
     * Prepare and format body of the assignment deadline mail
     * @param Assignment $assignment
     * @param string $locale
     * @return EmailRenderResult
     * @throws InvalidStateException
     */
    private function createAssignmentDeadline(Assignment $assignment, string $locale): EmailRenderResult
    {
        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $localizedGroup = EmailLocalizationHelper::getLocalization(
            $locale,
            $assignment->getGroup()->getLocalizedTexts()
        );
        $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/assignmentDeadline_{locale}.latte");
        return $latte->renderEmail(
            $template,
            [
                "assignment" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $assignment->getLocalizedTexts()
                )->getName(),
                "group" => $localizedGroup ? $localizedGroup->getName() : "",
                "firstDeadline" => $assignment->getFirstDeadline(),
                "allowSecondDeadline" => $assignment->getAllowSecondDeadline(),
                "secondDeadline" => $assignment->getSecondDeadline(),
                "link" => $this->webappLinks->getAssignmentPageUrl($assignment->getId()),
            ]
        );
    }

    /**
     * Deadline of shadow assignment is nearby so the users should be alerted.
     * @param ShadowAssignment $assignment
     * @return bool
     * @throws InvalidStateException
     */
    public function shadowAssignmentDeadline(ShadowAssignment $assignment): bool
    {
        if ($assignment->getGroup() === null) {
            // group was deleted, do not send emails
            return false;
        }

        $recipients = [];
        /** @var User $student */
        foreach ($assignment->getGroup()->getStudents() as $student) {
            if (!$student->isVerified() || !$student->getSettings()->getAssignmentDeadlineEmails()) {
                continue;  // disabled sending emails or not verified email
            }

            $recipients[] = $student;
        }

        if (count($recipients) === 0) {
            return true;
        }

        return $this->localizationHelper->sendLocalizedEmail(
            $recipients,
            function ($toUsers, $emails, $locale) use ($assignment) {
                $result = $this->createShadowAssignmentDeadline($assignment, $locale);

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
     * Prepare and format body of the assignment deadline mail
     * @param ShadowAssignment $assignment
     * @param string $locale
     * @return EmailRenderResult
     * @throws InvalidStateException
     */
    private function createShadowAssignmentDeadline(ShadowAssignment $assignment, string $locale): EmailRenderResult
    {
        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $localizedGroup = EmailLocalizationHelper::getLocalization(
            $locale,
            $assignment->getGroup()->getLocalizedTexts()
        );
        $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/shadowAssignmentDeadline_{locale}.latte");
        return $latte->renderEmail(
            $template,
            [
                "assignment" => EmailLocalizationHelper::getLocalization(
                    $locale,
                    $assignment->getLocalizedTexts()
                )->getName(),
                "group" => $localizedGroup ? $localizedGroup->getName() : "",
                "deadline" => $assignment->getDeadline(),
                "link" => $this->webappLinks->getShadowAssignmentPageUrl($assignment->getId()),
            ]
        );
    }
}
