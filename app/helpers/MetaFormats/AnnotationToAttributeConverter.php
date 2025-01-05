<?php

namespace App\Helpers\MetaFormats;

use App\Exceptions\InternalServerException;
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
    private static string $postRegex = "/\*\s*@Param\((?:([a-z]+?=.+?),?\s*\*?\s*)?(?:([a-z]+?=.+?),?\s*\*?\s*)?(?:([a-z]+?=.+?),?\s*\*?\s*)?(?:([a-z]+?=.+?),?\s*\*?\s*)?(?:([a-z]+?=.+?),?\s*\*?\s*)?(?:([a-z]+?=.+?),?\s*\*?\s*)?([a-z]+?=.+)\)/";

    /**
     * Converts an array of preg_replace_callback matches to an attribute string.
     * @param array $matches An array of matches, with empty captures as NULL (PREG_UNMATCHED_AS_NULL flag).
     * @return string Returns an attribute string.
     */
    private static function regexCaptureToAttributeCallback(array $matches)
    {
        // convert the string assignments in $matches to an associative array
        $annotationParameters = [];
        // the first element is the matched string
        for ($i = 1; $i < count($matches); $i++) {
            $capture = $matches[$i];
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
        $typeStr = $annotationParameters["type"];
        $type = null;
        switch ($typeStr) {
            case "post":
                $type = "RequestParamType::Post";
                break;
            case "query":
                $type = "RequestParamType::Query";
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

        if (array_key_exists("description", $annotationParameters)) {
            $parenthesesBuilder->addValue("description: \"{$annotationParameters["description"]}\"");
        }

        if (array_key_exists("validation", $annotationParameters)) {
            $validator = self::convertAnnotationValidationToValidatorString($annotationParameters["validation"]);
            $parenthesesBuilder->addValue("validators: [ $validator ]");
        }

        if (array_key_exists("required", $annotationParameters)) {
            $parenthesesBuilder->addValue("required: " . $annotationParameters["required"]);
        }

        if (!array_key_exists("type", $annotationParameters)) {
            throw new InternalServerException("Missing type parameter.");
        }

        return "#[RequestParamAttribute{$parenthesesBuilder->toString()}]";
    }

    /**
     * Converts annotation validation values (such as "string:1..255") to Validator construction
     *   strings (such as "new StringValidator(1, 255)").
     * @param string $validation The annotation validation string.
     * @return string Returns the object construction string.
     */
    private static function convertAnnotationValidationToValidatorString(string $validation): string
    {
        if (str_starts_with($validation, "string")) {
            // handle string length constraints, such as "string:1..255"
            if (strlen($validation) > 6) {
                if ($validation[6] !== ":") {
                    throw new InternalServerException("Unknown string validation format: $validation");
                }
                $suffix = substr($validation, 7);

                // special case for uuids
                if ($suffix === "36") {
                    return "new UuidValidator()";
                }

                // capture the two bounding numbers and the double dot in strings of
                // types "1..255", "..255", "1..", or "255"
                if (preg_match("/([0-9]*)(..)?([0-9]+)?/", $suffix, $matches, PREG_UNMATCHED_AS_NULL) !== 1) {
                    throw new InternalServerException("Unknown string validation format: $validation");
                }

                // type "255", exact match
                if ($matches[2] === null) {
                    return "new StringValidator({$matches[1]}, {$matches[1]})";
                // type "1..255"
                } elseif ($matches[1] !== null && $matches[3] !== null) {
                    return "new StringValidator({$matches[1]}, {$matches[3]})";
                // type "..255"
                } elseif ($matches[1] === null) {
                    return "new StringValidator(0, {$matches[3]})";
                // type "1.."
                } elseif ($matches[3] === null) {
                    return "new StringValidator({$matches[1]})";
                }

                throw new InternalServerException("Unknown string validation format: $validation");
            }

            return "new StringValidator()";
        }

        switch ($validation) {
            case "email":
                return "new EmailValidator()";
            default:
                ///TODO
                return "\"UNSUPPORTED\"";
        }
    }

    public static function convertFile(string $path)
    {
        // read file and replace @Param annotations with attributes
        $content = file_get_contents($path);
        $withInterleavedAttributes = preg_replace_callback(self::$postRegex, function ($matches) {
            return self::regexCaptureToAttributeCallback($matches);
        }, $content, -1, $count, PREG_UNMATCHED_AS_NULL);

        // move the attribute lines below the comment block
        $lines = [];
        $attributeLinesBuffer = [];
        $usingsAdded = false;
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $withInterleavedAttributes) as $line) {
            // add usings for new types
            if (!$usingsAdded && strlen($line) > 3 && substr($line, 0, 3) === "use") {
                $lines[] = "use App\Helpers\MetaFormats\Attributes\RequestParamAttribute;";
                $lines[] = "use App\Helpers\MetaFormats\RequestParamType;";
                $lines[] = "use App\Helpers\MetaFormats\Validators\StringValidator;";
                $lines[] = "use App\Helpers\MetaFormats\Validators\EmailValidator;";
                $lines[] = "use App\Helpers\MetaFormats\Validators\UuidValidator;";
                $lines[] = $line;
                $usingsAdded = true;
            // store attribute lines in the buffer and do not write them
            } elseif (preg_match("/#\[RequestParamAttribute/", $line) === 1) {
                $attributeLinesBuffer[] = $line;
            // flush attribute lines
            } elseif (trim($line) === "*/") {
                $lines[] = $line;
                foreach ($attributeLinesBuffer as $attributeLine) {
                    // the attribute lines are shifted by one space to the right (due to the comment block origin)
                    $lines[] = substr($attributeLine, 1);
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
