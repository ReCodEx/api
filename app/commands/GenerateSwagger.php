<?php
namespace App\Console;

use App\V1Module\Router\MethodRoute;
use Faker;
use JsonSerializable;
use Nette\Application\IPresenterFactory;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;
use Nette\Application\UI\Presenter;
use Nette\Reflection\ClassType;
use Nette\Reflection\Method;
use Nette\Utils\ArrayHash;
use Nette\Utils\Arrays;
use Nette\Utils\Finder;
use Nette\Utils\Strings;
use ReflectionClass;
use ReflectionException;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Nelmio\Alice\Fixtures;

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

  /**
   * @var Fixtures\Loader
   */
  private $fixtureLoader;

  public function __construct(RouteList $router, IPresenterFactory $presenterFactory, Fixtures\Loader $loader)
  {
    parent::__construct();
    $this->router = $router;
    $this->presenterFactory = $presenterFactory;
    $this->fixtureLoader = $loader;
  }

  protected function configure()
  {
    $this->setName("swagger:generate")->setDescription("Generate a swagger specification file from existing code");
    $this->addArgument("source", InputArgument::OPTIONAL, "A YAML Swagger file to use as a template for the generated file", NULL);
    $this->addOption("save", NULL, InputOption::VALUE_NONE, "Save the output back to the source file");
  }

  protected function setArrayDefault(&$array, $key, $default)
  {
    if (!array_key_exists($key, $array)) {
      $array[$key] = $default;
      return TRUE;
    }

    return FALSE;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $apiRoutes = $this->findAPIRouteList();

    if (!$apiRoutes) {
      $output->writeln("<error>No suitable routes found</error>");
      return;
    }

    $source = $input->getArgument("source");
    $save = $input->getOption("save");

    if ($save && $source === NULL) {
      $output->writeln("<error>--save cannot be used without a source file</error>");
      return;
    }

    $document = $source ? Yaml::parse(file_get_contents($source)) : [];
    $basePath = ltrim(Arrays::get($document, "basePath", "/v1"), "/");

    $this->setArrayDefault($document, "paths", []);
    $paths = &$document["paths"];

    $this->setArrayDefault($document, "tags", []);
    $tags = &$document["tags"];

    foreach ($apiRoutes as $routeData) {
      $route = $routeData["route"];
      $parentRoute = $routeData["parent"];

      $method = self::getPropertyValue($route, "method");
      $actualRoute = self::getPropertyValue($route, "route");

      $metadata = self::getPropertyValue($actualRoute, "metadata");
      $mask = self::getPropertyValue($actualRoute, "mask");

      if (!Strings::startsWith($mask, $basePath)) {
        continue;
      }

      $mask = substr(str_replace(["<", ">"], ["{", "}"], $mask), strlen($basePath));

      $this->setArrayDefault($paths, $mask, []);
      $this->setArrayDefault($paths[$mask], strtolower($method), []);

      $module = self::getPropertyValue($parentRoute, "module");
      $this->fillPathEntry($metadata, $paths[$mask][strtolower($method)], $module);
      $this->makePresenterTag($metadata, $module, $tags, $paths[$mask][strtolower($method)]);
    }

    $this->setArrayDefault($document, "definitions", []);
    $this->fillEntityExamples($document["definitions"]);

    $yaml = Yaml::dump($document, 10, 2);
    $yaml = Strings::replace($yaml, '/(?<=parameters:)\s*\{\s*\}/', " [ ]"); // :-!
    $yaml = Strings::replace($yaml, '/(?<=tags:)\s*\{\s*\}/', " [ ]"); // :-!
    $yaml = Strings::replace($yaml, '/(?<=items:)\s*\{\s*\}/', " [ ]"); // :-!
    $output->write($yaml);

    if ($save) {
      file_put_contents($source, $yaml);
    }
  }

  private function fillPathEntry(array $metadata, array &$entry, $module)
  {
    $presenterName = $module . $metadata[Route::PRESENTER_KEY]["value"];
    $action = $metadata["action"]["value"] ?: "default";

    /** @var Presenter $presenter */
    $presenter = $this->presenterFactory->createPresenter($presenterName);
    $methodName = $presenter->formatActionMethod($action);

    try {
      $method = Method::from(get_class($presenter), $methodName);
    } catch (ReflectionException $exception) {
      return NULL;
    }

    $annotations = $method->getAnnotations();

    $entry["description"] = $method->getDescription() ?: "";
    $this->setArrayDefault($entry, "parameters", []);
    $this->setArrayDefault($entry, "responses", []);

    foreach (Arrays::get($annotations, "Param", []) as $annotation) {
      if ($annotation instanceof ArrayHash) {
        $annotation = get_object_vars($annotation);
      }

      if (isset($paramEntry)) {
        unset($paramEntry);
      }

      $paramEntryFound = FALSE;

      foreach ($entry["parameters"] as $i => $parameter) {
        if ($parameter["name"] === $annotation["name"]) {
          $paramEntry = &$entry["parameters"][$i];
          $paramEntryFound = TRUE;
          break;
        }
      }

      if (!$paramEntryFound) {
        $entry["parameters"][] = [
          "name" => $annotation["name"]
        ];

        $paramEntry = &$entry["parameters"][count($entry["parameters"]) - 1];
      }

      $paramEntry["required"] = Arrays::get($annotation, "required", FALSE);
      $this->setArrayDefault($paramEntry, "type", $annotation["validation"]); // TODO translate to Swagger types so that we can override values from the file

      if ($annotation["type"] === "post") {
        $paramEntry["in"] = "formData";
      } else if ($annotation["type"] === "query") {
        $paramEntry["in"] = "query";
      }

      $paramEntry["description"] = Arrays::get($annotation, "description", "");
    }

    foreach ($method->getParameters() as $methodParameter) {
      if (isset($paramEntry)) {
        unset($paramEntry);
      }

      $paramEntryFound = FALSE;

      foreach ($entry["parameters"] as $i => $parameter) {
        if ($parameter["name"] === $methodParameter->getName()) {
          $paramEntry = &$entry["parameters"][$i];
          $paramEntryFound = TRUE;
          break;
        }
      }

      if (!$paramEntryFound) {
        $entry["parameters"][] = [
          "name" => $methodParameter->getName()
        ];

        $paramEntry = &$entry["parameters"][count($entry["parameters"]) - 1];
      }

      $paramEntry["required"] = !$methodParameter->isOptional();
      $paramEntry["in"] = "path";
      $this->setArrayDefault($paramEntry, "type", "string");
    }

    $this->setArrayDefault($entry["responses"], "200", []);

    $isAuthFailurePossible = $presenter->getReflection()->getAnnotation("LoggedIn")
      || $method->getAnnotation("LoggedIn")
      || $method->getAnnotation("UserIsAllowed");

    if ($isAuthFailurePossible) {
      $this->setArrayDefault($entry["responses"], "403", []);
    }

    return $entry;
  }

  private function findAPIRouteList()
  {
    $queue = [$this->router];

    while (count($queue) != 0) {
      $cursor = array_shift($queue);

      if ($cursor instanceof RouteList) {
        foreach ($cursor as $item) {
          if ($item instanceof MethodRoute) {
            yield [
              "parent" => $cursor,
              "route" => $item
            ];
          }

          if ($item instanceof RouteList) {
            array_push($queue, $item);
          }
        }
      }
    }

    return NULL;
  }

  private static function getPropertyValue($object, $propertyName)
  {
    $class = new ReflectionClass($object);

    do {
      try {
        $property = $class->getProperty($propertyName);
      } catch (ReflectionException $exception) {
        $class = $class->getParentClass();
        $property = NULL;
      }
    } while ($property === NULL && $class !== NULL);

    $property->setAccessible(TRUE);
    return $property->getValue($object);
  }

  private function fillEntityExamples(array &$target)
  {
    // Load fixtures from the "base" and "demo" groups
    $fixtureDir = __DIR__ . "/../../fixtures";

    $finder = Finder::findFiles("*.neon", "*.yaml", ".yml")
      ->in($fixtureDir . "/base", $fixtureDir . "/demo");

    $files = [];

    /** @var SplFileInfo $file */
    foreach ($finder as $file) {
      $files[] = $file->getRealPath();
    }

    sort($files);
    $entities = [];

    foreach ($files as $file) {
      $entities = array_merge($this->fixtureLoader->load($file), $entities);
    }

    // Traverse all entities and fill in identifiers first so that we don't try to serialize an entity without an ID
    $faker = Faker\Factory::create();

    foreach ($entities as $entity) {
      $entity->id = $faker->unique()->uuid;
    }

    // Dump serializable entities into the document
    foreach ($entities as $entity) {
      if ($entity instanceof JsonSerializable) {
        $class = ClassType::from($entity);
        $this->updateEntityEntry($target, $class->getShortName(), $entity->jsonSerialize());
      }
    }
  }

  private function updateEntityEntry(array &$entry, $key, $value)
  {
    $type = is_array($value)
      ? (Arrays::isList($value) ? "array" : "object")
      : gettype($value);

    $this->setArrayDefault($entry, $key, []);

    // If a property value is a reference, just skip it
    if (count($entry[$key]) == 1 && array_key_exists('$ref', $entry[$key])) {
      return;
    }

    if ($type === "object") {
      $entry[$key]["type"] = "object";
      $this->setArrayDefault($entry[$key], "properties", []);

      foreach ($value as $objectKey => $objectValue) {
        $this->updateEntityEntry($entry[$key]["properties"], $objectKey, $objectValue);
      }
    } else if ($type === "array") {
      $entry[$key]["type"] = "array";
      $this->setArrayDefault($entry[$key], "items", []);

      if (count($value) > 0) {
        $this->updateEntityEntry($entry[$key], "items", $value[0]);
      }
    } else {
      $this->setArrayDefault($entry[$key], "type", $type);
      $entry[$key]["example"] = $value;
    }
  }

  private function makePresenterTag($metadata, $module, array &$tags, array &$entry)
  {
    $presenterName = $metadata[Route::PRESENTER_KEY]["value"];
    $fullPresenterName = $module . $presenterName;

    /** @var Presenter $presenter */
    $presenter = $this->presenterFactory->createPresenter($fullPresenterName);

    $tag = strtolower(Strings::replace($presenterName, '/(?!^)([A-Z])/', '-\1'));
    $tagEntry = [];
    $tagEntryFound = FALSE;

    foreach ($tags as $i => $tagEntry) {
      if ($tagEntry["name"] === $tag) {
        $tagEntryFound = TRUE;
        $tagEntry = &$tags[$i];
        break;
      }
    }

    if (!$tagEntryFound) {
      $tags[] = [
        "name" => $tag
      ];

      $tagEntry = &$tags[count($tags) - 1];
    }

    $tagEntry["description"] = (new ClassType($presenter))->getDescription() ?: "";

    $this->setArrayDefault($entry, "tags", []);
    $entry["tags"][] = $tag;
    $entry["tags"] = array_unique($entry["tags"]);
  }
}