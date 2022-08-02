<?php

namespace App\Helpers\Notifications;

use App\Exceptions\InvalidStateException;
use App\Helpers\EmailHelper;
use App\Helpers\WebappLinks;
use App\Helpers\Emails\EmailLatteFactory;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Emails\EmailRenderResult;
use App\Model\Entity\Assignment;
use App\Model\Entity\Comment;
use App\Model\Entity\User;
use Nette\Utils\Arrays;

/**
 * Sending emails when new comment is added in assignment's public discussion.
 */
class AssignmentCommentsEmailsSender
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
     * Comment was added to the assignment discussion.
     * @param Assignment $assignment
     * @param Comment $comment
     * @return bool
     * @throws InvalidStateException
     */
    public function assignmentComment(Assignment $assignment, Comment $comment): bool
    {
        if ($comment->isPrivate()) {
            // comment was private, therefore do not send email to others
            return true;
        }

        $group = $assignment->getGroup();
        if ($group === null || $group->isArchived()) {
            return true;  // no notifications in deleted or archived groups
        }

        // Recepients are all users related to the group (all members + all admins...)
        $recipients = [];
        $authorId = $comment->getUser()->getId();
        foreach ($group->getMembers() as $user) {
            // filter out the author of the comment, it is pointless to send email to that user
            if (
                $user->isVerified() && $user->getId() !== $authorId
                && $user->getSettings()->getAssignmentCommentsEmails()
            ) {
                $recipients[$user->getEmail()] = $user;
            }
        }

        if (count($recipients) === 0) {
            return true;
        }

        return $this->localizationHelper->sendLocalizedEmail(
            $recipients,
            function ($toUsers, $emails, $locale) use ($assignment, $comment) {
                $result = $this->createAssignmentComment($assignment, $comment, $locale);

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
     * Prepare and format body of the assignment comment.
     * @param Assignment $assignment
     * @param Comment $comment
     * @param string $locale
     * @return EmailRenderResult
     * @throws InvalidStateException
     */
    private function createAssignmentComment(
        Assignment $assignment,
        Comment $comment,
        string $locale
    ): EmailRenderResult {
        // render the HTML to string using Latte engine
        $latte = EmailLatteFactory::latte();
        $template = EmailLocalizationHelper::getTemplate(
            $locale,
            __DIR__ . "/assignmentComment_{locale}.latte"
        );
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
                "author" => $comment->getUser() ? $comment->getUser()->getName() : "",
                "date" => $comment->getPostedAt(),
                "comment" => $comment->getText(),
                "link" => $this->webappLinks->getAssignmentPageUrl($assignment->getId())
            ]
        );
    }
}
