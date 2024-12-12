<?php

namespace App\Helpers\Swagger;

/**
 * Contains data of a single annotation parameter.
 */
class AnnotationParameterData
{
    public string | null $dataType;
    public string $name;
    public string | null $description;
    public string $location;

    private static $nullableSuffix = '|null';
    private static $typeMap = [
      'bool' => 'boolean',
      'boolean' => 'boolean',
      'array' => 'array',
      'int' => 'integer',
      'integer' => 'integer',
      'float' => 'number',
      'number' => 'number',
      'numeric' => 'number',
      'numericint' => 'integer',
      'timestamp' => 'integer',
      'string' => 'string',
      'unicode' => 'string',
      'email' => 'string',
      'url' => 'string',
      'uri' => 'string',
      'pattern' => null,
      'alnum' => 'string',
      'alpha' => 'string',
      'digit' => 'string',
      'lower' => 'string',
      'upper' => 'string',
    ];

    public function __construct(
        string | null $dataType,
        string $name,
        string | null $description,
        string $location
    ) {
        $this->dataType = $dataType;
        $this->name = $name;
        $this->description = $description;
        $this->location = $location;
    }

    private function isDatatypeNullable(): bool
    {
        // if the dataType is not specified (it is null), it means that the annotation is not
        // complete and defaults to a non nullable string
        if ($this->dataType === null) {
            return false;
        }

        // assumes that the typename ends with '|null'
        if (str_ends_with($this->dataType, self::$nullableSuffix)) {
            return true;
        }

        return false;
    }

    /**
     * Returns the swagger type associated with the annotation data type.
     * @return string Returns the name of the swagger type.
     */
    private function getSwaggerType(): string
    {
        // if the type is not specified, default to a string
        $type = 'string';
        $typename = $this->dataType;
        if ($typename !== null) {
            if ($this->isDatatypeNullable()) {
                $typename = substr($typename, 0, -strlen(self::$nullableSuffix));
            }
  
            if (self::$typeMap[$typename] === null) {
                ///TODO: Return the commented exception below once the meta-view formats are implemented.
                /// This detaults to strings because custom types like 'email' are not supported yet.
                return 'string';
            }
            //throw new \InvalidArgumentException("Error in getSwaggerType: Unknown typename: {$typename}");
          
            $type = self::$typeMap[$typename];
        }
        return $type;
    }

    /**
     * Generates swagger schema annotations based on the data type.
     * @return string Returns the annotation.
     */
    private function generateSchemaAnnotation(): string
    {
        $head = "@OA\\Schema";
        $body = new ParenthesesBuilder();

        $body->addKeyValue("type", $this->getSwaggerType());
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
        $body->addKeyValue("required", !$this->isDatatypeNullable());
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

        ///TODO: Once the meta-view formats are implemented, add support for property nullability here.
        $body->addKeyValue("property", $this->name);
        $body->addKeyValue("type", $this->getSwaggerType());
        return $head . $body->toString();
    }
}
