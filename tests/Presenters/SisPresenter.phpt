<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\SisHelper;
use App\Model\Entity\ExternalLogin;
use App\Model\Entity\Group;
use App\Model\Entity\SisGroupBinding;
use App\Model\Entity\SisValidTerm;
use App\Model\Entity\User;
use App\Model\Repository\Groups;
use App\Model\Repository\SisGroupBindings;
use App\Model\Repository\SisValidTerms;
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

  /** @var SisValidTerms */
  private $terms;

  /** @var Groups */
  private $groups;

  public function __construct() {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(Nette\Security\User::class);
    $this->bindings = $container->getByType(SisGroupBindings::class);
    $this->terms = $container->getByType(SisValidTerms::class);
    $this->groups = $container->getByType(Groups::class);
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

  public function testStatus() {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    /** @var User $user */
    $user = $this->user->getIdentity()->getUserData();
    $login = new ExternalLogin($user, "cas-uk", "12345678");
    $this->em->persist($login);

    $term_1 = new SisValidTerm(2016, 1);
    $term_2 = new SisValidTerm(2016, 2);

    $this->em->persist($term_1);
    $this->em->persist($term_2);
    $this->em->flush();

    /** @var JsonResponse $response */
    $response = $this->presenter->run(new Request('V1:Sis', 'GET', [
      'action' => 'status'
    ]));

    $result = $response->getPayload();

    Assert::type(JsonResponse::class, $response);
    Assert::same(200, $result['code']);
    $payload = $result['payload'];

    Assert::true($payload['accessible']);
    Assert::count(2, $payload['terms']);
  }

  public function testStatusNoCasAccount() {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    /** @var User $user */
    $user = $this->user->getIdentity()->getUserData();

    /** @var JsonResponse $response */
    $response = $this->presenter->run(new Request('V1:Sis', 'GET', [
      'action' => 'status'
    ]));

    $result = $response->getPayload();

    Assert::type(JsonResponse::class, $response);
    Assert::same(200, $result['code']);
    $payload = $result['payload'];

    Assert::false($payload['accessible']);
  }

  public function testStudentGroups() {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    /** @var User $user */
    $user = $this->user->getIdentity()->getUserData();
    $login = new ExternalLogin($user, "cas-uk", "12345678");
    $this->em->persist($login);

    $term_1 = new SisValidTerm(2016, 1);
    $term_2 = new SisValidTerm(2016, 2);

    $this->em->persist($term_1);
    $this->em->persist($term_2);

    $groups = $this->groups->findAll();
    $group_1 = $groups[0];
    $binding_1 = new SisGroupBinding($group_1, "16bNPRG042p1");
    $this->em->persist($binding_1);

    $group_2 = $groups[1];
    $binding_2 = new SisGroupBinding($group_2, "16bNSWI153x01");
    $this->em->persist($binding_2);

    $this->em->flush();

    $this->httpHandler->append(
      new Response(200, [], file_get_contents(self::DATA_DIR . '/student_simple.json'))
    );

    /** @var JsonResponse $response */
    $response = $this->presenter->run(new Request('V1:Sis', 'GET', [
      'action' => 'subscribedGroups',
      'userId' => $user->getId(),
      'year' => 3054,
      'term' => 1
    ]));

    $result = $response->getPayload();
    $payload = $result['payload'];

    Assert::type(JsonResponse::class, $response);
    Assert::same(200, $result['code']);

    $expected = [$group_2, $group_1];
    sort($expected);
    sort($payload);

    Assert::equal($expected, $payload);
  }
}

(new TestSisPresenter())->run();

