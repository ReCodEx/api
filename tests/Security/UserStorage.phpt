<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidAccessTokenException;
use App\Model\Entity\User;
use App\Model\Repository\Users;
use App\Security\AccessManager;
use App\Security\AccessToken;
use App\Security\UserStorage;
use Nette\DI\Container;
use Nette\Http;
use Tester\Assert;


/**
 * @testCase
 */
class TestUserStorage extends Tester\TestCase {
  use MockeryTrait;

  /** @var Container */
  private $container;

  /** @var AccessManager */
  private $accessManager;

  public function __construct(Container $container) {
    $this->container = $container;
  }

  protected function setUp() {
    PresenterTestHelper::fillDatabase($this->container);
    $this->accessManager = $this->container->getByType(AccessManager::class);
  }

  protected function createUserStorage(AccessToken $token) {
    $verificationKey = $this->container->parameters["accessManager"]["verificationKey"];
    $usedAlgorithm = $this->container->parameters["accessManager"]["usedAlgorithm"];
    $httpRequest = new Http\Request(new Http\UrlScript("/hello"), null, null, null, null,
      ["Authorization" => sprintf("Bearer %s", $token->encode($verificationKey, $usedAlgorithm))]);

    return new UserStorage($this->accessManager, $httpRequest);
  }

  protected function getUser(): User {
    return $this->container->getByType(Users::class)->findAll()[0];
  }

  protected function createToken($payload = []): AccessToken {
    return new AccessToken((object) array_merge([
      "sub" => $this->getUser()->getId()
    ], $payload));
  }

  public function testGetIdentity() {
    $userStorage = $this->createUserStorage($this->createToken());
    $identity = $userStorage->getIdentity();

    Assert::same($this->getUser()->getId(), $identity->getId());
  }

  public function testGetIdentityExpired() {
    $userStorage = $this->createUserStorage($this->createToken([
      "exp" => time() - 3600
    ]));

    Assert::exception(function () use ($userStorage) {
      $userStorage->getIdentity();
    }, InvalidAccessTokenException::class);
  }

  public function testGetIdentityInvalidated() {
    $this->getUser()->setTokenValidityThreshold(new DateTime());
    $userStorage = $this->createUserStorage($this->createToken([
      "iat" => time() - 3600
    ]));

    Assert::exception(function () use ($userStorage) {
      $userStorage->getIdentity();
    }, ForbiddenRequestException::class);
  }
}

(new TestUserStorage($container))->run();
