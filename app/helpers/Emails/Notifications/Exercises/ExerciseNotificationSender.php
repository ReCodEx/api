<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailRenderResult;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\GeneralStatsHelper;
use App\Helpers\GeneralStats;
use App\Helpers\EmailHelper;
use App\Helpers\WebappLinks;
use App\Model\Entity\Exercise;
use App\Model\Entity\User;
use App\Model\Entity\Group;
use App\Model\Entity\GroupMembership;
use DateTime;
use DateInterval;
use Nette\Utils\Arrays;

/**
 * Sending exercise-related notifications.
 */
class ExerciseNotificationSender
{
    /** @var string */
    private $sender;

    /** @var EmailHelper */
    private $emailHelper;

    /** @var EmailLocalizationHelper */
    private $localizationHelper;

    /** @var WebappLinks */
    private $webappLinks;

    /**
     * Constructor.
     * @param array $config
     * @param EmailHelper $emailHelper
     * @param EmailLocalizationHelper $localizationHelper
     * @param WebappLinks $webappLinks
     * @throws InvalidStateException
     */
    public function __construct(
        array $config,
        EmailHelper $emailHelper,
        EmailLocalizationHelper $localizationHelper,
        WebappLinks $webappLinks
    ) {
        $this->sender = Arrays::get($config, ["emails", "from"], "noreply@recodex");
        $this->emailHelper = $emailHelper;
        $this->localizationHelper = $localizationHelper;
        $this->webappLinks = $webappLinks;
    }

    /**
     * Prepare localized email with exercise notification.
     * @param Exercise $exercise
     * @param User $user author of the message
     * @param string $message
     * @param string $locale
     * @return EmailRenderResult
     */
    private function renderNotificationEmail(
        Exercise $exercise,
        User $user,
        string $message,
        string $locale
    ): EmailRenderResult {
        $latte = EmailLatteFactory::latte();
        $values = [
            'exercise' => EmailLocalizationHelper::getLocalization(
                $locale,
                $exercise->getLocalizedTexts()
            )->getName(),
            'user' => $user->getName(),
            'email' => $user->getEmail(),
            'message' => trim($message),
            'link' => $this->webappLinks->getExercisePageUrl($exercise->getId()),
        ];
        $template = EmailLocalizationHelper::getTemplate($locale, __DIR__ . "/exerciseNotification_{locale}.latte");
        return $latte->renderEmail($template, $values);
    }

    /**
     * Notify all teachers who used the exercise in their groups.
     * @param Exercise $exercise
     * @param User $user
     * @param string $message
     * @return int number of users actually notified (-1 on error)
     */
    public function sendNotification(Exercise $exercise, User $user, string $message): int
    {
        $recipients = []; // collect admins/supervisors of involved groups, email is the key (for deduplication)
        $membershipTypes = [GroupMembership::TYPE_ADMIN, GroupMembership::TYPE_SUPERVISOR];
        foreach ($exercise->getAssignments() as $assignment) {
            $group = $assignment->getGroup();
            if ($group) {
                foreach ($group->getMembers(...$membershipTypes) as $u) {
                    if (
                        $u->getId() !== $user->getId() && $u->isVerified()
                        && $u->getSettings()->getExerciseNotificationEmails()
                    ) {
                        $recipients[$u->getEmail()] = $u;
                    }
                }
            }
        }

        if (!$recipients) {
            return 0;
        }

        $res = $this->localizationHelper->sendLocalizedEmail(
            array_values($recipients),
            function ($toUsers, $emails, $locale) use ($exercise, $user, $message) {
                $mail = $this->renderNotificationEmail($exercise, $user, $message, $locale);

                // Send the mail
                return $this->emailHelper->setShowSettingsInfo()->send(
                    $this->sender,
                    [],
                    $locale,
                    $mail->getSubject(),
                    $mail->getText(),
                    $emails
                );
            }
        );

        return $res ? count($recipients) : -1;
    }
}
