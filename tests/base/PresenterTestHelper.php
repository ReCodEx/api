<?php

use App\Model\Entity\User;
use Nette\DI\Container;

class PresenterTestHelper
{
  const ADMIN_LOGIN = "admin@admin.com";
  const ADMIN_PASSWORD = "admin";

  const STUDENT_GROUP_MEMBER_LOGIN = "demoGroupMember1@example.com";
  const STUDENT_GROUP_MEMBER_PASSWORD = "";

  public static function prepareDatabase(Container $container): Kdyby\Doctrine\EntityManager
  {
    $em = $container->getByType(Kdyby\Doctrine\EntityManager::class);

    $schemaTool = new Doctrine\ORM\Tools\SchemaTool($em);
    $schemaTool->dropDatabase();
    $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

    return $em;
  }

  public static function fillDatabase(Container $container, string $group = "demo")
  {
    $command = $container->getByType(App\Console\DoctrineFixtures::class);

    $input = new Symfony\Component\Console\Input\ArgvInput(["index.php", "-test", "base", $group]);
    $output = new Symfony\Component\Console\Output\NullOutput();

    $command->run($input, $output);

    // destroy EntityManager to safely save all work and start with new one on demand
    $container->getByType(Kdyby\Doctrine\EntityManager::class)->clear();

    return;
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

  public static function getUser(Container $container, $login = NULL): User {
    $login = $login ?? self::ADMIN_LOGIN;
    return $container->getByType(\App\Model\Repository\Users::class)->getByEmail($login);
  }
}
