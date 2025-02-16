<?php

namespace App\Helpers\MetaFormats;

use App\Exceptions\InternalServerException;
use App\Helpers\MetaFormats\Attributes\Param;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VFloat;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Helpers\Swagger\AnnotationHelper;
use App\Helpers\Swagger\ParenthesesBuilder;
use App\V1Module\Presenters\BasePresenter;
use ReflectionMethod;

class AnnotationToAttributeConverter
{
    /**
     * A regex that matches @Param annotations and captures its parameters. Can capture up to 7 parameters.
     * Contains 6 copies of the following sub-regex: '(?:([a-z]+?=.+?),?\s*\*?\s*)?', which
     *   matches 'name=value' assignments followed by an optional comma, whitespace,
     *   star (multi-line annotation support), whitespace. The capture contains only 'name=value'.
     * The regex ends with '([a-z]+?=.+)\)', which is similar to the above, but instead of ending with
     *   an optional comma etc., it ends with the closing parentheses of the @Param annotation.
     */
    private static string $netteRegex = "/\*\s*@Param\((?:([a-z]+?=.+?),?\s*\*?\s*)?(?:([a-z]+?=.+?),?\s*\*?\s*)?(?:([a-z]+?=.+?),?\s*\*?\s*)?(?:([a-z]+?=.+?),?\s*\*?\s*)?(?:([a-z]+?=.+?),?\s*\*?\s*)?(?:([a-z]+?=.+?),?\s*\*?\s*)?([a-z]+?=.+)\)/";

    /**
     * A regex that matches standard PHP @param annotations of the <@param type $name description> format.
     * There are three capture groups: validation (type), name and description.
     * The name does not contain the '$' prefix, and the description can contain '*', newline symbols,
     * and extra spaces if multiline.
     */
    private static string $standardRegex = "/\*\h*@param\h+(?<validation>\S*)\h+\\$(?<name>\S*)\h*(?<description>.*(?:(?!\s*\*\s*(?:@|\/))(?:\s*\*\s*.*))*)/";

    // placeholder for detected nette annotations ("@Param")
    // this text must not be present in the presenter files
    private static string $netteAttributePlaceholder = "<!>#nette#<!>";
    // placeholder for detected standard php parameter annotations ("@param")
    private static string $standardAttributePlaceholder = "<!>#standard#<!>";

    // Metadata about endpoints used to determine what class methods are endpoints and what params
    // are path and query. Initialized lazily (it cannot be assigned here because it is not a constant expression). 
    private static ?array $routesMetadata = null;

    private static function shortenClass(string $className)
    {
        $tokens = explode("\\", $className);
        return end($tokens);
    }

    private static function convertNetteRegexCapturesToDictionary(array $captures)
    {
        // convert the string assignments in $captures to an associative array
        $annotationParameters = [];
        // the first element is the matched string
        for ($i = 1; $i < count($captures); $i++) {
            $capture = $captures[$i];
            if ($capture === null) {
                continue;
            }

            // the regex extracts the key as the first capture, and the value as the second or third (depends
            // whether the value is enclosed in double quotes)
            $parseResult = preg_match('/([a-z]+)=(?:(?:"(.+?)")|(?:(.+)))/', $capture, $tokens, PREG_UNMATCHED_AS_NULL);
            if ($parseResult !== 1) {
                throw new InternalServerException("Unexpected assignment format: $capture");
            }

            $key = $tokens[1];
            $value = $tokens[2] ?? $tokens[3];
            $annotationParameters[$key] = $value;
        }

        return $annotationParameters;
    }

    private static function convertStandardRegexCapturesToDictionary(array $captures)
    {
        ///TODO: add functionality to check whether the parameter is from query or path
        $annotationParameters = [];

        if (!array_key_exists("validation", $captures)) {
            throw new InternalServerException("Missing validation parameter.");
        }
        $annotationParameters["validation"] = $captures["validation"];

        if (!array_key_exists("name", $captures)) {
            throw new InternalServerException("Missing name parameter.");
        }
        $annotationParameters["name"] = $captures["name"];

        if (array_key_exists("description", $captures)) {
            $annotationParameters["description"] = $captures["description"];
        }

        return $annotationParameters;
    }

    /**
     * Convers an associative array into an attribute string builder.
     * @param array $annotationParameters An associative array with a subset of the following keys:
     *  type, name, validation, description, required, nullable.
     * @throws \App\Exceptions\InternalServerException
     * @return ParenthesesBuilder A string builder used to build the final attribute string.
     */
    private static function convertRegexCapturesToParenthesesBuilder(array $annotationParameters)
    {
        // serialize the parameters to an attribute
        $parenthesesBuilder = new ParenthesesBuilder();

        // add type
        if (!array_key_exists("type", $annotationParameters)) {
            throw new InternalServerException("Missing type parameter.");
        }

        $typeStr = $annotationParameters["type"];
        $paramTypeClass = self::shortenClass(Type::class);
        $type = null;
        switch ($typeStr) {
            case "post":
                $type = $paramTypeClass . "::Post";
                break;
            case "query":
                $type = $paramTypeClass . "::Query";
                break;
            case "path":
                $type = $paramTypeClass . "::Path";
                break;
            default:
                throw new InternalServerException("Unknown request type: $typeStr");
        }
        $parenthesesBuilder->addValue($type);

        // add name
        if (!array_key_exists("name", $annotationParameters)) {
            throw new InternalServerException("Missing name parameter.");
        }
        $parenthesesBuilder->addValue("\"{$annotationParameters["name"]}\"");

        $nullable = false;
        if (array_key_exists("validation", $annotationParameters)) {
            $validation = $annotationParameters["validation"];

            if (self::checkValidationNullability($validation)) {
                // remove the '|null' from the end of the string
                $validation = substr($validation, 0, -5);
                $nullable = true;
            }

            // this will always produce a single validator (the annotations do not contain multiple validation fields)
            $validator = self::convertAnnotationValidationToValidatorString($validation);
            $parenthesesBuilder->addValue(value: "[ $validator ]");
        }

        if (array_key_exists("description", $annotationParameters)) {
            $parenthesesBuilder->addValue("\"{$annotationParameters["description"]}\"");
        }

        if (array_key_exists("required", $annotationParameters)) {
            $parenthesesBuilder->addValue("required: " . $annotationParameters["required"]);
        }

        if ($nullable) {
            $parenthesesBuilder->addValue("nullable: true");
        }

        return $parenthesesBuilder;
    }

    /**
     * Used by preg_replace_callback to replace "@param" annotation captures with placeholder strings to
     * mark the lines for future replacement. Additionally stores the captures into an output array.
     * @param array $captures An array of captures, with empty captures as NULL (PREG_UNMATCHED_AS_NULL flag).
     * @param array $capturesList An output list for captures.
     * @return string Returns a placeholder.
     */
    private static function standardRegexCaptureToAttributeCallback(array $captures, array &$capturesList)
    {
        $capturesList[] = $captures;
        return self::$standardAttributePlaceholder;
    }

    /**
     * Used by preg_replace_callback to replace "@Param" annotation captures with placeholder strings to mark the
     * lines for future replacement. Additionally stores the captures into an output array.
     * @param array $captures An array of captures, with empty captures as NULL (PREG_UNMATCHED_AS_NULL flag).
     * @param array $capturesList An output list for captures.
     * @return string Returns a placeholder.
     */
    private static function netteRegexCaptureToAttributeCallback(array $captures, array &$capturesList)
    {
        $capturesList[] = $captures;
        return self::$netteAttributePlaceholder;
    }

    private static function checkValidationNullability(string $validation): bool
    {
        return str_ends_with($validation, "|null");
    }

    /**
     * Converts annotation validation values (such as "string:1..255") to Validator construction
     *   strings (such as "new VString(1, 255)").
     * @param string $validation The annotation validation string.
     * @return string Returns the object construction string.
     */
    private static function convertAnnotationValidationToValidatorString(string $validation): string
    {
        if (str_starts_with($validation, "string")) {
            $stringValidator = self::shortenClass(VString::class);

            // handle string length constraints, such as "string:1..255"
            if (strlen($validation) > 6) {
                if ($validation[6] !== ":") {
                    throw new InternalServerException("Unknown string validation format: $validation");
                }
                $suffix = substr($validation, 7);

                // special case for uuids
                if ($suffix === "36") {
                    return "new " . self::shortenClass(VUuid::class) . "()";
                }

                // capture the two bounding numbers and the double dot in strings of
                // types "1..255", "..255", "1..", or "255"
                if (preg_match("/([0-9]*)(..)?([0-9]+)?/", $suffix, $matches, PREG_UNMATCHED_AS_NULL) !== 1) {
                    throw new InternalServerException("Unknown string validation format: $validation");
                }

                // type "255", exact match
                if ($matches[2] === null) {
                    return "new {$stringValidator}({$matches[1]}, {$matches[1]})";
                // type "1..255"
                } elseif ($matches[1] !== null && $matches[3] !== null) {
                    return "new {$stringValidator}({$matches[1]}, {$matches[3]})";
                // type "..255"
                } elseif ($matches[1] === null) {
                    return "new {$stringValidator}(0, {$matches[3]})";
                // type "1.."
                } elseif ($matches[3] === null) {
                    return "new {$stringValidator}({$matches[1]})";
                }

                throw new InternalServerException("Unknown string validation format: $validation");
            }

            return "new {$stringValidator}()";
        }

        // non-string validation rules do not have parameters, so they can be converted directly
        $validatorClass = null;
        switch ($validation) {
            case "email":
            // there is one occurrence of this
            case "email:1..":
                $validatorClass = VEmail::class;
                break;
            case "numericint":
            case "integer":
                $validatorClass = VInt::class;
                break;
            case "bool":
            case "boolean":
                $validatorClass = VBool::class;
                break;
            case "array":
            case "list":
                $validatorClass = VArray::class;
                break;
            case "timestamp":
                $validatorClass = VTimestamp::class;
                break;
            case "numeric":
                $validatorClass = VFloat::class;
                break;
            default:
                throw new InternalServerException("Unknown validation rule: $validation");
        }

        return "new " . self::shortenClass($validatorClass) . "()";
    }

    /**
     * @return string[] Returns an array of Validator class names (without the namespace).
     */
    private static function getValidatorNames()
    {
        $dir = __DIR__ . "/Validators";
        $baseFilenames = scandir($dir);
        $classNames = [];
        foreach ($baseFilenames as $filename) {
            if (!str_ends_with($filename, ".php")) {
                continue;
            }

            // remove the ".php" suffix
            $className = substr($filename, 0, -4);
            $classNames[] = $className;
        }
        return $classNames;
    }

    private static function preprocessFile(string $path)
    {
        if (self::$routesMetadata == null) {
            self::$routesMetadata = AnnotationHelper::getRoutesMetadata();
        }

        // extract presenter namespace from BasePresenter
        $namespaceTokens = explode("\\", BasePresenter::class);
        $namespace = implode("\\", array_slice($namespaceTokens, 0, count($namespaceTokens) - 1));
        // join with presenter name from the file
        $className = $namespace . "\\" . basename($path, ".php");

        // get endpoint metadata for this file
        $endpoints = array_filter(self::$routesMetadata, function ($route) use ($className) {
            return $route["class"] == $className;
        });

        // add info about where the method starts
        foreach ($endpoints as &$endpoint) {
            $reflectionMethod = new ReflectionMethod($endpoint["class"], $endpoint["method"]);
            // the method returns the line indexed from 1
            $endpoint["startLine"] = $reflectionMethod->getStartLine() - 1;
            $endpoint["endLine"] = $reflectionMethod->getEndLine() - 1;
        }
        
        // sort endpoint based on position in the file (so that the file preprocessing can be done top-down)
        $startLines = array_column($endpoints, "startLine");
        array_multisort($startLines, SORT_ASC, $endpoints);
        
        // get file lines
        $content = file_get_contents($path);
        $lines = self::fileStringToLines($content);

        // maps certain line indices to replacement annotation blocks and their extends
        $annotationReplacements = [];

        foreach ($endpoints as $endpoint) {
            $class = $endpoint["class"];
            $method = $endpoint["method"];
            $route = $endpoint["route"];
            $startLine = $endpoint["startLine"];

            // get info about endpoint parameters and their types
            $annotationData = AnnotationHelper::extractAnnotationData(
                $class,
                $method,
                $route
            );

            // find start and end lines of method annotations
            $annotationEndLine = $startLine - 1;
            $annotationStartLine = -1;
            for ($i = $annotationEndLine - 1; $i >= 0; $i--) {
                if (str_contains($lines[$i], "/**")) {
                    $annotationStartLine = $i;
                    break;
                }
            }
            if ($annotationStartLine == -1) {
                throw new InternalServerException("Could not find annotation start line");
            }

            $annotationLines = array_slice($lines, $annotationStartLine, $annotationEndLine - $annotationStartLine + 1);
            $params = $annotationData->getAllParams();

            /// attempt to remove param lines, but it is too complicated (handle missing param lines + multiline params)
            // foreach ($params as $param) {
            //     // matches the line containing the parameter name with word boundaries
            //     $paramLineRegex = "/\\$\\b" . $param->name . "\\b/";
            //     $lineIdx = -1;
            //     for ($i = 0; $i < count($annotationLines); $i++) {
            //         if (preg_match($paramLineRegex, $annotationLines[$i]) == 1) {
            //             $lineIdx = $i;
            //             break;
            //         }
            //     }
            // }

            // crate an attribute from each parameter
            foreach ($params as $param) {
                $data = [
                    "name" => $param->name,
                    "validation" => $param->swaggerType,
                    "type" => $param->location,
                    "required" => ($param->required ? "true" : "false"),
                    "nullable" => ($param->nullable ? "true" : "false"),
                ];
                if ($param->description != null) {
                    $data["description"] = $param->description;
                }

                $builder = self::convertRegexCapturesToParenthesesBuilder($data);
                $paramAttributeClass = self::shortenClass(Param::class);
                $attributeLine = "    #[{$paramAttributeClass}{$builder->toString()}]";
                // change to multiline if the line is too long
                if (strlen($attributeLine) > 120) {
                    $attributeLine = "    #[{$paramAttributeClass}{$builder->toMultilineString(4)}]";
                }

                // append the attribute line to the existing annotations
                $annotationLines[] = $attributeLine;
            }

            $annotationReplacements[$annotationStartLine] = [
                "annotations" => $annotationLines,
                "originalAnnotationEndLine" => $annotationEndLine,
            ];
        }

        $newLines = [];
        for ($i = 0; $i < count($lines); $i++) {
            // copy non-annotation lines
            if (!array_key_exists($i, $annotationReplacements)) {
                $newLines[] = $lines[$i];
                continue;
            }

            // add new annotations
            foreach ($annotationReplacements[$i]["annotations"] as $replacementLine) {
                $newLines[] = $replacementLine;
            }
            // move $i to the original annotation end line
            $i = $annotationReplacements[$i]["originalAnnotationEndLine"];
        }

        return self::linesToFileString($newLines);
    }

    private static function fileStringToLines(string $fileContent): array
    {
        $lines = preg_split("/((\r?\n)|(\r\n?))/", $fileContent);
        if ($lines == false) {
            throw new InternalServerException("File content cannot be split into lines");
        }
        return $lines;
    }

    private static function linesToFileString(array $lines): string
    {
        return implode("\n", $lines);
    }

    public static function convertFile(string $path)
    {
        $content = self::preprocessFile($path);
        // Array that contains parentheses builders of all future generated attributes.
        // Filled dynamically with the preg_replace_callback callback.
        $standardCapturesList = [];
        $netteCapturesList = [];
        // $withInterleavedAttributes = preg_replace_callback(
        //     self::$standardRegex,
        //     function ($matches) use (&$standardCapturesList) {
        //         return self::standardRegexCaptureToAttributeCallback($matches, $standardCapturesList);
        //     },
        //     $content,
        //     flags: PREG_UNMATCHED_AS_NULL
        // );
        $withInterleavedAttributes = preg_replace_callback(
            self::$netteRegex,
            function ($matches) use (&$netteCapturesList) {
                return self::netteRegexCaptureToAttributeCallback($matches, $netteCapturesList);
            },
            $content,
            flags: PREG_UNMATCHED_AS_NULL
        );

        // move the attribute lines below the comment block
        $lines = [];
        $standardAttributeLinesCount = 0;
        $netteAttributeLinesCount = 0;
        $usingsAdded = false;
        $paramAttributeClass = self::shortenClass(Param::class);
        $paramTypeClass = self::shortenClass(Type::class);
        foreach (self::fileStringToLines($withInterleavedAttributes) as $line) {
            // detected the initial "use" block, add usings for new types
            if (!$usingsAdded && strlen($line) > 3 && substr($line, 0, 3) === "use") {
                $lines[] = "use App\\Helpers\\MetaFormats\\Attributes\\{$paramAttributeClass};";
                $lines[] = "use App\\Helpers\\MetaFormats\\{$paramTypeClass};";
                foreach (self::getValidatorNames() as $validator) {
                    $lines[] = "use App\\Helpers\\MetaFormats\\Validators\\{$validator};";
                }
                // write the detected line (the first detected "use" line)
                $lines[] = $line;
                $usingsAdded = true;
            // detected an attribute line placeholder, increment the counter and remove the line
            } elseif (str_contains($line, self::$standardAttributePlaceholder)) {
                $standardAttributeLinesCount++;
            } elseif (str_contains($line, self::$netteAttributePlaceholder)) {
                $netteAttributeLinesCount++;
            // detected the end of the comment block "*/", flush attribute lines
            } elseif (trim($line) === "*/") {
                $lines[] = $line;
                for ($i = 0; $i < $standardAttributeLinesCount; $i++) {
                    self::convertStandardRegexCapturesToDictionary($standardCapturesList[$i]);
                    ///TODO: implement rest of logic
                }
                for ($i = 0; $i < $netteAttributeLinesCount; $i++) {
                    $annotationParameters = self::convertNetteRegexCapturesToDictionary($netteCapturesList[$i]);
                    $parenthesesBuilder = self::convertRegexCapturesToParenthesesBuilder($annotationParameters);
                    $attributeLine = "    #[{$paramAttributeClass}{$parenthesesBuilder->toString()}]";
                    // change to multiline if the line is too long
                    if (strlen($attributeLine) > 120) {
                        $attributeLine = "    #[{$paramAttributeClass}{$parenthesesBuilder->toMultilineString(4)}]";
                    }
                    $lines[] = $attributeLine;
                }
                
                // reset the counters for the next detected endpoint
                ///TODO: these should not be reset (later captures will never be used)
                $standardAttributeLinesCount = 0;
                $netteAttributeLinesCount = 0;
            } else {
                $lines[] = $line;
            }
        }

        return self::linesToFileString($lines);
    }
}
