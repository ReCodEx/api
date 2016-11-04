<?php

use Nette\Utils\Arrays;

class PresenterTestHelper
{
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
    /** @var $presenter Nette\Application\UI\Presenter */
    $presenter = $container->getByType($class);
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