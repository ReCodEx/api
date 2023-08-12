<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\UserCalendarsPresenter;
use App\Model\Entity\UserCalendar;
use App\Model\Repository\Assignments;
use App\Model\Repository\UserCalendars;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\WrongCredentialsException;
use App\Exceptions\NotFoundException;
use App\Exceptions\BadRequestException;
use App\Responses\CalendarResponse;
use Tester\Assert;
use Nette\Application\Request;

/**
 * @httpCode any
 * @testCase
 */
class TestUserCalendarsPresenter extends Tester\TestCase
{
    /** @var UserCalendarsPresenter */
    protected $presenter;

    /** @var string */
    private $presenterPath = "V1:UserCalendars";

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Assignments */
    private $assignments;

    /** @var UserCalendars */
    private $userCalendars;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->assignments = $container->getByType(Assignments::class);
        $this->userCalendars = $container->getByType(UserCalendars::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, UserCalendarsPresenter::class);
    }

    protected function tearDown()
    {
        Mockery::close();
    }

    public function testCreateCalendar()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);
        $user = PresenterTestHelper::getUser($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'POST',
            ['action' => 'createCalendar', 'id' => $user->getId()],
            []
        );

        $calendars = $this->userCalendars->findBy([ 'user' => $user ]);
        Assert::count(1, $calendars);
        Assert::equal($calendars[0]->getId(), $payload->getId());
    }

    public function testCreateCalendarOfAnotherUserFails()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::GROUP_SUPERVISOR_LOGIN);
        $user = PresenterTestHelper::getUser($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);

        Assert::exception(
            function () use ($user) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    $this->presenterPath,
                    'POST',
                    ['action' => 'createCalendar', 'id' => $user->getId()],
                    []
                );
            },
            ForbiddenRequestException::class
        );
    }

    public function testExpireCalendar()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);
        $user = PresenterTestHelper::getUser($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);
        $calendar = new UserCalendar($user);
        $this->userCalendars->persist($calendar);
        $this->userCalendars->flush();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'POST',
            ['action' => 'expireCalendar', 'id' => $calendar->getId()],
            []
        );

        $this->userCalendars->refresh($calendar);
        Assert::true($calendar->isExpired());
    }

    public function testExpireCalendarOfAnotherUserFails()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::GROUP_SUPERVISOR_LOGIN);
        $user = PresenterTestHelper::getUser($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);
        $calendar = new UserCalendar($user);
        $this->userCalendars->persist($calendar);
        $this->userCalendars->flush();

        Assert::exception(
            function () use ($calendar) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    $this->presenterPath,
                    'POST',
                    ['action' => 'expireCalendar', 'id' => $calendar->getId()],
                    []
                );
            },
            ForbiddenRequestException::class
        );
    }

    public function testGetUserCalendars()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);
        $user = PresenterTestHelper::getUser($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);

        $calendar1 = new UserCalendar($user);
        $calendar1->setExpiredAt();
        $this->userCalendars->persist($calendar1);

        $calendar2 = new UserCalendar($user);
        $this->userCalendars->persist($calendar2);
        $this->userCalendars->flush();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'POST',
            ['action' => 'userCalendars', 'id' => $user->getId()],
            []
        );

        Assert::count(2, $payload);
        $ids = [ $calendar1->getId(), $calendar2->getId() ];
        $payloadIds = [ $payload[0]->getId(), $payload[1]->getId() ];
        sort($ids);
        sort($payloadIds);
        Assert::equal($ids, $payloadIds);
    }

    public function testGetUserCalendarsOfAnotherUserFails()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::GROUP_SUPERVISOR_LOGIN);
        $user = PresenterTestHelper::getUser($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);

        Assert::exception(
            function () use ($user) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    $this->presenterPath,
                    'POST',
                    ['action' => 'userCalendars', 'id' => $user->getId()],
                    []
                );
            },
            ForbiddenRequestException::class
        );
    }

    public function testGetIcalData()
    {
        $user = PresenterTestHelper::getUser($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);

        $calendar = new UserCalendar($user);
        $this->userCalendars->persist($calendar);
        $this->userCalendars->flush();

        $request = new Request($this->presenterPath, 'POST', ['action' => 'default', 'id' => $calendar->getId()], []);
        $response = $this->presenter->run($request);
        Assert::type(CalendarResponse::class, $response);

        $calendarComponent = $response->getCalendarComponent();
        $output = trim((string)$calendarComponent);
        $output = str_replace("\r", '', $output);
        $output = preg_replace('/^(UID:|URL:|DTSTAMP:| ).*$/m', '', $output);

        $expected = <<<XXX
BEGIN:VCALENDAR
PRODID:-//ReCodEx Team at MFF-UK/ReCodEx//2.x/EN
VERSION:2.0
CALSCALE:GREGORIAN
BEGIN:VEVENT



SUMMARY:ReCodEx deadline: Convex hull


DTSTART:{DEADLINE}
DTEND:{DEADLINE}
LOCATION:Demo group
END:VEVENT
END:VCALENDAR
XXX;
        $assignment = current($this->assignments->findAll());
        $expected = str_replace('{DEADLINE}', $assignment->getFirstDeadline()->format('Ymd\\THis'), $expected);
        Assert::equal($expected, $output);
    }

    public function testGetIcalDataOfExpiredCalendarFails()
    {
        $user = PresenterTestHelper::getUser($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);

        $calendar = new UserCalendar($user);
        $calendar->setExpiredAt();
        $this->userCalendars->persist($calendar);
        $this->userCalendars->flush();

        Assert::exception(
            function () use ($calendar) {
                $request = new Request($this->presenterPath, 'POST', ['action' => 'default', 'id' => $calendar->getId()], []);
                $this->presenter->run($request);
            },
            BadRequestException::class
        );
    }
}

$testCase = new TestUserCalendarsPresenter();
$testCase->run();
