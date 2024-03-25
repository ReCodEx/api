<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Console\ListSecurityEvents;
use App\Model\Entity\SecurityEvent;
use App\Model\Repository\Users;
use App\Model\Repository\SecurityEvents;
use App\Model\Repository\GroupExams;
use App\Model\Repository\GroupExamLocks;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;


// A hack that allows us access protected static field in ListSecurityEvents class.
class ListSecurityEventsWrapper extends ListSecurityEvents
{
    public static function getCsvColumns(): array
    {
        return static::$csvColumns;
    }
}

/**
 * @testCase
 */
class ListSecurityEventsTest extends Tester\TestCase
{
    private $studentLogin = "submitUser1@example.com";

    /** @var ListSecurityEvents */
    protected $command;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var Nette\DI\Container */
    private $container;

    /** @var Users */
    private $users;

    /** @var SecurityEvents */
    private $events;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->users = $container->getByType(Users::class);
        $this->events = $container->getByType(SecurityEvents::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->command = $this->container->getByType(ListSecurityEvents::class);
    }

    public function testListNoSecurityEvents()
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

    public function testListSecurityEvents()
    {
        $user = $this->users->getByEmail($this->studentLogin);
        $event = SecurityEvent::createLoginEvent('127.0.0.1', $user);
        $this->events->persist($event);

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
        Assert::equal(ListSecurityEventsWrapper::getCsvColumns(), $columns);

        $tokens = str_getcsv($lines[1], ',');
        Assert::count(count($columns), $tokens);
        Assert::equal((string)$event->getId(), $tokens[0]);
        Assert::equal(SecurityEvent::TYPE_LOGIN, $tokens[2]);
        Assert::equal($user->getId(), $tokens[4]);
    }

    public function testListSecurityEventsInInterval()
    {
        $user = $this->users->getByEmail($this->studentLogin);
        $now = (new DateTime())->getTimestamp();

        // too old
        $event = SecurityEvent::createLoginEvent('127.0.0.1', $user);
        $event->overrideCreatedAt(DateTime::createFromFormat('U', $now - 100000));
        $this->events->persist($event);

        // too recent
        $event = SecurityEvent::createLoginEvent('127.0.0.1', $user);
        $this->events->persist($event);

        // the one that should be listed
        $event = SecurityEvent::createLoginEvent('127.0.0.1', $user);
        $event->overrideCreatedAt(DateTime::createFromFormat('U', $now - 7200));
        $this->events->persist($event);

        Assert::count(3, $this->events->findAll());

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
        Assert::equal(ListSecurityEventsWrapper::getCsvColumns(), $columns);

        $tokens = str_getcsv($lines[1], ',');
        Assert::count(count($columns), $tokens);
        Assert::equal((string)$event->getId(), $tokens[0]);
        Assert::equal(SecurityEvent::TYPE_LOGIN, $tokens[2]);
        Assert::equal($user->getId(), $tokens[4]);
    }
}

$testCase = new ListSecurityEventsTest();
$testCase->run();
