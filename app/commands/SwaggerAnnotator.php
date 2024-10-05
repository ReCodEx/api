<?php

namespace App\Console;

use App\Helpers\Notifications\ReviewsEmailsSender;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Entity\Group;
use App\Model\Entity\User;
use App\V1Module\Router\MethodRoute;
use Nette\Routing\RouteList;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use DateTime;

class SwaggerAnnotator extends Command
{
    protected static $defaultName = 'swagger:annotate';

    protected function configure(): void
    {
        $this->setName(self::$defaultName)->setDescription(
            'Annotate all methods with Swagger PHP annotations.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $namespacePrefix = 'App\V1Module\Presenters\\';

        $routes = $this->getRoutes();
        foreach ($routes as $route) {
            $metadata = $this->extractMetadata($route);
            $route = $this->extractRoute($route);

            $className = $namespacePrefix . $metadata['class'];
            $annotationData = AnnotationHelper::extractAnnotationData($className, $metadata['method']);
        }

        return Command::SUCCESS;
    }

    function getRoutes(): array {
        $router = \App\V1Module\RouterFactory::createRouter();
        
        # find all route object using a queue
        $queue = [$router];
        $routes = [];
        while (count($queue) != 0) {
            $cursor = array_shift($queue);

            if ($cursor instanceof RouteList) {
                foreach ($cursor->getRouters() as $item) {
                    # lists contain routes or nested lists
                    if ($item instanceof RouteList) {
                        array_push($queue, $item);
                    }
                    else {
                        # the first route is special and holds no useful information for annotation
                        if (get_parent_class($item) !== MethodRoute::class)
                            continue;

                        $routes[] = $this->getPropertyValue($item, "route");
                    }
                }
            }
        }

        return $routes;
    }

    private function extractRoute($routeObj) {
        $mask = self::getPropertyValue($routeObj, "mask");
        return $mask;
    }

    private function extractMetadata($routeObj) {
        $metadata = self::getPropertyValue($routeObj, "metadata");
        $presenter = $metadata["presenter"]["value"];
        $action = $metadata["action"]["value"];

        # if the name is empty, the method will be called 'actionDefault'
        if ($action === null)
            $action = "default";

        return [
            "class" => $presenter . "Presenter",
            "method" => "action" . ucfirst($action),
        ];
    }

    private static function getPropertyValue($object, string $propertyName): mixed
    {
        $class = new \ReflectionClass($object);

        do {
            try {
                $property = $class->getProperty($propertyName);
            } catch (\ReflectionException $exception) {
                $class = $class->getParentClass();
                $property = null;
            }
        } while ($property === null && $class !== null);

        $property->setAccessible(true);
        return $property->getValue($object);
    }
}

enum HttpMethods: string {
    case GET = "@GET";
    case POST = "@POST";
    case PUT = "@PUT";
    case DELETE = "@DELETE";
}

class AnnotationData {
    public HttpMethods $method;
    public array $queryParams;
    public array $bodyParams;

    public function __construct(
        HttpMethods $method,
        array $queryParams,
        array $bodyParams
    ) {
        $this->method = $method;
        $this->queryParams = $queryParams;
        $this->bodyParams = $bodyParams;
    }
}

class AnnotationParameterData {
    public string|null $dataType;
    public string $name;
    public string|null $description;

    public function __construct(
        string|null $dataType,
        string $name,
        string|null $description
    ) {
        $this->dataType = $dataType;
        $this->name = $name;
        $this->description = $description;
    }
}

class AnnotationHelper {
    private static function getMethod(string $className, string $methodName): \ReflectionMethod {
        $class = new \ReflectionClass($className);
        return $class->getMethod($methodName);
    }

    private static function extractAnnotationHttpMethod(array $annotations): HttpMethods|null {
        # get string values of backed enumeration
        $cases = HttpMethods::cases();
        $methods = [];
        foreach ($cases as $case) {
            $methods[] = $case->value;
        }

        # check if the annotations have a http method
        foreach ($methods as $method) {
            if (in_array($method, $annotations)) {
                return HttpMethods::from($method);
            }
        }

        return null;
    }

    private static function extractAnnotationQueryParams(array $annotations): array {
        $queryParams = [];
        foreach ($annotations as $annotation) {
            # assumed that all query parameters have a @param annotation
            if (str_starts_with($annotation, "@param")) {
                # sample: @param string $id Identifier of the user
                $tokens = explode(" ", $annotation);
                $type = $tokens[1];
                # assumed that all names start with $
                $name = substr($tokens[2], 1);
                $description = implode(" ", array_slice($tokens,3));
                $descriptor = new AnnotationParameterData($type, $name, $description);
                $queryParams[] = $descriptor;
            }
        }
        return $queryParams;
    }

    private static function extractBodyParams(array $expressions): array {
        $dict = [];
        #sample: [ name="uiData", validation="array|null" ]
        foreach ($expressions as $expression) {
            $tokens = explode('="', $expression);
            $name = $tokens[0];
            # remove the '"' at the end
            $value = substr($tokens[1], 0, -1);
            $dict[$name] = $value;
        }
        return $dict;
    }

    private static function extractAnnotationBodyParams(array $annotations): array {
        $bodyParams = [];
        $prefix = "@Param";
        foreach ($annotations as $annotation) {
            # assumed that all body parameters have a @Param annotation
            if (str_starts_with($annotation, $prefix)) {
                # sample: @Param(type="post", name="uiData", validation="array|null", description="Structured user-specific UI data")
                # remove '@Param(' from the start and ')' from the end
                $body = substr($annotation, strlen($prefix) + 1, -1);
                $tokens = explode(", ", $body);
                $values = self::extractBodyParams($tokens);
                $descriptor = new AnnotationParameterData($values["validation"],
                    $values["name"], $values["description"]);
                $bodyParams[] = $descriptor;
            }
        }
        return $bodyParams;
    }

    private static function getMethodAnnotations(string $className, string $methodName): array {
        $annotations = self::getMethod($className, $methodName)->getDocComment();
        $lines = preg_split("/\r\n|\n|\r/", $annotations);

        # trims whitespace and asterisks
        # assumes that asterisks are not used in some meaningful way at the beginning and end of a line
        foreach ($lines as &$line) {
            $line = trim($line);
            $line = trim($line, "*");
            $line = trim($line);
        }

        # removes the first and last line
        # assumes that the first line is '/**' and the last line '*/' (or '/' after trimming) 
        $lines = array_slice($lines, 1, -1);

        $merged = [];
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            # skip lines not starting with '@'
            if ($line[0] !== "@")
                continue;

            # merge lines not starting with '@' with their parent lines starting with '@'
            while ($i + 1 < count($lines) && $lines[$i + 1][0] !== "@") {
                $line .= " " . $lines[$i + 1];
                $i++;
            }

            $merged[] = $line;
        }

        return $merged;
    }

    public static function extractAnnotationData(string $className, string $methodName): AnnotationData {
        $methodAnnotations = self::getMethodAnnotations($className, $methodName);

        $httpMethod = self::extractAnnotationHttpMethod($methodAnnotations);
        $queryParams = self::extractAnnotationQueryParams($methodAnnotations);
        $bodyParams = self::extractAnnotationBodyParams($methodAnnotations);
        $data = new AnnotationData($httpMethod, $queryParams, $bodyParams);
        return $data;
    }
}