<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\SisHelper;
use App\Model\Entity\ExternalLogin;
use App\Model\Entity\Group;
use App\Model\Entity\User;
use App\Model\Repository\SisGroupBindings;
use App\V1Module\Presenters\SisPresenter;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Tester\Assert;
use Tester\TestCase;

/**
 * @testCase
 */
class TestSisPresenter extends TestCase {
  private const DATA_DIR = __DIR__ . '/sis_data';

  /** @var MockHandler */
  private $httpHandler;

  /** @var SisPresenter */
  private $presenter;

  private $container;

  private $em;

  /** @var Nette\Security\User */
  private $user;

  /** @var SisGroupBindings */
  private $bindings;

  public function __construct() {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(Nette\Security\User::class);
    $this->bindings = $container->getByType(SisGroupBindings::class);
  }

  protected function setUp() {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, SisPresenter::class);
    $this->httpHandler = new MockHandler();
    $this->presenter->sisHelper = new SisHelper(
      'https://api.sis.tld/rest.php',
      'faculty',
      'secret',
      HandlerStack::create($this->httpHandler)
    );
  }

  public function testGetSupervisedCourses() {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    /** @var User $user */
    $user = $this->user->getIdentity()->getUserData();
    $login = new ExternalLogin($user, "cas-uk", "12345678");
    $this->em->persist($login);
    $this->em->flush();

    $this->httpHandler->append(
      new Response(200, [], file_get_contents(self::DATA_DIR . '/teacher_simple.json'))
    );

    /** @var JsonResponse $response */
    $response = $this->presenter->run(new Request('V1:Sis', 'GET', [
      'action' => 'supervisedCourses',
      'userId' => $user->getId(),
      'year' => 2016,
      'term' => 2
    ]));
    Assert::type(JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::same(200, $result['code']);

    $payload = $result['payload'];
    Assert::count(2, $payload);
  }

  public function testCreateGroup() {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    /** @var User $user */
    $user = $this->user->getIdentity()->getUserData();
    $login = new ExternalLogin($user, "cas-uk", "12345678");
    $this->em->persist($login);
    $this->em->flush();

    $this->httpHandler->append(
      new Response(200, [], file_get_contents(self::DATA_DIR . '/teacher_simple.json'))
    );

    $courseId = '16bNSWI153x01';

    /** @var JsonResponse $response */
    $response = $this->presenter->run(new Request('V1:Sis', 'POST', [
      'action' => 'createGroup',
      'courseId' => $courseId
    ], [
      'instanceId' => $user->getInstance()->getId(),
      'language' => 'en'
    ]));
    Assert::type(JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::same(200, $result['code']);

    /** @var Group $group */
    $group = $result['payload'];

    Assert::type(Group::class, $group);
    Assert::same("Advanced Technologies for Web Applications (Wed, 15:40, Even weeks)", $group->getName());
    Assert::same("NSWI153", $group->getExternalId());
    Assert::same("The Lorem ipsum", $group->getDescription());

    Assert::notSame(NULL, $this->bindings->findByGroupAndCode($group, $courseId));
  }
}

(new TestSisPresenter())->run();

