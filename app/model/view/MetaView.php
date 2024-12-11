<?php

namespace App\Model\View;

use App\Helpers\Swagger\AnnotationHelper;
use App\Helpers\MetaFormats\MetaFormatHelper;
use App\Exceptions\InternalServerException;

// parent class of all meta classes

class MetaView
{
    /**
     * Extracts the parameters and annotations of the calling function and converts the parameters to their target type.
     * @return object[] Returns a map paramName=>targetTypeInstance, where the instances are filled with the
     * data from the parameters.
     */
    public function getTypedParams()
    {
        // extract function params of the caller
        $backtrace = debug_backtrace()[1];
        $className = $backtrace['class'];
        $methodName = $backtrace['function'];
        // get param values
        $paramsToValues = $this->getParamNamesToValuesMap($backtrace);
        // get param format
        $paramsToFormat = MetaFormatHelper::extractMethodCheckedParams($className, $methodName);

        // get all format definitions
        $formats = MetaFormatHelper::getFormatDefinitions();
        var_dump($formats);

        $paramToTypedMap = [];
        foreach ($paramsToValues as $paramName => $paramValue) {

            // the parameter name was not present in the annotations
            if (!array_key_exists($paramName, $paramsToFormat)) {
                throw new InternalServerException("Unknown method parameter format: $paramName\n");
            }

            $format = $paramsToFormat[$paramName];

            // the format is not defined
            if (!array_key_exists($format, $formats)) {
                throw new InternalServerException("The format does not have a definition class: $format\n");
            }

            $targetClassName = $formats[$format];
            $classFormat = MetaFormatHelper::getClassFormats($targetClassName);
            $obj = new $targetClassName();

            // fill the new object with the param values
            ///TODO: handle nested formated objects
            foreach ($paramValue as $propertyName => $propertyValue) {
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

    private function getParamNamesToValuesMap($backtrace): array
    {
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
