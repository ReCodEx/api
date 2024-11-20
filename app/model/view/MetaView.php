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
            foreach ($paramValue as $key=>$value) {
                ///TODO: return 404
                if (!array_key_exists($key, $classFormat)) {
                    echo "Error: unknown param: $paramName\n";
                    return [];
                }

                $obj->$key = $value;
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


class MetaFormat {
    /**
     * Validates the given format.
     * @return bool Returns whether the format and all nested formats are valid.
     */
    public function validate() {
        return true;
    }

    /**
     * Validates this format. Automatically called by the validate method on all fields.
     * Primitive formats should always override this, composite formats might want to override
     * this in case more complex contracts need to be enforced.
     * @return bool Returns whether the format is valid.
     */
    protected function validate_this() {
        
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
    function endpoint($group) {
        $params = $this->getTypedParams();
        $formattedGroup = $params["group"];
        var_dump($formattedGroup);
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
