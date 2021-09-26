<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\BrokerProxy;
use App\V1Module\Presenters\AsyncJobsPresenter;
use App\Helpers\FileStorageManager;
use App\Helpers\TmpFilesHelper;
use App\Helpers\FileStorage\LocalFileStorage;
use App\Helpers\FileStorage\LocalHashFileStorage;
use App\Model\Entity\AsyncJob;
use App\Model\Repository\AsyncJobs;
use App\Async\Dispatcher;
use App\Async\Worker;
use App\Async\Handler\PingAsyncJobHandler;
use Tester\Assert;
use Tracy\ILogger;

/**
 * @testCase
 */
class TestAsyncJobsPresenter extends Tester\TestCase
{
    /** @var AsyncJobsPresenter */
    protected $presenter;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Nette\Security\User */
    private $user;

    /** @var AsyncJobs */
    private $asyncJobs;

    /** @var Dispatcher */
    private $dispatcher;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->asyncJobs = $this->container->getByType(AsyncJobs::class);
        $this->dispatcher = new Dispatcher([], ['ping' => new PingAsyncJobHandler()], $this->asyncJobs);

        // patch container, since we cannot create actual file storage manarer
        $fsName = current($this->container->findByType(FileStorageManager::class));
        $this->container->removeService($fsName);
        $this->container->addService($fsName, new FileStorageManager(
            Mockery::mock(LocalFileStorage::class),
            Mockery::mock(LocalHashFileStorage::class),
            Mockery::mock(TmpFilesHelper::class),
            ""
        ));
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, AsyncJobsPresenter::class);
    }

    protected function tearDown()
    {
        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    protected function createWorker()
    {
        $logger = $this->container->getByType(ILogger::class);
        return new Worker(['pollingInterval' => 1, 'quiet' => true], $this->dispatcher, $this->asyncJobs, $logger);
    }

    public function testPing()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $asyncJob = PresenterTestHelper::performPresenterRequest($this->presenter, 'V1:AsyncJobs', 'POST', ['action' => 'ping']);

        Assert::type(AsyncJob::class, $asyncJob);
        Assert::equal('ping', $asyncJob->getCommand());
        Assert::null($asyncJob->getFinishedAt());

        $worker = $this->createWorker();
        $worker->run('w1');

        $asyncJob2 = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AsyncJobs',
            'GET',
            ['action' => 'default', 'id' => $asyncJob->getId()]
        );

        Assert::type(AsyncJob::class, $asyncJob2);
        Assert::equal($asyncJob->getId(), $asyncJob2->getId());

        $this->asyncJobs->refresh($asyncJob);
        Assert::truthy($asyncJob->getFinishedAt());
        Assert::null($asyncJob->getError());
    }


    public function testPingAbort()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $asyncJob = PresenterTestHelper::performPresenterRequest($this->presenter, 'V1:AsyncJobs', 'POST', ['action' => 'ping']);

        Assert::type(AsyncJob::class, $asyncJob);
        Assert::equal('ping', $asyncJob->getCommand());
        Assert::null($asyncJob->getFinishedAt());

        $asyncJob2 = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AsyncJobs',
            'POST',
            ['action' => 'abort', 'id' => $asyncJob->getId()]
        );

        Assert::type(AsyncJob::class, $asyncJob2);
        Assert::equal($asyncJob->getId(), $asyncJob2->getId());

        $this->asyncJobs->refresh($asyncJob);
        Assert::truthy($asyncJob->getFinishedAt());
        Assert::equal('ABORTED', $asyncJob->getError());
    }

    public function testMultiplePingsListAndSchedule()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        // create one scheduled, and two regular ping jobs
        for ($i = 0; $i < 3; ++$i) {
            $job = new AsyncJob(null, 'ping');
            if (!$i) {
                $date = new DateTime();
                $date->modify("+1 day");
                $job->setScheduledAt($date);
            }
            $this->asyncJobs->persist($job);
        }

        $worker = $this->createWorker();
        $worker->run('w1');

        // use list to see how it ended
        $list = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AsyncJobs',
            'GET',
            ['action' => 'list', 'ageThreshold' => 1000, 'includeScheduled' => false]
        );

        Assert::count(2, $list);
        $terminated = 0;
        foreach ($list as $asyncJob) {
            Assert::type(AsyncJob::class, $asyncJob);
            Assert::equal('ping', $asyncJob->getCommand());
            if ($asyncJob->getFinishedAt()) {
                ++$terminated;
            }
        }
        Assert::equal(1, $terminated);
    }
}

$testCase = new TestAsyncJobsPresenter();
$testCase->run();
