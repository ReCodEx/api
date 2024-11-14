<?php

namespace App\Model\View;
use \App\Console\AnnotationHelper;


// parent class of all meta classes

class MetaView {
    function generator($data, string $output_format) {
        $backtrace = debug_backtrace()[1];
        $class = $backtrace['class'];
        $method = $backtrace['function'];
        $args = $backtrace['args'];

        $formats = AnnotationHelper::extractClassFormats($class);
        var_dump($formats);
    }

    function conforms_to_format($format, $value) {
        ///TODO
        return false;
    }

    function validate_args_old() {
        $backtrace = debug_backtrace()[1];
        $className = $backtrace['class'];
        $methodName = $backtrace['function'];
        $params_to_values = $this->get_param_names_to_values_map($backtrace);
        $params_to_format = AnnotationHelper::extractMethodCheckedParams($className, $methodName);

        foreach ($params_to_values as $param=>$value) {
            $format = $params_to_format[$param];
            if (!$this->conforms_to_format($format, $value)) {
                ///TODO: debug only
                echo "Invalid param <$className:$methodName:$param> value <$value>, given format <$format> \n";
            }
        }
    }

    /// extracts function params and returns object representations of the formats
    function validate_args() {
        $backtrace = debug_backtrace()[1];
        $className = $backtrace['class'];
        $methodName = $backtrace['function'];
        $params_to_values = $this->get_param_names_to_values_map($backtrace);
        $params_to_format = AnnotationHelper::extractMethodCheckedParams($className, $methodName);

        $a = new GroupFormat();
        AnnotationHelper::extractClassFormat(get_class($a));

        foreach ($params_to_values as $param=>$value) {
            $format = $params_to_format[$param];
            if (!$this->conforms_to_format($format, $value)) {
                ///TODO: debug only
                echo "Invalid param <$className:$methodName:$param> value <$value>, given format <$format> \n";
            }
        }
    }

    function get_param_names_to_values_map($backtrace) {
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

class GroupFormat {
    /**
     * @format uuid
     */
    public string $id;
    /**
     * @format uuid
     */
    public string $externalId;
    /**
     * @format bool
     * ///REDUNDANT
     */
    public bool $organizational;
    /**
     * @format bool
     */
    public bool $exam;
    /**
     * @format bool
     */
    public bool $archived;
    /**
     * @format bool
     */
    public bool $public;
    /**
     * @format bool
     */
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
    //function endpoint() { generator(["user_info" /* this would generate the whole user_info object */, "messages":{"name", "message"} /* cherry picking */]) }


    /// should formats be defined in comments, or in classes?
    /// classes: enables autocomplete, enforces structure, creates a ton of data classes, classes are needed for all nested objects
    /// comments: no class cluttering, less verbose, no autocomplete, does not enforce structure -> views are created as dictionaries
    /**
     * @format_def group {
     *  "id":"format:uuid",
     *  "externalId":"format:uuid",
     *  "organizational":"format:bool",
     *  "exam":"format:bool",
     *  "archived":"format:bool",
     *  "public":"format:bool",
     *  "directlyArchived":"format:bool",
     *  "localizedTexts":"format:localizedText[]",
     *  "primaryAdminsIds":"format:uuid[]",
     *  "parentGroupId":"format:uuid?",
     *  "parentGroupsIds":"format:uuid[]",
     *  "childGroups":"format:uuid[]",
     *  "privateData":"format:groupPrivateData",
     *  "permissionHints":"format:acl[]"
     * }
     */
    private $placeholder;

    // here the generator takes an input argument that conforms to format:user_info, therefore the generator can extract named parameters out 
    // of it and pass them to the database methods
    ///TODO: check whether the parameters can support the below annotations with the current framework
    /**
     * Summary of endpoint
     * @format_def user_info { "name":"format:name", "points":"format:int", "comments":"format:string[]" }
     * @checked_param format:user_info user_info
     * @checked_param format:uuid user_id
     * @checked_param format:bool verbose
     */
    private $old;



    /**
     * @checked_param format:group group
     * @checked_param format:uuid user_id
     * @checked_param format:bool verbose
     */
    function endpoint($group, $user_id, $verbose) {
        $this->validate_args();

        /*$data = [
            "id" => $user_id,
        ];
        $this->generator($data, output_format: "format:group");*/


        //$message = $this->get_last_message($user_id);
        //$this->generator(output_format: "format:messages[]:text");
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
