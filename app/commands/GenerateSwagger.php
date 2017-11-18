<?php
namespace App\Console;

use App\V1Module\Router\MethodRoute;
use Doctrine\ORM\Tools\SchemaTool;
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
use Nette\Utils\Json;
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
use Kdyby;

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

  /**
   * @var Kdyby\Doctrine\EntityManager
   */
  private $em;

  private $typeMap = [
    'bool' => 'boolean',
    'boolean' => 'boolean',
    'int' => 'integer',
    'integer' => 'integer',
    'float' => 'number',
    'number' => 'number',
    'numeric' => 'number',
    'numericint' => 'integer',
    'string' => 'string',
    'unicode' => ['string', 'unicode'],
    'email' => ['string', 'email'],
    'url' => ['string', 'url'],
    'uri' => ['string', 'uri'],
    'pattern' => NULL,
    'alnum' => ['string', 'alphanumeric'],
    'alpha' => ['string', 'alphabetic'],
    'digit' => ['string', 'numeric'],
    'lower' => ['string', 'lowercase'],
    'upper' => ['string', 'uppercase']
  ];

  public function __construct(RouteList $router, IPresenterFactory $presenterFactory, Fixtures\Loader $loader, Kdyby\Doctrine\EntityManager $em)
  {
    parent::__construct();
    $this->router = $router;
    $this->presenterFactory = $presenterFactory;
    $this->fixtureLoader = $loader;
    $this->em = $em;
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

    $defaultSecurity = NULL;
    $securityDefinitions = [];

    if (array_key_exists('securityDefinitions', $document)) {
      $securityDefinitions = array_keys($document['securityDefinitions']);

      if (count($securityDefinitions) > 0) {
        $defaultSecurity = $securityDefinitions[0];
      }
    }

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

      // TODO hack - we need a better way of getting module names from nested RouteList objects
      $module = "V1:" . self::getPropertyValue($parentRoute, "module");
      $this->fillPathEntry($metadata, $paths[$mask][strtolower($method)], $module, $defaultSecurity, function ($text) use ($output, $method, $mask) {
        $output->writeln("<error>Endpoint $method $mask: $text</error>");
      });
      $this->makePresenterTag($metadata, $module, $tags, $paths[$mask][strtolower($method)]);
    }

    $this->setArrayDefault($document, "definitions", []);
    $this->fillEntityExamples($document["definitions"]);

    $yaml = Yaml::dump($document, 10, 2);
    $yaml = Strings::replace($yaml, '/(?<=parameters:)\s*\{\s*\}/', " [ ]"); // :-!
    $yaml = Strings::replace($yaml, '/(?<=tags:)\s*\{\s*\}/', " [ ]"); // :-!

    foreach ($securityDefinitions as $definition) {
      $yaml = Strings::replace($yaml, '/(?<=' . $definition . ':)\s*\{\s*\}/', " [ ]"); // :-!
    }

    // $output->write($yaml);

    if ($save) {
      file_put_contents($source, $yaml);
    }
  }

  private function fillPathEntry(array $metadata, array &$entry, $module, $defaultSecurity = NULL, callable $warning = NULL)
  {
    if ($warning === NULL) {
      $warning = function ($text) {};
    }

    if (count($entry["tags"]) > 1) {
      $warning("Multiple tags");
    }

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

    $existingParams = [];

    foreach ($entry["parameters"] as $paramEntry) {
      $existingParams[$paramEntry["name"]] = false;
    }

    foreach (Arrays::get($annotations, "Param", []) as $annotation) {
      if ($annotation instanceof ArrayHash) {
        $annotation = get_object_vars($annotation);
      }

      $required = Arrays::get($annotation, "required", FALSE);
      $validation = Arrays::get($annotation, "validation", "");
      $in = $annotation["type"] === "post" ? "formData" : "query";
      $description = Arrays::get($annotation, "description", "");
      $this->fillParamEntry($entry, $annotation["name"], $in, $required, $validation, $description);

      $existingParams[$annotation["name"]] = true;
    }

    $parameterAnnotations = Arrays::get($annotations, "param", []);

    foreach ($method->getParameters() as $methodParameter) {
      $in = $methodParameter->isOptional() ? "query" : "path";
      $description = "";
      $validation = "string";
      $existingParams[$methodParameter->getName()] = true;

      foreach ($parameterAnnotations as $annotation) {
        $annotationParts = explode(" ", $annotation, 3);
        $firstPart = Arrays::get($annotationParts, 0, NULL);
        $secondPart = Arrays::get($annotationParts, 1, NULL);

        if ($secondPart === "$" . $methodParameter->getName()) {
          $validation = $firstPart;
        } else if ($firstPart === "$" . $methodParameter->getName()) {
          $validation = $secondPart;
        } else {
          continue;
        }

        $description = Arrays::get($annotationParts, 2, "");
      }

      $this->fillParamEntry($entry, $methodParameter->getName(), $in, !$methodParameter->isOptional(), $validation ?? "", $description);
    }

    foreach ($existingParams as $param => $exists) {
      if (!$exists) {
        $warning("Unknown parameter $param");
      }
    }

    $this->setArrayDefault($entry["responses"], "200", []);

    $isLoginNeeded = $presenter->getReflection()->getAnnotation("LoggedIn")
      || $method->getAnnotation("LoggedIn");

    if ($isLoginNeeded) {
      $this->setArrayDefault($entry["responses"], "401", []);

      if ($defaultSecurity !== NULL) {
        $this->setArrayDefault($entry, 'security', [[$defaultSecurity => []]]);
      }
    } else if (array_key_exists($entry["responses"], "401")) {
      $warning(sprintf("Method %s is not annotated with @LoggedIn, but corresponding endpoint has 401 in its response list", $method->name));
    }

    $isAuthFailurePossible = $method->getAnnotation("UserIsAllowed")
      || $presenter->getReflection()->getAnnotation("Role")
      || $method->getAnnotation("Role");

    if ($isAuthFailurePossible) {
      $this->setArrayDefault($entry["responses"], "403", []);
    } else if (array_key_exists($entry["responses"], "403")) {
      $warning(sprintf("Method %s is not annotated with @UserIsAllowed, but corresponding endpoint has 403 in its response list", $method->name));
    }

    return $entry;
  }

  /**
   * @param array $entry
   * @param $name
   * @param $in
   * @param $required
   * @param $validation
   * @param $description
   */
  private function fillParamEntry(array &$entry, $name, $in, $required, $validation, $description)
  {
    $paramEntryFound = FALSE;

    foreach ($entry["parameters"] as $i => $parameter) {
      if ($parameter["name"] === $name) {
        $paramEntry = &$entry["parameters"][$i];
        $paramEntryFound = TRUE;
        break;
      }
    }

    if (!$paramEntryFound) {
      $entry["parameters"][] = [
        "name" => $name
      ];

      $paramEntry = &$entry["parameters"][count($entry["parameters"]) - 1];
    }

    $paramEntry["in"] = $in;
    $paramEntry["required"] = $required;

    if ($in === "path") {
      $paramEntry["required"] = TRUE;
    } else if ($in === "query") {
      $this->setArrayDefault($paramEntry, "required", FALSE);
    }

    $paramEntry = array_merge($paramEntry, $this->translateType($validation));
    $paramEntry["description"] = $description;
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

  private function translateType(string $type): array
  {
    if (!$type) {
      return [];
    }

    $validation = NULL;

    if (Strings::contains($type, ':')) {
      list($type, $validation) = explode(':', $type);
    }

    $translation = Arrays::get($this->typeMap, $type, NULL);
    if (is_array($translation)) {
      $typeInfo = [
        'type' => $translation[0],
        'format' => $translation[1]
      ];
    } else if ($translation !== NULL) {
      $typeInfo = [
        'type' => $translation
      ];
    } else {
      return [];
    }

    if ($validation && Strings::contains($validation, '..')) {
      list($min, $max) = explode('..', $validation);
      if ($min) {
        $typeInfo['minLength'] = intval($min);
      }

      if ($max) {
        $typeInfo['maxLength'] = intval($max);
      }
    } else if ($validation) {
      $typeInfo['minLength'] = intval($validation);
      $typeInfo['maxLength'] = intval($validation);
    }

    return $typeInfo;
  }

  private function fillEntityExamples(array &$target)
  {
    // Load fixtures from the "base" and "demo" groups
    $fixtureDir = __DIR__ . "/../../fixtures";

    $finder = Finder::findFiles("*.neon", "*.yaml", "*.yml")
      ->in($fixtureDir . "/base", $fixtureDir . "/demo");

    $files = [];

    /** @var SplFileInfo $file */
    foreach ($finder as $file) {
      $files[] = $file->getRealPath();
    }

    sort($files);

    // Create a DB in memory so that we don't mess up the default one
    $em = Kdyby\Doctrine\EntityManager::create(
      Kdyby\Doctrine\Connection::create(
        ['url' => 'sqlite://:memory:'],
        $this->em->getConfiguration(),
        $this->em->getEventManager()
      ),
      $this->em->getConfiguration(),
      $this->em->getEventManager()
    );

    $schemaTool = new SchemaTool($em);
    $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

    // Load fixtures and persist them
    foreach ($files as $file) {
      $loadedEntities = $this->fixtureLoader->load($file);

      foreach ($loadedEntities as $entity) {
        $em->persist($entity);
      }
    }

    $em->flush();
    $em->clear();

    $entityExamples = [];
    foreach ($em->getMetadataFactory()->getAllMetadata() as $metadata) {
      $name = $metadata->getName();
      if (Strings::startsWith($name, "App")) {
        $entityExamples[] = $em->getRepository($name)->findAll()[0];
      }
    }

    // Dump serializable entities into the document
    foreach ($entityExamples as $entity) {
      if ($entity instanceof JsonSerializable) {
        $entityClass = ClassType::from($entity);
        $entityData = Json::decode(Json::encode($entity), Json::FORCE_ARRAY);
        $this->updateEntityEntry($target, $entityClass->getShortName(), $entityData);
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
      if ($entry[$key]["type"] === $type && $value !== NULL) {
        $entry[$key]["example"] = $value;
      }
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