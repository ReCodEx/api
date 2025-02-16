<?php

namespace App\Helpers\MetaFormats\AnnotationConversion;

use App\Exceptions\InternalServerException;
use App\Helpers\MetaFormats\Attributes\Param;
use App\Helpers\Swagger\AnnotationHelper;
use App\V1Module\Presenters\BasePresenter;
use ReflectionMethod;

class StandardAnnotationConverter
{
    // Metadata about endpoints used to determine what class methods are endpoints and what params
    // are path and query. Initialized lazily (it cannot be assigned here because it is not a constant expression).
    private static ?array $routesMetadata = null;

    public static function preprocessFile(string $path)
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
        $lines = Utils::fileStringToLines($content);

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

                $builder = NetteAnnotationConverter::convertRegexCapturesToParenthesesBuilder($data);
                $paramAttributeClass = Utils::shortenClass(Param::class);
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

        return Utils::linesToFileString($newLines);
    }
}
