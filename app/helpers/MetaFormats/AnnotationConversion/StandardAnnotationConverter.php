<?php

namespace App\Helpers\MetaFormats\AnnotationConversion;

use App\Exceptions\InternalServerException;
use App\Helpers\Swagger\AnnotationHelper;
use App\Helpers\Swagger\AnnotationParameterData;
use ReflectionMethod;

class StandardAnnotationConverter
{
    /**
     * Metadata about endpoints used to determine what class methods are endpoints and what params
     * are path and query. Initialized lazily (it cannot be assigned here because it is not a constant expression).
     * @var ?array An array of dictionaries with "route", "class", and "method" keys. Each dictionary
     *  represents an endpoint.
     */
    private static array $routesMetadata = null;

    /**
     * Converts standard PHP annotations (@param) of a presenter to attributes.
     * @param string $path The path to the presenter file.
     * @throws \App\Exceptions\InternalServerException
     * @return string Returns the converted presenter file content.
     */
    public static function convertStandardAnnotations(string $path): string
    {
        // initialize the metadata structure
        if (self::$routesMetadata == null) {
            self::$routesMetadata = AnnotationHelper::getRoutesMetadata();
        }

        // get fully qualified class name of the presenter
        $presenterNamespace = Utils::getPresenterNamespace();
        $className = $presenterNamespace . "\\" . basename($path, ".php");

        // get endpoint metadata for this file
        $endpoints = array_filter(self::$routesMetadata, function ($route) use ($className) {
            return $route["class"] == $className;
        });

        // add info about where the method starts and ends
        foreach ($endpoints as &$endpoint) {
            $reflectionMethod = new ReflectionMethod($endpoint["class"], $endpoint["method"]);
            // the method returns the line indexed from 1
            $endpoint["startLine"] = $reflectionMethod->getStartLine() - 1;
            $endpoint["endLine"] = $reflectionMethod->getEndLine() - 1;
        }

        // sort endpoints based on position in the file (so that the file preprocessing can be done top-down)
        $startLines = array_column($endpoints, "startLine");
        array_multisort($startLines, SORT_ASC, $endpoints);

        // get file lines
        $content = file_get_contents($path);
        $lines = Utils::fileStringToLines($content);

        // creates a list of replacement annotation blocks and their extends, keyed by original annotation start lines
        $annotationReplacements = self::convertEndpointAnnotations($endpoints, $lines);

        // replace original annotations with the new ones
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
            // move $i to the original annotation end line (skip original annotations)
            $i = $annotationReplacements[$i]["originalAnnotationEndLine"];
        }

        return implode("\n", $newLines);
    }

    /**
     * Converts endpoint annotations to annotations with parameter attributes.
     * @param array $endpoints Endpoint method metadata sorted by line number.
     * @param array $lines Lines of the file to be converted.
     * @throws \App\Exceptions\InternalServerException
     * @return array A list of dictionaries containing the new annotation lines and the end line
     *  of the original annotations.
     */
    private static function convertEndpointAnnotations(array $endpoints, array $lines): array
    {
        $annotationReplacements = [];
        foreach ($endpoints as $endpoint) {
            // get info about endpoint parameters and their types
            $annotationData = AnnotationHelper::extractStandardAnnotationData(
                $endpoint["class"],
                $endpoint["method"],
                $endpoint["route"]
            );

            // find start and end lines of method annotations
            $annotationEndLine = $endpoint["startLine"] - 1;
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

            // get all annotation lines for the endoint
            $annotationLines = array_slice($lines, $annotationStartLine, $annotationEndLine - $annotationStartLine + 1);
            $params = $annotationData->getAllParams();

            foreach ($params as $param) {
                // matches the line containing the parameter name with word boundaries
                $paramLineRegex = "/\\$\\b" . $param->name . "\\b/";
                $lineIdx = -1;
                for ($i = 0; $i < count($annotationLines); $i++) {
                    if (preg_match($paramLineRegex, $annotationLines[$i]) === 1) {
                        $lineIdx = $i;
                        break;
                    }
                }

                // the endpoint is missing the annotation for the parameter, skip the parameter
                if ($lineIdx == -1) {
                    continue;
                }

                // length of the param annotation in lines
                $paramAnnotationLength = 1;
                // matches lines starting with an asterisks not continued by the @ symbol
                $paramContinuationRegex = "/\h*\*\h+[^@]/";
                // find out how long the parameter annotation is
                for ($i = $lineIdx + 1; $i < count($annotationLines); $i++) {
                    if (preg_match($paramContinuationRegex, $annotationLines[$i]) === 1) {
                        $paramAnnotationLength += 1;
                    } else {
                        break;
                    }
                }

                // remove param annotations
                array_splice($annotationLines, $lineIdx, $paramAnnotationLength);
            }

            // crate an attribute from each parameter
            foreach ($params as $param) {
                // append the attribute line to the existing annotations
                $annotationLines[] = self::getAttributeLineFromMetadata($param);
            }

            $annotationReplacements[$annotationStartLine] = [
                "annotations" => $annotationLines,
                "originalAnnotationEndLine" => $annotationEndLine,
            ];
        }

        return $annotationReplacements;
    }

    /**
     * Converts parameter metadata into an attribute string.
     * @param \App\Helpers\Swagger\AnnotationParameterData $param The parameter metadata.
     * @return string The attribute string.
     */
    private static function getAttributeLineFromMetadata(AnnotationParameterData $param): string
    {
        // convert metadata to nette regex capture dictionary
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

        $builder = NetteAnnotationConverter::convertRegexCapturesToParenthesesBuilder($data);
        $paramAttributeClass = Utils::getAttributeClassFromString($data["type"]);
        $attributeLine = "    #[{$paramAttributeClass}{$builder->toString()}]";
        // change to multiline if the line is too long
        if (strlen($attributeLine) > 120) {
            $attributeLine = "    #[{$paramAttributeClass}{$builder->toMultilineString(4)}]";
        }

        return $attributeLine;
    }
}
