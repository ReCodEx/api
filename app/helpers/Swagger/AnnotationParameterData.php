<?php

namespace App\Helpers\Swagger;

use App\Exceptions\InternalServerException;
use App\Helpers\MetaFormats\FileRequestType;
use App\Helpers\MetaFormats\Type;

/**
 * Contains data of a single annotation parameter.
 * Used for swagger generation.
 */
class AnnotationParameterData
{
    public string $swaggerType;
    public string $name;
    public ?string $description;
    public string $location;
    public bool $required;
    public bool $nullable;
    public ?string $example;
    public ?string $nestedArraySwaggerType;
    public ?int $arrayDepth;
    public ?array $nestedObjectParameterData;
    public ?ParameterConstraints $constraints;
    public ?FileRequestType $fileRequestType;

    public function __construct(
        string $swaggerType,
        string $name,
        ?string $description,
        string $location,
        bool $required,
        bool $nullable,
        ?string $example = null,
        ?string $nestedArraySwaggerType = null,
        ?int $arrayDepth = null,
        ?array $nestedObjectParameterData = null,
        ?ParameterConstraints $constraints = null,
        ?FileRequestType $fileRequestType = null,
    ) {
        $this->swaggerType = $swaggerType;
        $this->name = $name;
        $this->description = $description;
        $this->location = $location;
        $this->required = $required;
        $this->nullable = $nullable;
        $this->example = $example;
        $this->nestedArraySwaggerType = $nestedArraySwaggerType;
        $this->arrayDepth = $arrayDepth;
        $this->nestedObjectParameterData = $nestedObjectParameterData;
        $this->constraints = $constraints;
        $this->fileRequestType = $fileRequestType;
    }

    private function addArrayItemsIfArray(ParenthesesBuilder $container)
    {
        if ($this->swaggerType !== "array") {
            return;
        }

        $itemsHead = "new OA\\Schema";
        $layers = [];
        for ($i = 0; $i < $this->arrayDepth; $i++) {
            $items = new ParenthesesBuilder();

            // add array time for all nested arrays
            if ($i < $this->arrayDepth - 1) {
                $items->addKeyValue("type", "array");
                // handle bottommost elements
            } else {
                // add element type if present
                if ($this->nestedArraySwaggerType !== null) {
                    $items->addKeyValue("type", $this->nestedArraySwaggerType);
                }

                // add example value
                if ($this->example != null) {
                    $items->addKeyValue("example", $this->example);
                }

                // add constraints
                $this->constraints?->addConstraints($items);
            }

            $layers[] = $items;
        }

        // serialize the layers from the bottom up
        $layers = array_reverse($layers);
        $serialized_layer = $itemsHead . $layers[0]->toString();
        for ($i = 1; $i < $this->arrayDepth; $i++) {
            $layer = $layers[$i];
            $layer->addKeyRaw("items", $serialized_layer);
            $serialized_layer = $itemsHead . $layer->toString();
        }

        $container->addKeyRaw("items", $serialized_layer);
    }

    private function addObjectParamsIfObject(ParenthesesBuilder $container)
    {
        if ($this->nestedObjectParameterData === null) {
            return;
        }

        $properties = [];
        foreach ($this->nestedObjectParameterData as $paramData) {
            $properties[] = $paramData->toPropertyAnnotation();
        }

        if (count($properties) > 0) {
            $container->addKeyArray("properties", $properties);
        }
    }

    private function addFileParamsIfFile(ParenthesesBuilder $container)
    {
        if ($this->location !== strtolower(Type::File->name)) {
            return;
        }

        $container->addKeyValue("format", "binary");
    }

    /**
     * Generates swagger schema attributes based on the data type.
     * @return string Returns the attribute expression.
     */
    private function generateSchemaAnnotation(): string
    {
        $head = "new OA\\Schema";
        $body = new ParenthesesBuilder();

        $body->addKeyValue("type", $this->swaggerType);
        $body->addKeyValue("nullable", $this->nullable);

        if ($this->swaggerType !== "array") {
            $this->constraints?->addConstraints($body);
        }

        $this->addArrayItemsIfArray($body);
        $this->addObjectParamsIfObject($body);

        return $head . $body->toString();
    }

    /**
     * Converts the object to a 'new OA\Parameter(...)' expression string
     */
    public function toParameterAnnotation(): string
    {
        $head = "new OA\\Parameter";
        $body = new ParenthesesBuilder();

        $body->addKeyValue("name", $this->name);
        $body->addKeyValue("in", $this->location);
        $body->addKeyValue("required", $this->required);

        // add flags for query parameters with objects that make the client generate correct endpoints
        if ($this->nestedObjectParameterData !== null) {
            $body->addKeyValue("style", "deepObject");
            $body->addKeyValue("explode", true);
        }

        if ($this->description !== null) {
            $body->addKeyValue("description", $this->description);
        }

        $body->addKeyRaw("schema", $this->generateSchemaAnnotation());

        return $head . $body->toString();
    }

    /**
     * Generates swagger property attributes based on the data type.
     * @return string Returns the attribute expression.
     */
    public function toPropertyAnnotation(): string
    {
        $head = "new OA\\Property";
        $body = new ParenthesesBuilder();
        $body->addKeyValue("property", $this->name);

        // the property type and constraints are defined by its schema
        $schema = new ParenthesesBuilder();
        $schema->addKeyValue("type", $this->swaggerType);
        $schema->addKeyValue("nullable", $this->nullable);

        if ($this->description !== null) {
            $schema->addKeyValue("description", $this->description);
        }

        // handle param constraints (array constrains have to be added to the element, not the array)
        if ($this->swaggerType !== "array") {
            $this->constraints?->addConstraints($schema);
        }

        // handle arrays
        $this->addArrayItemsIfArray($schema);

        // handle objects
        $this->addObjectParamsIfObject($schema);

        // handle files
        $this->addFileParamsIfFile($schema);

        // add example value
        if ($this->swaggerType !== "array" && $this->swaggerType !== "object") {
            if ($this->example != null) {
                $schema->addKeyValue("example", $this->example);
            }
        }

        $body->addKeyRaw("schema", "new OA\\Schema" . $schema->toString());

        return $head . $body->toString();
    }
}
