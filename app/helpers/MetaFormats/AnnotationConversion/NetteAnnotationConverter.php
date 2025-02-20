<?php

namespace App\Helpers\MetaFormats\AnnotationConversion;

use App\Exceptions\InternalServerException;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VFloat;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Helpers\Swagger\ParenthesesBuilder;

class NetteAnnotationConverter
{
    /**
     * A regex that matches @Param annotations and captures its parameters. Can capture up to 7 parameters.
     * Contains 6 copies of the following sub-regex: '(?:([a-z]+?=.+?),?\s*\*?\s*)?', which
     *   matches 'name=value' assignments followed by an optional comma, whitespace,
     *   star (multi-line annotation support), and whitespace. The capture contains only 'name=value'.
     * The regex ends with '([a-z]+?=.+)\)', which is similar to the above, but instead of ending with
     *   an optional comma etc., it ends with the closing parentheses of the @Param annotation.
     */
    private static string $paramRegex = "/\*\s*@Param\((?:([a-z]+?=.+?),?\s*\*?\s*)?(?:([a-z]+?=.+?),?\s*\*?\s*)?(?:([a-z]+?=.+?),?\s*\*?\s*)?(?:([a-z]+?=.+?),?\s*\*?\s*)?(?:([a-z]+?=.+?),?\s*\*?\s*)?(?:([a-z]+?=.+?),?\s*\*?\s*)?([a-z]+?=.+)\)/";

    // placeholder for detected nette annotations (prefixed with "@Param")
    // this text must not be present in the presenter files
    public static string $attributePlaceholder = "<!>#nette#<!>";


    /**
     * Replaces "@Param" annotations with placeholders and extracts its data.
     * @param string $fileContent The file content to be replaced.
     * @return array{captures: array, contentWithPlaceholders: string} Returns the content with placeholders and the
     * extracted data.
     */
    public static function regexReplaceAnnotations(string $fileContent)
    {
        // Array that contains parentheses builders of all future generated attributes.
        // Filled dynamically with the preg_replace_callback callback.
        $captures = [];

        $contentWithPlaceholders = preg_replace_callback(
            self::$paramRegex,
            function ($matches) use (&$captures) {
                return self::regexCaptureToAttributeCallback($matches, $captures);
            },
            $fileContent,
            flags: PREG_UNMATCHED_AS_NULL
        );

        return [
            "contentWithPlaceholders" => $contentWithPlaceholders,
            "captures" => $captures,
        ];
    }

    /**
     * Converts regex parameter captures to an attribute string.
     * @param array $captures Regex parameter captures.
     * @return string Returns the attribute string.
     */
    public static function convertCapturesToAttributeString(array $captures)
    {

        $annotationParameters = NetteAnnotationConverter::convertCapturesToDictionary($captures);
        $paramAttributeClass = Utils::getAttributeClassFromString($annotationParameters["type"]);
        $parenthesesBuilder = NetteAnnotationConverter::convertRegexCapturesToParenthesesBuilder($annotationParameters);
        $attributeLine = "    #[{$paramAttributeClass}{$parenthesesBuilder->toString()}]";
        // change to multiline if the line is too long
        if (strlen($attributeLine) > 120) {
            $attributeLine = "    #[{$paramAttributeClass}{$parenthesesBuilder->toMultilineString(4)}]";
        }
        return $attributeLine;
    }

    /**
     * Converts regex parameter captures into a dictionary.
     * @param array $captures The regex captures.
     * @throws \App\Exceptions\InternalServerException
     * @return array Returns a dictionary with field names as keys pointing to values.
     */
    private static function convertCapturesToDictionary(array $captures)
    {
        // convert the string assignments in $captures to a dictionary
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

    /**
     * Used by preg_replace_callback to replace "@Param" annotation captures with placeholder strings to mark the
     * lines for future replacement. Additionally stores the captures into an output array.
     * @param array $captures An array of captures, with empty captures as NULL (PREG_UNMATCHED_AS_NULL flag).
     * @param array $capturesList An output list for captures.
     * @return string Returns a placeholder.
     */
    private static function regexCaptureToAttributeCallback(array $captures, array &$capturesList)
    {
        $capturesList[] = $captures;
        return self::$attributePlaceholder;
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
            $stringValidator = Utils::shortenClass(VString::class);

            // handle string length constraints, such as "string:1..255"
            if (strlen($validation) > 6) {
                if ($validation[6] !== ":") {
                    throw new InternalServerException("Unknown string validation format: $validation");
                }
                $suffix = substr($validation, 7);

                // special case for uuids
                if ($suffix === "36") {
                    return "new " . Utils::shortenClass(VUuid::class) . "()";
                }

                // capture the two bounding numbers and the double dot in strings of
                // types "1..255", "..255", "1..", or "255"
                if (preg_match("/([0-9]*)(..)?([0-9]+)?/", $suffix, $matches, PREG_UNMATCHED_AS_NULL) !== 1) {
                    throw new InternalServerException("Unknown string validation format: $validation");
                }

                // type "255", exact match
                if ($matches[2] == null) {
                    return "new {$stringValidator}({$matches[1]}, {$matches[1]})";
                // type "1..255"
                } elseif ($matches[1] != null && $matches[3] !== null) {
                    return "new {$stringValidator}({$matches[1]}, {$matches[3]})";
                // type "..255"
                } elseif ($matches[1] == null) {
                    return "new {$stringValidator}(0, {$matches[3]})";
                // type "1.."
                } elseif ($matches[3] == null) {
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
            case "mixed":
                $validatorClass = VMixed::class;
                break;
            default:
                throw new InternalServerException("Unknown validation rule: $validation");
        }

        return "new " . Utils::shortenClass($validatorClass) . "()";
    }

    /**
     * Convers a parameter dictionary into an attribute string builder.
     * @param array $annotationParameters An associative array with a subset of the following keys:
     *  name, validation, description, required, nullable.
     * @throws \App\Exceptions\InternalServerException
     * @return ParenthesesBuilder A string builder used to build the final attribute string.
     */
    public static function convertRegexCapturesToParenthesesBuilder(array $annotationParameters)
    {
        // serialize the parameters to an attribute
        $parenthesesBuilder = new ParenthesesBuilder();

        // add name
        if (!array_key_exists("name", $annotationParameters)) {
            throw new InternalServerException("Missing name parameter.");
        }
        $parenthesesBuilder->addValue("\"{$annotationParameters["name"]}\"");

        $nullable = false;
        // replace missing validations with placeholder validations
        if (!array_key_exists("validation", $annotationParameters)) {
            $annotationParameters["validation"] = "mixed";
            // missing validations imply nullability
            $nullable = true;
        }
        $validation = $annotationParameters["validation"];

        if (Utils::checkValidationNullability($validation)) {
            // remove the '|null' from the end of the string
            $validation = substr($validation, 0, -5);
            $nullable = true;
        }
        // this will always produce a single validator (the annotations do not contain multiple validation fields)
        $validator = self::convertAnnotationValidationToValidatorString($validation);
        $parenthesesBuilder->addValue(value: $validator);

        if (array_key_exists("description", $annotationParameters)) {
            $description = $annotationParameters["description"];
            // escape all quotes and dollar signs
            $description = str_replace("\"", "\\\"", $description);
            $description = str_replace("$", "\\$", $description);
            $parenthesesBuilder->addValue(value: "\"{$description}\"");
        }

        if (array_key_exists("required", $annotationParameters)) {
            $parenthesesBuilder->addValue("required: " . $annotationParameters["required"]);
        }

        if ($nullable) {
            $parenthesesBuilder->addValue("nullable: true");
        }

        return $parenthesesBuilder;
    }
}
