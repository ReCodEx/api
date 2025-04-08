<?php

namespace App\Helpers\Swagger;

use App\Exceptions\InternalServerException;

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
    public ?array $nestedObjectParameterData;

    public function __construct(
        string $swaggerType,
        string $name,
        ?string $description,
        string $location,
        bool $required,
        bool $nullable,
        ?string $example = null,
        ?string $nestedArraySwaggerType = null,
        ?array $nestedObjectParameterData = null,
    ) {
        $this->swaggerType = $swaggerType;
        $this->name = $name;
        $this->description = $description;
        $this->location = $location;
        $this->required = $required;
        $this->nullable = $nullable;
        $this->example = $example;
        $this->nestedArraySwaggerType = $nestedArraySwaggerType;
        $this->nestedObjectParameterData = $nestedObjectParameterData;
    }

    private function addArrayItemsIfArray(ParenthesesBuilder $container)
    {
        if ($this->swaggerType !== "array") {
            return;
        }

        $itemsHead = "@OA\\Items";
        $items = new ParenthesesBuilder();

        if ($this->nestedArraySwaggerType !== null) {
            $items->addKeyValue("type", $this->nestedArraySwaggerType);
        }

        // add example value
        if ($this->example != null) {
            $items->addKeyValue("example", $this->example);
        }

        $container->addValue($itemsHead . $items->toString());
    }

    private function addObjectParamsIfObject(ParenthesesBuilder $container)
    {
        if ($this->nestedObjectParameterData === null) {
            return;
        }

        foreach ($this->nestedObjectParameterData as $paramData) {
            $annotation = $paramData->toPropertyAnnotation();
            $container->addValue($annotation);
        }
    }

    /**
     * Generates swagger schema annotations based on the data type.
     * @return string Returns the annotation.
     */
    private function generateSchemaAnnotation(): string
    {
        $head = "@OA\\Schema";
        $body = new ParenthesesBuilder();

        $body->addKeyValue("type", $this->swaggerType);
        $this->addArrayItemsIfArray($body);

        return $head . $body->toString();
    }

    /**
     * Converts the object to a @OA\Parameter(...) annotation string
     */
    public function toParameterAnnotation(): string
    {
        $head = "@OA\\Parameter";
        $body = new ParenthesesBuilder();

        $body->addKeyValue("name", $this->name);
        $body->addKeyValue("in", $this->location);
        $body->addKeyValue("required", $this->required);

        if ($this->description !== null) {
            $body->addKeyValue("description", $this->description);
        }

        $body->addValue($this->generateSchemaAnnotation());

        return $head . $body->toString();
    }

    /**
     * Generates swagger property annotations based on the data type.
     * @return string Returns the annotation.
     */
    public function toPropertyAnnotation(): string
    {
        $head = "@OA\\Property";
        $body = new ParenthesesBuilder();

        $body->addKeyValue("property", $this->name);
        $body->addKeyValue("type", $this->swaggerType);
        $body->addKeyValue("nullable", $this->nullable);

        if ($this->description !== null) {
            $body->addKeyValue("description", $this->description);
        }

        // handle arrays
        $this->addArrayItemsIfArray($body);

        // handle objects
        $this->addObjectParamsIfObject($body);

        // add example value
        if ($this->swaggerType !== "array" && $this->swaggerType !== "object") {
            if ($this->example != null) {
                $body->addKeyValue("example", $this->example);
            }
        }

        return $head . $body->toString();
    }
}
