<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Model\Entity\User;
use App\Model\Entity\UserCalendar;
use App\Model\Entity\Assignment;
use App\Model\Repository\Users;
use App\Model\Repository\UserCalendars;
use App\Model\Repository\Groups;
use App\Security\ACL\IUserPermissions;
use App\Security\ACL\IAssignmentPermissions;
use App\Security\ACL\IGroupPermissions;
use App\Security\Loader;
use App\Security\Identity;
use App\Responses\CalendarResponse;
use App\Helpers\WebappLinks;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Eluceo\iCal\Domain\ValueObject;
use DateTime;

/**
 * User iCal management endpoints
 */
class UserCalendarsPresenter extends BasePresenter
{
    /**
     * @var UserCalendars
     * @inject
     */
    public $userCalendars;

    /**
     * @var Users
     * @inject
     */
    public $users;

    /**
     * @var Groups
     * @inject
     */
    public $groups;

    /**
     * @var IUserPermissions
     * @inject
     */
    public $userAcl;

    /**
     * @var Loader
     * @inject
     */
    public $aclLoader;

    /**
     * @var WebappLinks
     * @inject
     */
    public $webappLinks;

    /**
     * Helper that selects best localized entity from associative array.
     * @param array $texts [ lang => localized entity ]
     * @param string $lang identifier
     * @return mixed
     */
    private static function getBestLoale(array $texts, string $lang): mixed
    {
        return !empty($texts[$lang]) ? $texts[$lang] : (!empty($texts['en']) ? $texts['en'] : reset($texts));
    }

    /**
     * Constructs an iCal event for the deadline of an assignment.
     * @param User $user
     * @param Assignment $assignment
     * @param string $lang
     * @return Event
     */
    private function createDeadlineEvent(User $user, Assignment $assignment, string $lang): Event
    {
        $id = [ 'recodex', $user->getId(), $assignment->getId() ];
        $event = new Event(new ValueObject\UniqueIdentifier(join('/', $id)));

        // time
        $deadline = new ValueObject\DateTime($assignment->getFirstDeadline(), false);
        $event->setOccurrence(new ValueObject\TimeSpan($deadline, $deadline));

        // capion
        $texts = self::getBestLoale($assignment->getLocalizedTextsAssocArray(), $lang);
        if ($texts) {
            $event->setSummary('ReCodEx deadline: ' . $texts->getName());
        }

        // url
        $assignmentUrl = $this->webappLinks->getAssignmentPageUrl($assignment->getId());
        $event->setUrl(new ValueObject\Uri($assignmentUrl));

        // location
        $group = $assignment->getGroup();
        if ($group) {
            $groupTexts = self::getBestLoale($group->getLocalizedTextsAssocArray(), $lang);
            if ($groupTexts) {
                $location = new ValueObject\Location($groupTexts->getName());
                $event->setLocation($location);
            }
        }
        return $event;
    }

    /**
     * Get calendar values in iCal format that correspond to given token.
     * @GET
     * @param string $id the iCal token
     */
    public function actionDefault(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUserCalendars(string $id)
    {
        $user = $this->users->findOrThrow($id);
        if (!$this->userAcl->canViewCalendars($user)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get all iCal tokens of one user (including expired ones).
     * @GET
     * @param string $id of the user
     */
    public function actionUserCalendars(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckCreateCalendar(string $id)
    {
        $user = $this->users->findOrThrow($id);
        if (!$this->userAcl->canEditCalendars($user)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Create new iCal token for a particular user.
     * @POST
     * @param string $id of the user
     */
    public function actionCreateCalendar(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckExpireCalendar(string $id)
    {
        $calendar = $this->userCalendars->findOrThrow($id);
        $user = $calendar->getUser();
        if (!$user || !$this->userAcl->canEditCalendars($user)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Set given iCal token to expired state. Expired tokens cannot be used to retrieve calendars.
     * @DELETE
     * @param string $id the iCal token
     */
    public function actionExpireCalendar(string $id)
    {
        $this->sendSuccessResponse("OK");
    }
}
