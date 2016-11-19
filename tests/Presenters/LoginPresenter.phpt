<?php
use App\Exceptions\WrongCredentialsException;
use App\Helpers\ExternalLogin\ExternalServiceAuthenticator;
use App\Security\AccessToken;
use App\Security\Identity;
use App\V1Module\Presenters\LoginPresenter;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Tester\Assert;

$container = require_once __DIR__ . "../bootstrap.php";

class TestLoginPresenter extends Tester\TestCase
{
  private $userLogin = "user2@example.com";
  private $userPassword = "password2";

  /** @var LoginPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

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
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, LoginPresenter::class);
  }

  protected function tearDown()
  {
    $this->user->logout(TRUE);
    Mockery::close();
  }

  public function testLogin()
  {
    $request = new Request("V1:Login", "POST", ["action" => "default"], [
      "username" => $this->userLogin,
      "password" => $this->userPassword
    ]);

    /** @var JsonResponse $response */
    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
    $result = $response->getPayload();

    Assert::same(200, $result["code"]);
    Assert::true(array_key_exists("accessToken", $result["payload"]));
    Assert::same($this->presenter->users->getByEmail($this->userLogin), $result["payload"]["user"]);
    Assert::true($this->presenter->user->isLoggedIn());
  }

  public function testLoginIncorrect()
  {
    $request = new Request("V1:Login", "POST", ["action" => "default"], [
      "username" => $this->userLogin,
      "password" => $this->userPassword . "42"
    ]);

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, WrongCredentialsException::class);

    Assert::false($this->presenter->user->isLoggedIn());
  }

  public function testLoginExternal()
  {
    $user = $this->presenter->users->getByEmail($this->userLogin);
    $mockAuthenticator = Mockery::mock(ExternalServiceAuthenticator::class);
    $mockAuthenticator->shouldReceive("authenticate")
      ->with("foo", $this->userLogin, $this->userPassword)
      ->andReturn($user);

    $this->presenter->externalServiceAuthenticator = $mockAuthenticator;

    $request = new Request("V1:Login", "POST", ["action" => "external", "serviceId" => "foo"], [
      "username" => $this->userLogin,
      "password" => $this->userPassword
    ]);

    /** @var JsonResponse $response */
    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
    $result = $response->getPayload();

    Assert::same(200, $result["code"]);
    Assert::true(array_key_exists("accessToken", $result["payload"]));
    Assert::same($user, $result["payload"]["user"]);
    Assert::true($this->presenter->user->isLoggedIn());
  }

  public function testRefresh()
  {
    $user = $this->presenter->users->getByEmail($this->userLogin);
    $mockToken = Mockery::mock(AccessToken::class);
    $mockToken->shouldReceive("isInScope")
      ->with(AccessToken::SCOPE_REFRESH)
      ->once()
      ->andReturn(TRUE);
    $mockToken->shouldReceive("getUserId")
      ->withNoArgs()
      ->zeroOrMoreTimes()
      ->andReturn($user->id);

    $this->presenter->user->login(new Identity($user, $mockToken));

    $request = new Request("V1:Login", "POST", ["action" => "refresh"], []);

    /** @var JsonResponse $response */
    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
    $result = $response->getPayload();

    Assert::same(200, $result["code"]);
    Assert::true(array_key_exists("accessToken", $result["payload"]));
    Assert::same($user, $result["payload"]["user"]);
    Assert::true($this->presenter->user->isLoggedIn());

    $newToken = $this->presenter->accessManager->decodeToken($result["payload"]["accessToken"]);
    Assert::true($newToken->isInScope(AccessToken::SCOPE_REFRESH));
  }

  public function testRefreshInvalidToken()
  {
    $user = $this->presenter->users->getByEmail($this->userLogin);

    $mockToken = Mockery::mock(AccessToken::class);
    $mockToken->shouldReceive("isInScope")
      ->with(AccessToken::SCOPE_REFRESH)
      ->once()
      ->andReturn(FALSE);
    $mockToken->shouldReceive("getUserId")
      ->withNoArgs()
      ->zeroOrMoreTimes()
      ->andReturn($user->id);

    $this->presenter->user->login(new Identity($user, $mockToken));
    $request = new Request("V1:Login", "POST", ["action" => "refresh"], []);

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, \App\Exceptions\ForbiddenRequestException::class);
  }
}

$testCase = new TestLoginPresenter();
$testCase->run();
