<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\BrokerProxy;
use App\V1Module\Presenters\BrokerPresenter;
use Tester\Assert;


/**
 * @testCase
 */
class TestBrokerPresenter extends Tester\TestCase
{
    /** @var BrokerPresenter */
    protected $presenter;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Nette\Security\User */
    private $user;

    /** @var \App\Security\AccessManager */
    private $accessManager;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->accessManager = $container->getByType(\App\Security\AccessManager::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, BrokerPresenter::class);
    }

    protected function tearDown()
    {
        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    public function testStats()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $stats = [
            "first" => "first",
            "second" => "second"
        ];

        /** @var Mockery\Mock | BrokerProxy $mockBrokerProxy */
        $mockBrokerProxy = Mockery::mock(BrokerProxy::class);
        $mockBrokerProxy->shouldReceive("getStats")->andReturn($stats)->once();
        $this->presenter->brokerProxy = $mockBrokerProxy;

        $request = new Nette\Application\Request(
            'V1:Broker', 'GET', ['action' => 'stats']
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal($stats, $result['payload']);
    }

    public function testFreeze()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var Mockery\Mock | BrokerProxy $mockBrokerProxy */
        $mockBrokerProxy = Mockery::mock(BrokerProxy::class);
        $mockBrokerProxy->shouldReceive("freeze")->once();
        $this->presenter->brokerProxy = $mockBrokerProxy;

        $request = new Nette\Application\Request(
            'V1:Broker', 'POST', ['action' => 'freeze']
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);
    }

    public function testUnfreeze()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var Mockery\Mock | BrokerProxy $mockBrokerProxy */
        $mockBrokerProxy = Mockery::mock(BrokerProxy::class);
        $mockBrokerProxy->shouldReceive("unfreeze")->once();
        $this->presenter->brokerProxy = $mockBrokerProxy;

        $request = new Nette\Application\Request(
            'V1:Broker', 'POST', ['action' => 'unfreeze']
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);
    }
}

$testCase = new TestBrokerPresenter();
$testCase->run();
