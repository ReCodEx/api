<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\Localizations;
use App\Model\Entity\Instance;
use App\V1Module\Presenters\InstancesPresenter;
use Tester\Assert;
use App\Model\Entity\Licence;

/**
 * @httpCode any
 * @testCase
 */
class TestInstancesPresenter extends Tester\TestCase
{
    private $adminLogin = "admin@admin.com";
    private $adminPassword = "admin";

    private $userLogin = "user2@example.com";
    private $userPassword = "password2";

    /** @var InstancesPresenter */
    protected $presenter;

    /** @var Kdyby\Doctrine\EntityManager */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Nette\Security\User */
    private $user;

    /** @var App\Security\AccessManager */
    private $accessManager;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->accessManager = $container->getByType(\App\Security\AccessManager::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, InstancesPresenter::class);
    }

    protected function tearDown()
    {
        Mockery::close();

        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    public function testGetAllInstances()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);

        $request = new Nette\Application\Request('V1:Instances', 'GET', ['action' => 'default']);
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal(1, count($result['payload']));
        $instance = array_pop($result['payload']);
        Assert::equal("Frankenstein University, Atlantida", $instance['name']);
    }

    public function testGetAllInstancesUnauthenticated()
    {
        $request = new Nette\Application\Request('V1:Instances', 'GET', ['action' => 'default']);
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal(1, count($result['payload']));
        $instance = array_pop($result['payload']);
        Assert::equal("Frankenstein University, Atlantida", $instance['name']);
    }

    public function testCreateInstance()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);

        $request = new Nette\Application\Request(
            'V1:Instances',
            'POST',
            ['action' => 'createInstance'],
            ['name' => 'NIOT', 'description' => 'Just a new instance', 'isOpen' => 'true']
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(201, $result['code']);
        $instance = $result['payload'];
        Assert::equal("NIOT", $instance['name']);
        Assert::true($instance['isOpen']);
        Assert::equal("Just a new instance", $instance['description']);
    }

    public function testUpdateInstance()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);

        $allInstances = $this->presenter->instances->findAll();
        $instance = array_pop($allInstances);

        $request = new Nette\Application\Request(
            'V1:Instances',
            'POST',
            ['action' => 'updateInstance', 'id' => $instance->id],
            ['isOpen' => 'false']
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        /** @var Instance $instance */
        $instance = $result['payload'];
        Assert::equal(false, $instance['isOpen']);
    }

    public function testDeleteInstance()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);

        // create new testing instance for further deletion
        $request = new Nette\Application\Request(
            'V1:Instances',
            'POST',
            ['action' => 'createInstance'],
            ['name' => 'NIOT', 'description' => 'Just a new instance', 'isOpen' => 'true']
        );
        $response = $this->presenter->run($request);
        $newInstanceId = $response->getPayload()['payload']['id'];

        $allInstances = $this->presenter->instances->findAll();
        Assert::equal(2, count($allInstances));

        $request = new Nette\Application\Request(
            'V1:Instances',
            'DELETE',
            ['action' => 'deleteInstance', 'id' => $newInstanceId]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);
        Assert::equal(1, count($this->presenter->instances->findAll()));
    }

    public function testGetLicences()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);

        $allInstances = $this->presenter->instances->findAll();
        $instance = array_pop($allInstances);

        $request = new Nette\Application\Request(
            'V1:Instances', 'GET', ['action' => 'licences', 'id' => $instance->id]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal($this->presenter->instances->get($instance->id)->getLicences()->getValues(), $result['payload']);
    }

    public function testCreateLicence()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);

        $allInstances = $this->presenter->instances->findAll();
        $instance = array_pop($allInstances);
        $validUntil = (new \DateTime())->setTimestamp((new \DateTime())->getTimestamp());

        $request = new Nette\Application\Request(
            'V1:Instances',
            'POST',
            ['action' => 'createLicence', 'id' => $instance->id],
            ['note' => 'Another year', 'validUntil' => $validUntil->getTimestamp()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        $licence = $result['payload'];
        Assert::equal($instance->id, $licence->instance->id);
        Assert::equal('Another year', $licence->note);
        Assert::equal($validUntil, $licence->validUntil);
    }

    public function testUpdateLicence()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);

        // create testing licence
        $allInstances = $this->presenter->instances->findAll();
        $instance = array_pop($allInstances);
        $newLicence = Licence::createLicence('Another year', new \DateTime('2017-05-12 13:02:56'), $instance);
        $this->presenter->licences->persist($newLicence);

        // actual test to update the licence
        $request = new Nette\Application\Request(
            'V1:Instances',
            'POST',
            ['action' => 'updateLicence', 'licenceId' => $newLicence->id],
            ['note' => 'Changed description', 'validUntil' => '2020-01-01 13:02:56', 'isValid' => 'false']
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        // check invariants
        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        $licence = $result['payload'];
        Assert::equal('Changed description', $licence->note);
        Assert::equal(new DateTime('2020-01-01 13:02:56'), $licence->validUntil);
        Assert::false($licence->isValid);
        Assert::equal($newLicence->note, $licence->note);
    }

    public function testRemoveLicence()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);

        // create testing licence
        $allInstances = $this->presenter->instances->findAll();
        $instance = array_pop($allInstances);
        $newLicence = Licence::createLicence('Another year', new \DateTime('2017-05-12 13:02:56'), $instance);
        $this->presenter->licences->persist($newLicence);

        // check there are two licences for this instance
        Assert::equal(2, $instance->licences->count());

        // perform delete request
        $request = new Nette\Application\Request(
            'V1:Instances',
            'DELETE',
            ['action' => 'deleteLicence', 'licenceId' => $newLicence->id]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        // check invariants
        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);
        Assert::equal(1, $instance->licences->count());
    }

}

$testCase = new TestInstancesPresenter();
$testCase->run();
