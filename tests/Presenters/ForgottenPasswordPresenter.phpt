<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\ForgottenPasswordPresenter;
use Tester\Assert;

class TestForgottenPasswordPresenter extends Tester\TestCase
{
  private $userLogin = "user2@example.com";
  private $userPassword = "password2";
  use MockeryTrait;

  /** @var ForgottenPasswordPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var Nette\Security\User */
  private $user;

  private $originalHttpRequest;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->originalHttpRequest = $this->container->getService("http.request");
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);

    $this->container->removeService("http.request");
    $mockRequest = Mockery::mock($this->originalHttpRequest)  // create proxied partial mock
        ->shouldDeferMissing()
        ->shouldReceive('getPost')->with('username')->andReturn($this->userLogin)->zeroOrMoreTimes()
        ->shouldReceive('getPost')->with('password')->andReturn($this->userPassword)->zeroOrMoreTimes()
        ->shouldReceive('getRemoteAddress')->withNoArgs()->andReturn('localhost')->zeroOrMoreTimes()
        ->getMock();
    $this->container->addService("http.request", $mockRequest);

    $this->presenter = PresenterTestHelper::createPresenter($this->container, ForgottenPasswordPresenter::class);
    $this->presenter->forgottenPasswordHelper = Mockery::mock('App\Helpers\ForgottenPasswordHelper')
        ->shouldReceive('process')
        ->with(Mockery::any(), 'localhost')  // we won't fetch here the Login class
        ->andReturn("")
        ->zeroOrMoreTimes()
        ->getMock();
  }

  protected function tearDown()
  {
    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
    Mockery::close();
  }

  public function testResetRequest()
  {
    $request = new Nette\Application\Request('V1:ForgottenPassword', 'POST', ['action' => 'default'], ['username' => $this->userLogin]);
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal("OK", $result['payload']);
  }

  public function testPasswordStrength()
  {
    $request = new Nette\Application\Request('V1:ForgottenPassword', 'POST', ['action' => 'validatePasswordStrength'], ['password' => $this->userPassword]);
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));
    
    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::type("int", $result['payload']['passwordScore']);
  }
}

$testCase = new TestForgottenPasswordPresenter();
$testCase->run();
