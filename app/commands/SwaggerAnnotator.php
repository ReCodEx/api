<?php

namespace App\Console;

use App\Helpers\Notifications\ReviewsEmailsSender;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Entity\Group;
use App\Model\Entity\User;
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
        $r = new AnnotationHelper('App\V1Module\Presenters\UsersPresenter');
        $data = $r->extractMethodData('actionUpdateUiData');
        var_dump($data);

        return Command::SUCCESS;
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
    public string $dataType;
    public string $name;
    public string $description;

    public function __construct(
        string $dataType,
        string $name,
        string $description
    ) {
        $this->dataType = $dataType;
        $this->name = $name;
        $this->description = $description;
    }
}

class AnnotationHelper {
    private string $className;
    private \ReflectionClass $class;

    /**
     * Constructor
     * @param string $className Name of the class.
     */
    public function __construct(
        string $className
    ) {
        $this->className = $className;
        $this->class = new \ReflectionClass($this->className);
    }

    public function getMethod(string $methodName): \ReflectionMethod {
        return $this->class->getMethod($methodName);
    }

    function extractAnnotationHttpMethod(array $annotations): HttpMethods|null {
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

    function extractAnnotationQueryParams(array $annotations): array {
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

    function extractBodyParams(array $expressions): array {
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

    function extractAnnotationBodyParams(array $annotations): array {
        $bodyParams = [];
        $prefix = "@Param";
        foreach ($annotations as $annotation) {
            # assumed that all body parameters have a @Param annotation
            if (str_starts_with($annotation, $prefix)) {
                # sample: @Param(type="post", name="uiData", validation="array|null", description="Structured user-specific UI data")
                # remove '@Param(' from the start and ')' from the end
                $body = substr($annotation, strlen($prefix) + 1, -1);
                $tokens = explode(", ", $body);
                $values = $this->extractBodyParams($tokens);
                $descriptor = new AnnotationParameterData($values["validation"],
                    $values["name"], $values["description"]);
                $bodyParams[] = $descriptor;
            }
        }
        return $bodyParams;
    }

    function getMethodAnnotations(string $methodName): array {
        $annotations = $this->getMethod($methodName)->getDocComment();
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

    public function extractMethodData($methodName): AnnotationData {
        $methodAnnotations = $this->getMethodAnnotations($methodName);
        $httpMethod = $this->extractAnnotationHttpMethod($methodAnnotations);
        $queryParams = $this->extractAnnotationQueryParams($methodAnnotations);
        $bodyParams = $this->extractAnnotationBodyParams($methodAnnotations);
        $data = new AnnotationData($httpMethod, $queryParams, $bodyParams);
        return $data;
    }
}