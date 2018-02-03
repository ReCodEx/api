<?php

use App\Model\Entity\User;
use Nette\DI\Container;
use Nette\Utils\FileSystem;
use Kdyby\Doctrine\EntityManager;
use Kdyby\Doctrine\Configuration;
use Doctrine\Common\EventManager;
use Nette\Utils\Json;
use Nette\Utils\Random;
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

  public static function replaceService(Container $container, $service) {
    $emServiceName = $container->findByType(get_class($service))[0];
    $container->removeService($emServiceName);
    $container->addService($emServiceName, $service);
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
    $presenter->autoCanonicalize = FALSE;
    if ($presenter instanceof App\V1Module\Presenters\BasePresenter) {
      $presenter->responseDecorator = null;
    }

    return $presenter;
  }

  public static function login(Container $container, string $login): string
  {
    /** @var \Nette\Security\User $userSession */
    $userSession = $container->getByType(\Nette\Security\User::class);
    $user = $container->getByType(\App\Model\Repository\Users::class)->getByEmail($login);

    /** @var \App\Security\AccessManager $accessManager */
    $accessManager = $container->getByType(\App\Security\AccessManager::class);
    $tokenText = $accessManager->issueToken($user);
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
}
