<?php

namespace App\Model\View;
use \App\Helpers\Swagger\AnnotationHelper;


// parent class of all meta classes

class MetaView {
    /**
     * Extracts the parameters and annotations of the calling function and converts the parameters to their target type.
     * @return object[] Returns a map paramName=>targetTypeInstance, where the instances are filled with the data from the parameters.
     */
    function getTypedParams() {
        // extract function params of the caller
        $backtrace = debug_backtrace()[1];
        $className = $backtrace['class'];
        $methodName = $backtrace['function'];
        // get param values
        $paramsToValues = $this->getParamNamesToValuesMap($backtrace);
        // get param format
        $paramsToFormat = AnnotationHelper::extractMethodCheckedParams($className, $methodName);

        // get all format definitions
        $formats = AnnotationHelper::getFormatDefinitions();

        $paramToTypedMap = [];
        foreach ($paramsToValues as $paramName=>$paramValue) {
            $format = $paramsToFormat[$paramName];

            // the parameter name was not present in the annotations
            if (!array_key_exists($paramName, $paramsToFormat)) {
                ///TODO: return 500
                echo "Error: unknown param format: $paramName\n";
                return [];
            }

            $targetClassName = $formats[$format];
            $classFormat = AnnotationHelper::getClassFormats($targetClassName);
            $obj = new $targetClassName();

            // fill the new object with the param values
            ///TODO: handle nested formated objects
            foreach ($paramValue as $propertyName=>$propertyValue) {
                ///TODO: return 404
                // the property was not present in the class definition
                if (!array_key_exists($propertyName, $classFormat)) {
                    echo "Error: unknown param: $paramName\n";
                    return [];
                }

                $obj->$propertyName = $propertyValue;
            }

            $paramToTypedMap[$paramName] = $obj;
        }

        return $paramToTypedMap;
    }

    function getParamNamesToValuesMap($backtrace): array {
        $className = $backtrace['class'];
        $args = $backtrace['args'];
        $methodName = $backtrace['function'];

        $class = new \ReflectionClass($className);
        $method = $class->getMethod($methodName);
        $params = array_map(fn($param) => $param->name, $method->getParameters());

        $argMap = [];
        for ($i = 0; $i < count($params); $i++) {
            $argMap[$params[$i]] = $args[$i];
        }
        return $argMap;
    }
}

/**
 * Parses format string enriched by nullability and array modifiers.
 * In case the format contains array, this data class can be recursive.
 * Example: string?[]? can either be null or of string?[] type, an array of nullable strings
 * Example2: string[]?[] is an array of null or string arrays
 */
class FormatParser {
    public bool $nullable = false;
    public bool $isArray = false;
    // contains the format stripped of the nullability ?, null if it is an array
    public ?string $format = null;
    // contains the format definition of nested elements, null if it is not an array
    public ?FormatParser $nested = null; 

    public function __construct(string $format) {
        // check nullability
        if (str_ends_with($format, "?")) {
            $this->nullable = true;
            $format = substr($format, 0, -1);
        }

        // check array
        if (str_ends_with($format, "[]")) {
            $this->isArray = true;
            $format = substr($format, 0, -2);
            $this->nested = new FormatParser($format);
        }
        else {
            $this->format = $format;
        }
    }
}


class MetaFormat {
    // validates primitive formats of intrinsic PHP types
    ///TODO: make this static somehow (or cached)
    private $validators;

    public function __construct() {
        $this->validators = AnnotationHelper::getValidators();
    }


    /**
     * Validates the given format.
     * @return bool Returns whether the format and all nested formats are valid.
     */
    public function validate() {
        // check whether all higher level contracts hold
        if (!$this->validateSelf())
            return false;

        // check properties
        $selfFormat = AnnotationHelper::getClassFormats(get_class($this));
        foreach ($selfFormat as $propertyName=>$propertyFormat) {
            ///TODO: check if this is true
            /// if the property is checked by type only, there is no need to check it as an invalid assignment would rise an error
            $value = $this->$propertyName;
            $format = $propertyFormat["format"];
            if ($format === null)
                continue;

            // enables parsing more complicated formats (string[]?, string?[], string?[][]?, ...)
            $parsedFormat = new FormatParser($format);
            if (!$this->recursiveFormatChecker($value, $parsedFormat))
            return false;
        }

    }

    private function recursiveFormatChecker($value, FormatParser $parsedFormat) {
        // enables parsing more complicated formats (string[]?, string?[], string?[][]?, ...)
        
        // check nullability
        if ($value === null)
            return $parsedFormat->nullable;

        // handle arrays
        if ($parsedFormat->isArray) {
            if (!is_array($value))
                return false;

            // if any element fails, the whole format fails
            foreach ($value as $element) {
                if (!$this->recursiveFormatChecker($element, $parsedFormat->nested))
                    return false;
            }
            return true;
        }

        ///TODO: raise an error
        // check whether the validator exists
        if (!array_key_exists($parsedFormat->format, $this->validators)) {
            echo "Error: missing validator for format: " . $parsedFormat->format . "\n";
            return false;
        }

        return $this->validators[$parsedFormat->format]($value);
    }

    /**
     * Validates this format. Automatically called by the validate method on all fields.
     * Primitive formats should always override this, composite formats might want to override
     * this in case more complex contracts need to be enforced.
     * This method should not check the format of nested types.
     * @return bool Returns whether the format is valid.
     */
    protected function validateSelf() {
        // there are no constraints by default
        return true;
    }
}

/**
 * @format_def group
 */
class GroupFormat extends MetaFormat {
    /**
     * @format uuid
     */
    public string $id;
    /**
     * @format uuid
     */
    public string $externalId;
    public bool $organizational;
    public bool $exam;
    public bool $archived;
    public bool $public;
    public bool $directlyArchived;
    /**
     * @format localizedText[]
     */
    public array $localizedTexts;
    /**
     * @format uuid[]
     */
    public array $primaryAdminsIds;
    /**
     * @format uuid?
     */
    public string $parentGroupId;
    /**
     * @format uuid[]
     */
    public array $parentGroupsIds;
    /**
     * @format uuid[]
     */
    public array $childGroups;
    /**
     * @format groupPrivateData
     */
    public $privateData;
    /**
     * @format acl[]
     */
    public array $permissionHints;
}

class TestView extends MetaView {
    /**
     * @checked_param format:group group
     * @checked_param format:uuid user_id
     */
    function endpoint($group, $user_id) {
        $params = $this->getTypedParams();
        $formattedGroup = $params["group"];
        var_dump($formattedGroup);

        // $a = new GroupFormat();
        // $a->validate();
    }

    // the names of the format and the output do not have to be identical, the strings in the desired data format refer the output names
    /**
     * @input format:user_id user_id              // the input has to be invoked with the user_id extracted from format:group
     * @format_def message { "id":"format:uuid", "name":"format:name", "text":"format:string", "date":"format:datetime" }
     * @generates format:message[] messages
     */
    function get_messages($user_id) {
        $messages = [];
        for ($i = 0; $i < 5; $i++) {
            $messages[] = [
                "id" => $i,
                "name" => "John Doe",
                "message" => "hello $i",
                "date" => "todo",
            ];
        }
        return $messages;
    }

    function get_last_message($user_id) {
        return [
            "id" => 5,
            "name" => "John Doe",
            "message" => "hello 5",
            "date" => "todo",
        ];
    }

    ///TODO: validators should not exist, validation should be automatic (or should they? what about specific domain rules)
    // validators should only return a bool and a descriptive error message; whether it is input or output validation should be handled elsewhere (should the output be validated?)
    // @format group { ... } // formats should be defined on validators so that they can be easily found, additionally the validators enforce their structure, so it is nice when they are next to each other
    function validate_group($group) { }
}
