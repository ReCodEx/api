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

        $fileBuilder = new FileBuilder("app/V1Module/presenters/annotations.php");
        $fileBuilder->startClass("AnnotationController");
        $routes = $this->getRoutes();
        foreach ($routes as $route) {
            $metadata = $this->extractMetadata($route);
            $route = $this->extractRoute($route);

            $className = $namespacePrefix . $metadata['class'];
            $annotationData = AnnotationHelper::extractAnnotationData($className, $metadata['method']);

            $fileBuilder->addAnnotatedMethod($metadata['method'], $annotationData->toSwaggerAnnotations($route));
        }
        $fileBuilder->endClass();


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

        # sample: replaces '/users/<id>' with '/users/{id}'
        $mask = str_replace(["<", ">"], ["{", "}"], $mask);
        return "/" . $mask;
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

class FileBuilder {
    private $file;
    private $methodEntries;

    public function __construct(
        string $filename
    ) {
        $this->initFile($filename);
        $this->methodEntries = 0;
    }

    private function initFile(string $filename) {
        $this->file = fopen($filename, "w");
        fwrite($this->file, "<?php\n");
        fwrite($this->file, "namespace App\V1Module\Presenters;\n");
        fwrite($this->file, "use OpenApi\Annotations as OA;\n");
    }

    ///TODO: hardcoded info
    private function createInfoAnnotation() {
        $head = "@OA\\Info";
        $body = new ParenthesesBuilder();
        $body->addKeyValue("version", "1.0");
        $body->addKeyValue("title", "ReCodEx API");
        return $head . $body->toString();
    }

    private function writeAnnotationLineWithComments(string $annotationLine) {
        fwrite($this->file, "/**\n");
        fwrite($this->file, "* {$annotationLine}\n");
        fwrite($this->file, "*/\n");
    }

    public function startClass(string $className) {
        ///TODO: hardcoded
        $this->writeAnnotationLineWithComments($this->createInfoAnnotation());
        fwrite($this->file, "class {$className} {\n");
    }

    public function endClass(){
        fwrite($this->file, "}\n");
    }

    public function addAnnotatedMethod(string $methodName, string $annotationLine) {
        $this->writeAnnotationLineWithComments($annotationLine);
        fwrite($this->file, "public function {$methodName}{$this->methodEntries}() {}\n");
        $this->methodEntries++;
    }

}

enum HttpMethods: string {
    case GET = "@GET";
    case POST = "@POST";
    case PUT = "@PUT";
    case DELETE = "@DELETE";
}

class AnnotationData {
    public HttpMethods $httpMethod;
    
    # $queryParams contain path and query params. This is because they are extracted from
    # annotations directly, and the annotations do not contain this information.
    public array $queryParams;
    public array $bodyParams;

    public function __construct(
        HttpMethods $httpMethod,
        array $queryParams,
        array $bodyParams
    ) {
        $this->httpMethod = $httpMethod;
        $this->queryParams = $queryParams;
        $this->bodyParams = $bodyParams;
    }

    private function getHttpMethodAnnotation(): string {
        # sample: converts '@PUT' to 'Put'
        $httpMethodString = ucfirst(strtolower(substr($this->httpMethod->value, 1)));
        return "@OA\\" . $httpMethodString;
    }

    private function getRoutePathParamNames(string $route): array {
        # sample: from '/users/{id}/{name}' generates ['id', 'name']
        preg_match_all('/\{([A-Za-z0-9 ]+?)\}/', $route, $out);
        return $out[1];
    }

    public function toSwaggerAnnotations(string $route) {
        $httpMethodAnnotation = $this->getHttpMethodAnnotation();
        $body = new ParenthesesBuilder();
        $body->addKeyValue("path", $route);

        $pathParamNames = $this->getRoutePathParamNames($route);
        foreach ($this->queryParams as $queryParam) {
            # find out where the parameter is located
            $location = 'query';
            if (in_array($queryParam->name, $pathParamNames))
                $location = 'path';

            $body->addValue($queryParam->toParameterAnnotation($location));
        }

        ///TODO: placeholder
        $body->addValue('@OA\Response(response="200",description="The data")');
        return $httpMethodAnnotation . $body->toString();
    }
}

class ParenthesesBuilder {
    private array $tokens;

    public function __construct() {
        $this->tokens = [];
    }

    public function addKeyValue(string $key, mixed $value): ParenthesesBuilder {
        $valueString = strval($value);
        # strings need to be wrapped in quotes
        if (is_string($value))
            $valueString = "\"{$value}\"";
        # convert bools to strings
        else if (is_bool($value))
            $valueString = ($value ? "true" : "false");

        $assignment = "{$key}={$valueString}";
        return $this->addValue($assignment);
    }

    public function addValue(string $value): ParenthesesBuilder {
        $this->tokens[] = $value;
        return $this;
    }

    public function toString(): string {
        return '(' . implode(',', $this->tokens) . ')';
    }
}

class AnnotationParameterData {
    public string|null $dataType;
    public string $name;
    public string|null $description;

    private static $nullableSuffix = '|null';
    private static $typeMap = [
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'array' => 'array',
        'int' => 'integer',
        'integer' => 'integer',
        'float' => 'number',
        'number' => 'number',
        'numeric' => 'number',
        'numericint' => 'integer',
        'timestamp' => 'integer',
        'string' => 'string',
        'unicode' => ['string', 'unicode'],
        'email' => ['string', 'email'],
        'url' => ['string', 'url'],
        'uri' => ['string', 'uri'],
        'pattern' => null,
        'alnum' => ['string', 'alphanumeric'],
        'alpha' => ['string', 'alphabetic'],
        'digit' => ['string', 'numeric'],
        'lower' => ['string', 'lowercase'],
        'upper' => ['string', 'uppercase']
    ];

    public function __construct(
        string|null $dataType,
        string $name,
        string|null $description
    ) {
        $this->dataType = $dataType;
        $this->name = $name;
        $this->description = $description;
    }

    private function isDatatypeNullable(): bool {
        # if the dataType is not specified (it is null), it means that the annotation is not
        # complete and defaults to a non nullable string
        if ($this->dataType === null)
            return false;

        # assumes that the typename ends with '|null'
        if (str_ends_with($this->dataType, self::$nullableSuffix))
            return true;

        return false;
    }

    private function generateSchemaAnnotation(): string {
        # if the type is not specified, default to a string
        $type = 'string';
        $typename = $this->dataType;
        if ($typename !== null) {
            if ($this->isDatatypeNullable())
                $typename = substr($typename,0,-strlen(self::$nullableSuffix));
    
            if (self::$typeMap[$typename] === null) 
                throw new \InvalidArgumentException("Error in SwaggerTypeConverter: Unknown typename: {$typename}");
            
            $type = self::$typeMap[$typename];
        }

        $head = "@OA\\Schema";
        $body = new ParenthesesBuilder();
        $body->addKeyValue("type", $type);

        return $head . $body->toString();
    }

    /**
     * Converts the object to a @OA\Parameter(...) annotation string
     * @param string $parameterLocation Where the parameter resides. Can be 'path', 'query', 'header' or 'cookie'.
     */
    public function toParameterAnnotation(string $parameterLocation): string {
        $head = "@OA\\Parameter";
        $body = new ParenthesesBuilder();
        
        $body->addKeyValue("name", $this->name);
        $body->addKeyValue("in", $parameterLocation);
        $body->addKeyValue("required", !$this->isDatatypeNullable());
        if ($this->description !== null)
            $body->addKeyValue("description", $this->description);

        $body->addValue($this->generateSchemaAnnotation());

        return $head . $body->toString();
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