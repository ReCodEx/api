<?php

use Nette\Utils\Arrays;

class PresenterTestHelper
{
  const ADMIN_LOGIN = "admin@admin.com";
  const ADMIN_PASSWORD = "admin";

  public static function prepareDatabase(\Nette\DI\Container $container): Kdyby\Doctrine\EntityManager
  {
    $em = $container->getByType(Kdyby\Doctrine\EntityManager::class);

    $schemaTool = new Doctrine\ORM\Tools\SchemaTool($em);
    $schemaTool->dropDatabase();
    $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

    return $em;
  }

  public static function fillDatabase(\Nette\DI\Container $container, string $group = "demo")
  {
    $command = $container->getByType(App\Console\DoctrineFixtures::class);

    $input = new Symfony\Component\Console\Input\ArgvInput(["index.php", "base", $group]);
    $output = new Symfony\Component\Console\Output\NullOutput();

    $command->run($input, $output);

    return;
  }

  public static function createPresenter(Nette\DI\Container $container, string $class): Nette\Application\UI\Presenter
  {
    $names = $container->findByType($class);
    $name = reset($names);

    /** @var $presenter Nette\Application\UI\Presenter */
    $presenter = $container->createService($name);
    $presenter->autoCanonicalize = FALSE;

    return $presenter;
  }

  public static function login(\Nette\DI\Container $container, string $login, string $password): string
  {
    $presenter = self::createPresenter($container, \App\V1Module\Presenters\LoginPresenter::class);
    $response = $presenter->run(new \Nette\Application\Request("V1:Login", "POST", ["action" => "default"], ["username" => $login, "password" => $password]));
    $payload = $response->getPayload();
    return Arrays::get($payload, ["payload", "accessToken"]);
  }

  public static function loginDefaultAdmin(\Nette\DI\Container $container): string {
    $presenter = self::createPresenter($container, \App\V1Module\Presenters\LoginPresenter::class);
    $response = $presenter->run(new \Nette\Application\Request("V1:Login", "POST", ["action" => "default"], ["username" => self::ADMIN_LOGIN, "password" => self::ADMIN_PASSWORD]));
    $payload = $response->getPayload();
    return Arrays::get($payload, ["payload", "accessToken"]);
  }

  public static function setToken(Nette\Application\UI\Presenter $presenter, $token)
  {
    $method = Nette\Reflection\ClassType::from($presenter)->getMethod("getHttpRequest");
    $method->setAccessible(TRUE);

    /** @var Nette\Http\Request $request */
    $request = $method->invoke($presenter);
    $headersRef = Nette\Reflection\ClassType::from($request)->getProperty("headers");
    $headersRef->setAccessible(TRUE);
    $headers = $headersRef->getValue($request);
    $headers["authorization"] = "Bearer ". $token;
    $headersRef->setValue($request, $headers);
  }
}