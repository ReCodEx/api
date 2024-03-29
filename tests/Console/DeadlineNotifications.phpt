<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Console\SendAssignmentDeadlineNotification;
use App\Helpers\Localizations;
use App\Helpers\EmailHelper;
use App\Helpers\WebappLinks;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Helpers\Notifications\AssignmentEmailsSender;
use App\Model\Entity\Assignment;
use App\Model\Entity\ShadowAssignment;
use App\Model\Entity\Exercise;
use App\Model\Entity\Group;
use App\Model\Entity\LocalizedShadowAssignment;
use App\Model\Repository\Assignments;
use App\Model\Repository\ShadowAssignments;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\Exercises;
use App\Model\Repository\Groups;
use App\Model\Repository\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Tester\Assert;

/**
 * @testCase
 */
class TestDeadlineNotifications extends Tester\TestCase
{
    /** @var Assignments */
    private $assignments;

    /** @var ShadowAssignments */
    private $shadowAssignments;

    /** @var AssignmentSolutions */
    private $assignmentSolutions;

    /** @var EmailLocalizationHelper */
    private $localizationHelper;

    /** @var SendAssignmentDeadlineNotification */
    protected $command;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var Nette\DI\Container */
    private $container;

    /** @var Users */
    private $users;

    /** @var Mockery\Mock|EmailHelper */
    private $emailHelperMock;

    /** @var AssignmentEmailsSender */
    private $sender;

    /** @var Exercise */
    private $demoExercise;

    /** @var Group */
    private $demoGroup;

    public function __construct(Nette\DI\Container $container)
    {
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->users = $this->container->getByType(Users::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->demoExercise = $this->container->getByType(Exercises::class)->findAll()[0];

        /** @var Group $group */
        foreach ($this->container->getByType(Groups::class)->findAll() as $group) {
            if ($group->getStudents()->count() > 0) {
                $this->demoGroup = $group;
            }
        }

        $this->emailHelperMock = Mockery::mock(EmailHelper::class);
        $this->assignments = $this->container->getByType(Assignments::class);
        $this->shadowAssignments = $this->container->getByType(ShadowAssignments::class);
        $this->assignmentSolutions = $this->container->getByType(AssignmentSolutions::class);
        $this->localizationHelper = $this->container->getByType(EmailLocalizationHelper::class);
        $this->sender = new AssignmentEmailsSender(
            [],
            $this->emailHelperMock,
            $this->assignmentSolutions,
            $this->localizationHelper,
            new WebappLinks('', []),
        );
        $this->command = new SendAssignmentDeadlineNotification(
            "",
            "1 day",
            $this->assignments,
            $this->shadowAssignments,
            $this->sender
        );
    }

    protected function tearDown()
    {
        Mockery::close();
    }

    public function testNothing()
    {
        $assignment = Assignment::assignToGroup($this->demoExercise, $this->demoGroup, true);

        $deadline = new DateTime();
        $deadline->modify("+3 days");
        $assignment->setFirstDeadline($deadline);
        $this->assignments->persist($assignment);

        $this->emailHelperMock->shouldNotReceive("send");
        $input = new StringInput("");
        $this->command->run($input, new NullOutput());

        Assert::true(true); // We make no assertions here - all the work is done by Mockery
    }

    public function testFirstDeadlineNearby()
    {
        $assignment = Assignment::assignToGroup($this->demoExercise, $this->demoGroup, true);

        $deadline = new DateTime();
        $deadline->modify("+12 hours");
        $assignment->setFirstDeadline($deadline);
        $this->assignments->persist($assignment);

        $this->emailHelperMock->shouldReceive("setShowSettingsInfo")->once()->andReturn($this->emailHelperMock);
        $this->emailHelperMock->shouldReceive("send")->once()->andReturn(true);
        $input = new StringInput("");
        $this->command->run($input, new NullOutput());

        Assert::true(true); // We make no assertions here - all the work is done by Mockery
    }

    public function testShadowAssignmentDeadlineNearby()
    {
        $assignment = ShadowAssignment::createInGroup($this->demoGroup, true);
        $localizedTexts = [];
        foreach (['cs', 'en'] as $lang) {
            $localizedTexts[$lang] = new LocalizedShadowAssignment($lang, "Foo", "Bar", null);
        }
        Localizations::updateCollection($assignment->getLocalizedTexts(), $localizedTexts);
        foreach ($assignment->getLocalizedTexts() as $localizedText) {
            $this->shadowAssignments->persist($localizedText, false);
        }

        $deadline = new DateTime();
        $deadline->modify("+12 hours");
        $assignment->setDeadline($deadline);
        $this->shadowAssignments->persist($assignment);

        $this->emailHelperMock->shouldReceive("setShowSettingsInfo")->once()->andReturn($this->emailHelperMock);
        $this->emailHelperMock->shouldReceive("send")->once()->andReturn(true);
        $input = new StringInput("");
        $this->command->run($input, new NullOutput());

        Assert::true(true); // We make no assertions here - all the work is done by Mockery
    }
}

$testCase = new TestDeadlineNotifications($container);
$testCase->run();
