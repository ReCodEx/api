<?php
namespace App\Console;

use App\V1Module\Router\MethodRoute;
use Nette\Application\IPresenterFactory;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;
use Nette\Application\UI\Presenter;
use Nette\Reflection\Method;
use Nette\Utils\Arrays;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class GenerateSwagger extends Command
{
  /**
   * @var RouteList
   */
  private $router;

  /**
   * @var IPresenterFactory
   */
  private $presenterFactory;

  public function __construct(RouteList $router, IPresenterFactory $presenterFactory)
  {
    parent::__construct();
    $this->router = $router;
    $this->presenterFactory = $presenterFactory;
  }

  protected function configure()
  {
    $this->setName("swagger:generate")->setDescription("Generate a swagger specification file from existing code");
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $apiRoutes = $this->findAPIRouteList();

    if (!$apiRoutes) {
      $output->writeln("<error>No suitable router found</error>");
      return;
    }

    $document = [];
    $paths = [];

    foreach ($apiRoutes as $route) {
      if ($route instanceof MethodRoute) {
        $routeReflection = new ReflectionClass(MethodRoute::class);
        $method = self::getPropertyValue($routeReflection, $route, "method");
        $actualRoute = self::getPropertyValue($routeReflection, $route, "route");

        $actualRouteReflection = new ReflectionClass($actualRoute);
        $metadata = self::getPropertyValue($actualRouteReflection, $actualRoute, "metadata");
        $mask = self::getPropertyValue($actualRouteReflection, $actualRoute, "mask");
        $mask = str_replace(["<", ">"], ["{", "}"], $mask);

        if (!array_key_exists($mask, $paths)) {
          $paths[$mask] = [];
        }

        $entry = $this->makePathEntry($metadata);

        if ($entry === null) {
          continue;
        }

        $paths[$mask][strtolower($method)] = $entry;
      }
    }

    $document["paths"] = $paths;

    $output->write(Yaml::dump($document));
  }

  private function makePathEntry(array $metadata)
  {
    $presenterName = "V1:" . $metadata[Route::PRESENTER_KEY]["value"]; # TODO get module name somewhere else
    $action = $metadata["action"]["value"] ?: "default";
    $entry = [];

    /** @var Presenter $presenter */
    $presenter = $this->presenterFactory->createPresenter($presenterName);
    $methodName = $presenter->formatActionMethod($action);

    try {
      $method = Method::from(get_class($presenter), $methodName);
    } catch (ReflectionException $exception) {
      return null;
    }

    $annotations = $method->getAnnotations();

    $entry["description"] = $method->getDescription();
    $entry["parameters"] = [];
    $entry["responses"] = [];

    foreach (Arrays::get($annotations, "Param", []) as $annotation) {
      $entry["parameters"] = [
        "name" => $annotation["name"]
      ];
    }

    return $entry;
  }

  private function findAPIRouteList()
  {
    $queue = [$this->router];

    while (count($queue) != 0) {
      $cursor = array_shift($queue);

      if ($cursor instanceof RouteList) {
        if (count($cursor) == 0) {
          continue;
        }

        foreach ($cursor as $item) {
          if ($item instanceof MethodRoute) {
            return $cursor;
          }

          if ($item instanceof RouteList) {
            array_push($queue, $item);
          }
        }
      }
    }

    return null;
  }

  private static function getPropertyValue(ReflectionClass $class, $object, $propertyName)
  {
    $property = $class->getProperty($propertyName);
    $property->setAccessible(TRUE);
    return $property->getValue($object);
  }
}