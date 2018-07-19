<?php

use App\Model\Entity\User;
use App\Security\AccessToken;
use App\Security\TokenScope;
use Nette\Application\IResponse;
use Nette\Application\Responses\JsonResponse;
use Nette\DI\Container;
use Nette\Utils\FileSystem;
use Kdyby\Doctrine\EntityManager;
use Kdyby\Doctrine\Configuration;
use Doctrine\Common\EventManager;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Symfony\Component\Process\Process;

class PresenterTestHelper
{
  const ADMIN_LOGIN = "admin@admin.com";
  const ADMIN_PASSWORD = "admin";

  const STUDENT_GROUP_MEMBER_LOGIN = "demoGroupMember1@example.com";
  const STUDENT_GROUP_MEMBER_PASSWORD = "";

  private static function createEntityManager(string $dbPath, Configuration $configuration, EventManager $eventManager): EntityManager {
    return EntityManager::create(
      ["driver" => "pdo_sqlite", "path" => $dbPath],
      $configuration,
      $eventManager
    );
  }

  public static function replaceService(Container $container, $service, $type = null) {
    $type = $type ?? get_class($service);
    $emServiceName = $container->findByType($type)[0];
    $container->removeService($emServiceName);
    $container->addService($emServiceName, $service);
  }

  /**
   * @throws Tester\AssertException
   * @throws JsonException
   */
  public static function extractPayload(IResponse $response, $jsonify = true) {
    Tester\Assert::type(JsonResponse::class, $response);

    /** @var JsonResponse $response */
    Tester\Assert::same(200, $response->getPayload()["code"]);
    $payload = $response->getPayload()["payload"];

    if ($jsonify) {
      return Json::decode(Json::encode($payload), Json::FORCE_ARRAY);
    }

    return $payload;
  }

  public static function getEntityManager(Container $container): EntityManager {
    /** @var EntityManager $em */
    $em = $container->getByType(EntityManager::class);
    return $em;
  }

  public static function fillDatabase(Container $container, string $group = "demo")
  {
    $tmpDir = $container->getParameters()["tempDir"] . DIRECTORY_SEPARATOR . "testDB";
    if (is_dir("/tmp")) { // Creating a sqlite db in tmpfs is much faster than on a regular file system
      $tmpDir = "/tmp/ReCodEx" . DIRECTORY_SEPARATOR . "testDB";
    }

    FileSystem::createDir($tmpDir);

    $dbPath = $tmpDir . DIRECTORY_SEPARATOR . "database_" . $group . ".db";
    $dumpPath = $tmpDir . DIRECTORY_SEPARATOR . "database_" . $group . ".sql";
    $originalEm = static::getEntityManager($container);

    $lockHandle = fopen($dbPath . ".lock", "c+");
    flock($lockHandle, LOCK_EX);

    if (!is_file($dbPath) || !is_file($dumpPath) || filesize($dumpPath) === 0) {
      // Create a new entity manager connected to a temporary sqlite database
      $schemaEm = static::createEntityManager($dbPath, $originalEm->getConfiguration(),
        $originalEm->getEventManager());
      static::replaceService($container, $schemaEm);

      $schemaTool = new Doctrine\ORM\Tools\SchemaTool($schemaEm);
      $schemaTool->dropSchema($schemaEm->getMetadataFactory()->getAllMetadata());
      $schemaTool->createSchema($schemaEm->getMetadataFactory()->getAllMetadata());

      $command = $container->getByType(App\Console\DoctrineFixtures::class);

      $input = new Symfony\Component\Console\Input\ArgvInput(["index.php", "-test", "base", $group]);
      $output = new Symfony\Component\Console\Output\NullOutput();

      $command->run($input, $output);
      $originalEm->flush();
      $originalEm->clear();

      $sqliteProcess = new Process("sqlite3 --bail $dbPath");
      $sqliteProcess->setInput(".dump");
      $rc = $sqliteProcess->run();

      if ($rc !== 0) {
        throw new RuntimeException('Could not run sqlite export. Make sure "sqlite3" is installed and accessible through $PATH.');
      }

      file_put_contents($dumpPath, $sqliteProcess->getOutput());

      // Replace the temporary entity manager with the original one
      static::replaceService($container, $originalEm);
    }

    flock($lockHandle, LOCK_UN);
    $originalEm->getConnection()->exec(file_get_contents($dumpPath));
    $originalEm->clear();
  }

  public static function createPresenter(Nette\DI\Container $container, string $class): Nette\Application\UI\Presenter
  {
    $names = $container->findByType($class);
    $name = reset($names);

    /** @var $presenter Nette\Application\UI\Presenter */
    $presenter = $container->createService($name);
    $presenter->autoCanonicalize = false;

    return $presenter;
  }

  public static function login(Container $container, string $login): string
  {
    /** @var \Nette\Security\User $userSession */
    $userSession = $container->getByType(\Nette\Security\User::class);
    $user = $container->getByType(\App\Model\Repository\Users::class)->getByEmail($login);

    /** @var \App\Security\AccessManager $accessManager */
    $accessManager = $container->getByType(\App\Security\AccessManager::class);
    $tokenText = $accessManager->issueToken($user, [TokenScope::MASTER, TokenScope::REFRESH]);
    $token = $accessManager->decodeToken($tokenText);

    $userSession->login(new \App\Security\Identity($user, $token));
    return $tokenText;
  }

  public static function loginDefaultAdmin(Container $container): string {
    return self::login($container, self::ADMIN_LOGIN);
  }

  public static function getUser(Container $container, $login = null): User {
    $login = $login ?? self::ADMIN_LOGIN;
    return $container->getByType(\App\Model\Repository\Users::class)->getByEmail($login);
  }

  public static function jsonResponse($payload) {
    return Json::decode(Json::encode($payload), Json::FORCE_ARRAY);
  }

  /**
   * Perform regular presenter request and make common asserts.
   * @param $presenter The presenter which should handle the request.
   * @param $module String representing the module path (e.g., 'V1:Exercises').
   * @param $method HTTP method of the request (GET, POST, ...).
   * @param $params Parameters of the request.
   * @param $expectedCode Expected HTTP response code (200 by default).
   * @return array|null Payload subtree of JSON request.
   * @throws Tester\AssertException
   * @throws JsonException
   */
  public static function preformPresenterRequest($presenter, string $module, string $method = 'GET', array $params = [], $expectedCode = 200)
  {
    $request = new \Nette\Application\Request($module, $method, $params);
    $response = $presenter->run($request);
    Tester\Assert::type(\Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Tester\Assert::equal($expectedCode, $result['code']);
    return array_key_exists('payload', $result) ? $result['payload'] : null;
  }

}
