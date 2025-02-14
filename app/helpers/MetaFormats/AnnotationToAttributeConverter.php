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
use App\Helpers\Swagger\ParenthesesBuilder;

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
     * There are three capture groups: type, name and description.
     * The name does not contain the '$' prefix, and the description can contain '*', newline symbols,
     * and extra spaces if multiline.
     */
    private static string $standardRegex = "/\*\s*@param\s+(?<type>\S*)\s+\$(?<name>\S*)\s*(?<description>.*(?:(?!\s*\*\s*(?:@|\/))(?:\s*\*\s*.*))*)/";

    private static function shortenClass(string $className)
    {
        $tokens = explode("\\", $className);
        return end($tokens);
    }

    private static function convertNetteRegexCapturesToParenthesesBuilder(array $captures)
    {
        // convert the string assignments in $matches to an associative array
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
     * Used by preg_replace_callback to replace captures with "#[Param]" strings to mark the lines for future
     * replacement. Additionally stores the captures into an output array.
     * @param array $captures An array of captures, with empty captures as NULL (PREG_UNMATCHED_AS_NULL flag).
     * @param array $capturesList An output list for captures.
     * @return string Returns "#[Param]".
     */
    private static function netteRegexCaptureToAttributeCallback(array $captures, array &$capturesList)
    {
        $capturesList[] = $captures;
        $paramAttributeClass = self::shortenClass(Param::class);
        return "#[{$paramAttributeClass}]";
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

    public static function convertFile(string $path)
    {
        // read file and replace @Param annotations with attributes
        $content = file_get_contents($path);
        // Array that contains parentheses builders of all future generated attributes.
        // Filled dynamically with the preg_replace_callback callback.
        $capturesList = [];
        $withInterleavedAttributes = preg_replace_callback(
            self::$netteRegex,
            function ($matches) use (&$capturesList) {
                return self::netteRegexCaptureToAttributeCallback($matches, $capturesList);
            },
            $content,
            -1,
            $count,
            PREG_UNMATCHED_AS_NULL
        );

        // move the attribute lines below the comment block
        $lines = [];
        $attributeLinesBuffer = [];
        $usingsAdded = false;
        $paramAttributeClass = self::shortenClass(Param::class);
        $paramTypeClass = self::shortenClass(Type::class);
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $withInterleavedAttributes) as $line) {
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
            // detected the new attribute line, store it in the buffer and do not write it yet
            } elseif (preg_match("/#\[{$paramAttributeClass}/", $line) === 1) {
                $attributeLinesBuffer[] = $line;
            // detected the end of the comment block "*/", flush attribute lines
            } elseif (trim($line) === "*/") {
                $lines[] = $line;
                for ($i = 0; $i < count($attributeLinesBuffer); $i++) {
                    $parenthesesBuilder = self::convertNetteRegexCapturesToParenthesesBuilder($capturesList[$i]);
                    $attributeLine = "    #[{$paramAttributeClass}{$parenthesesBuilder->toString()}]";
                    // change to multiline if the line is too long
                    if (strlen($attributeLine) > 120) {
                        $attributeLine = "    #[{$paramAttributeClass}{$parenthesesBuilder->toMultilineString(4)}]";
                    }
                    $lines[] = $attributeLine;
                }

                $attributeLinesBuffer = [];
            } else {
                $lines[] = $line;
            }
        }

        ///TODO: add usings for used validators
        ///TODO: handle too long lines
        return implode("\n", $lines);
    }
}
