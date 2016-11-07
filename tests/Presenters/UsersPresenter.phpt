<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\UsersPresenter;
use Tester\Assert;

/**
 * @httpCode any
 */
class TestUsersPresenter extends Tester\TestCase
{
  /** @var UsersPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var App\Model\Repository\Instances */
  protected $instances;

  /** @var App\Model\Repository\Logins */
  protected $logins;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var Nette\Security\User */
  private $user;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->instances = $container->getByType(\App\Model\Repository\Instances::class);
    $this->logins = $container->getByType(\App\Model\Repository\Logins::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, UsersPresenter::class);
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testGetAllUsers()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    PresenterTestHelper::setToken($this->presenter, $token);

    $request = new Nette\Application\Request('V1:Users', 'GET', ['action' => 'default']);
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::true(count($result['payload']) > 0);

    $users = array_pop($result['payload']);
    foreach ($users as $user) {
      Assert::same(App\Model\Entity\User::class, get_class($user));
    }
  }

  public function testCreateUser()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    PresenterTestHelper::setToken($this->presenter, $token);

    $email = "email@email.email";
    $firstName = "firstName";
    $lastName = "lastName";
    $password = "password";
    $instances = $this->instances->findAll();
    $instanceId = array_pop($instances)->getId();
    $degreesBeforeName = "degreesBeforeName";
    $degreesAfterName = "degreesAfterName";

    $request = new Nette\Application\Request('V1:Users', 'POST',
            ['action' => 'createAccount'],
            [
                'email' => $email,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'password' => $password,
                'instanceId' => $instanceId,
                'degreesBeforeName' => $degreesBeforeName,
                'degreesAfterName' => $degreesAfterName
            ]
    );
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(201, $result['code']);
    Assert::equal(2, count($result['payload']));
    Assert::true(array_key_exists("accessToken", $result["payload"]));
    Assert::true(array_key_exists("user", $result["payload"]));

    // check created user
    $user = $result["payload"]["user"];
    Assert::same(\App\Model\Entity\User::class, get_class($user));
    Assert::equal($email, $user->getEmail());
    Assert::equal($firstName, $user->getFirstName());
    Assert::equal($lastName, $user->getLastName());
    Assert::equal($instanceId, $user->getInstance()->getId());
    Assert::equal($degreesBeforeName, $user->getDegreesBeforeName());
    Assert::equal($degreesAfterName, $user->getDegreesAfterName());

    // check created login
    $login = $this->logins->findByUserId($user->getId());
    Assert::true($login->passwordsMatch($password));
  }

  public function testCreateAccountExt()
  {
    // TODO
  }

  public function testValidateRegistrationData()
  {
    // TODO
  }

  public function testDetail()
  {
    // TODO
  }

  public function testUpdateProfile()
  {
    // TODO
  }

  public function testGroups()
  {
    // TODO
  }

  public function testInstances()
  {
    // TODO
  }

  public function testExercises()
  {
    // TODO
  }

}

$testCase = new TestUsersPresenter();
$testCase->run();