<?php

namespace App\Helpers\MetaFormats\AnnotationConversion;

use App\Helpers\MetaFormats\Type;

class AnnotationToAttributeConverter
{
    /**
     * Converts the endpoint annotations in a presenter class file into attributes.
     * @param string $path The path to the presenter.
     * @return string Returns the converted file content as a string.
     */
    public static function convertFile(string $path): string
    {
        $content = StandardAnnotationConverter::convertStandardAnnotations($path);
        $nettePreprocess = NetteAnnotationConverter::regexReplaceAnnotations($content);

        $netteCapturesList = $nettePreprocess["captures"];
        $contentWithPlaceholders = $nettePreprocess["contentWithPlaceholders"];

        // move the attribute lines below the comment block
        $lines = [];
        $netteAttributeLinesCount = 0;
        $usingsAdded = false;
        $paramAttributeClasses = Utils::getParamAttributeClassNames();
        $paramTypeClass = Utils::shortenClass(Type::class);
        foreach (Utils::fileStringToLines($contentWithPlaceholders) as $line) {
            // detected the initial "use" block, add usings for new types
            if (!$usingsAdded && strlen($line) > 3 && substr($line, 0, 3) === "use") {
                // add usings for attributes
                foreach ($paramAttributeClasses as $class) {
                    $lines[] = "use App\\Helpers\\MetaFormats\\Attributes\\{$class};";
                }
                $lines[] = "use App\\Helpers\\MetaFormats\\{$paramTypeClass};";
                foreach (Utils::getValidatorNames() as $validator) {
                    $lines[] = "use App\\Helpers\\MetaFormats\\Validators\\{$validator};";
                }
                // write the detected line (the first detected "use" line)
                $lines[] = $line;
                $usingsAdded = true;
            // detected an attribute line placeholder, increment the counter and remove the line
            } elseif (str_contains($line, NetteAnnotationConverter::$attributePlaceholder)) {
                $netteAttributeLinesCount++;
            // detected the end of the comment block "*/", flush attribute lines
            } elseif (trim($line) === "*/") {
                $lines[] = $line;
                for ($i = 0; $i < $netteAttributeLinesCount; $i++) {
                    $lines[] = NetteAnnotationConverter::convertCapturesToAttributeString($netteCapturesList[$i]);
                }

                // remove the captures used in this endpoint
                $netteCapturesList = array_slice($netteCapturesList, $netteAttributeLinesCount);
                // reset the counters for the next detected endpoint
                $netteAttributeLinesCount = 0;
            } else {
                $lines[] = $line;
            }
        }

        return Utils::linesToFileString($lines);
    }
}
