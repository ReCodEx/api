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
        $calendar = $this->userCalendars->findOrThrow($id);
        if ($calendar->isExpired()) {
            throw new BadRequestException("Given iCal identifier has expired.");
        }

        $user = $calendar->getUser();
        if (!$user) {
            throw new BadRequestException("The user attached to given iCal identifier was deleted.");
        }

        // prepare ACLs for given user (this endpoint is not using identity)
        /** @var IAssignmentPermissions */
        $assignmentAcl = $this->aclLoader->loadACLModule(
            IAssignmentPermissions::class,
            $this->authorizator,
            new Identity($user, null)
        );

        /** @var IGroupPermissions */
        $groupAcl = $this->aclLoader->loadACLModule(
            IGroupPermissions::class,
            $this->authorizator,
            new Identity($user, null)
        );

        // get all relevant assignments from all groups related to user...
        $lang = $user->getSettings()->getDefaultLanguage();
        $events = [];
        foreach ($this->groups->findGroupsByMembership($user) as $group) {
            if ($groupAcl->canViewAssignments($group)) {
                foreach ($group->getAssignments() as $assignment) {
                    if ($assignmentAcl->canViewDetail($assignment)) {
                        $events[] = $this->createDeadlineEvent($user, $assignment, $lang);
                    }
                }
            }
        }

        // prepare the calendar entity that wraps the events
        $calendarEntity = new Calendar($events);
        $calendarEntity->setProductIdentifier('-//ReCodEx Team at MFF-UK/ReCodEx//2.x/EN');

        // render the deadline events
        $componentFactory = new CalendarFactory();
        $calendarComponent = $componentFactory->createCalendar($calendarEntity);
        $this->sendResponse(new CalendarResponse($calendarComponent));
    }

    public function checkUserCalendars(string $id)
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
        $user = $this->users->findOrThrow($id);
        $calendars = $this->userCalendars->findBy([ 'user' => $user ], [ 'createdAt' => 'DESC' ]);
        $this->sendSuccessResponse($calendars);
    }

    public function checkCreateCalendar(string $id)
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
        $user = $this->users->findOrThrow($id);
        $calendar = new UserCalendar($user);
        $this->userCalendars->persist($calendar);
        $this->sendSuccessResponse($calendar);
    }

    public function checkExpireCalendar(string $id)
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
        $calendar = $this->userCalendars->findOrThrow($id);
        $calendar->setExpiredAt();
        $this->userCalendars->persist($calendar);
        $this->sendSuccessResponse($calendar);
    }
}
