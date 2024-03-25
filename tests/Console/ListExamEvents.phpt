<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Console\ListExamEvents;
use App\Model\Entity\Group;
use App\Model\Entity\GroupExam;
use App\Model\Entity\GroupExamLock;
use App\Model\Repository\Users;
use App\Model\Repository\Groups;
use App\Model\Repository\GroupExams;
use App\Model\Repository\GroupExamLocks;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;


// A hack that allows us access protected static field in ListExamEvents class.
class ListExamEventsWrapper extends ListExamEvents
{
    public static function getCsvColumns(): array
    {
        return static::$csvColumns;
    }
}

/**
 * @testCase
 */
class ListExamEventsTest extends Tester\TestCase
{
    private $studentLogin = "submitUser1@example.com";

    /** @var ListExamEvents */
    protected $command;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var Nette\DI\Container */
    private $container;

    /** @var Users */
    private $users;

    /** @var Groups */
    private $groups;

    /** @var GroupExams */
    private $exams;

    /** @var GroupExamLocks */
    private $locks;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->users = $container->getByType(Users::class);
        $this->groups = $container->getByType(Groups::class);
        $this->exams = $container->getByType(GroupExams::class);
        $this->locks = $container->getByType(GroupExamLocks::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->command = $this->container->getByType(ListExamEvents::class);
    }

    private function getGroup(): Group
    {
        $candidates = array_filter(
            $this->groups->findAll(),
            function (Group $g) {
                return $g->getParentGroup() !== null && !$g->isArchived() && !$g->isOrganizational();
            }
        );
        Assert::count(3, $candidates);
        return reset($candidates);
    }

    public function testListNoExamEvents()
    {
        ob_start();

        $this->command->run(
            new Symfony\Component\Console\Input\StringInput(''),
            new Symfony\Component\Console\Output\NullOutput()
        );

        $output = ob_get_contents();
        ob_end_clean();
        Assert::equal('', $output);
    }

    public function testListExamEvents()
    {
        $user = $this->users->getByEmail($this->studentLogin);
        $group = $this->getGroup();

        $now = (new DateTime())->getTimestamp();
        $begin = $now - 3600;
        $end = $now - 1800;
        $exam = new GroupExam($group, DateTime::createFromFormat('U', $begin), DateTime::createFromFormat('U', $end), true);
        $this->exams->persist($exam);

        $lock = new GroupExamLock($exam, $user, '127.0.0.1');
        $lock->overrideCreatedAt(DateTime::createFromFormat('U', $now - 2500));
        $this->locks->persist($lock);

        ob_start();

        $this->command->run(
            new Symfony\Component\Console\Input\StringInput(''),
            new Symfony\Component\Console\Output\NullOutput()
        );

        $output = trim(ob_get_contents());
        ob_end_clean();

        $lines = explode("\n", $output);
        Assert::count(2, $lines);
        $columns = str_getcsv($lines[0], ',');
        Assert::equal(ListExamEventsWrapper::getCsvColumns(), $columns);

        $tokens = str_getcsv($lines[1], ',');
        Assert::count(count($columns), $tokens);
        Assert::equal((string)$lock->getId(), $tokens[0]);
        Assert::equal((string)$exam->getId(), $tokens[1]);
        Assert::equal($group->getId(), $tokens[2]);
        Assert::equal($user->getId(), $tokens[3]);
    }

    public function testListExamEventsInInterval()
    {
        $user = $this->users->getByEmail($this->studentLogin);
        $group = $this->getGroup();
        $now = (new DateTime())->getTimestamp();

        // prepare old lock
        $exam = new GroupExam(
            $group,
            DateTime::createFromFormat('U', $now - 100000),
            DateTime::createFromFormat('U', $now - 98000),
            true
        );
        $this->exams->persist($exam);
        $lock = new GroupExamLock($exam, $user, '127.0.0.1');
        $lock->overrideCreatedAt(DateTime::createFromFormat('U', $now - 99000));
        $this->locks->persist($lock);

        // prepare too recent lock
        $exam = new GroupExam(
            $group,
            DateTime::createFromFormat('U', $now - 3000),
            DateTime::createFromFormat('U', $now),
            true
        );
        $this->exams->persist($exam);
        $lock = new GroupExamLock($exam, $user, '127.0.0.1');
        $lock->overrideCreatedAt(DateTime::createFromFormat('U', $now - 1000));
        $this->locks->persist($lock);

        // prepare the relevant lock
        $exam = new GroupExam(
            $group,
            DateTime::createFromFormat('U', $now - 7200),
            DateTime::createFromFormat('U', $now - 3600),
            true
        );
        $this->exams->persist($exam);
        $lock = new GroupExamLock($exam, $user, '127.0.0.1');
        $lock->overrideCreatedAt(DateTime::createFromFormat('U', $now - 5000));
        $this->locks->persist($lock);

        Assert::count(3, $this->locks->findAll());

        ob_start();

        $this->command->run(
            new Symfony\Component\Console\Input\StringInput('"--from=-1 day" "--to=-1 hour"'),
            new Symfony\Component\Console\Output\NullOutput()
        );

        $output = trim(ob_get_contents());
        ob_end_clean();

        $lines = explode("\n", $output);
        Assert::count(2, $lines);
        $columns = str_getcsv($lines[0], ',');
        Assert::equal(ListExamEventsWrapper::getCsvColumns(), $columns);

        $tokens = str_getcsv($lines[1], ',');
        Assert::count(count($columns), $tokens);
        Assert::equal((string)$lock->getId(), $tokens[0]);
        Assert::equal((string)$exam->getId(), $tokens[1]);
        Assert::equal($group->getId(), $tokens[2]);
        Assert::equal($user->getId(), $tokens[3]);
    }
}

$testCase = new ListExamEventsTest();
$testCase->run();
