<?php

namespace App\Helpers\Swagger;

/**
 * Builder class that can create strings of the schema: '(key1="value1", key2="value2", standalone1, standalone2, ...)'
 */
class ParenthesesBuilder
{
    private array $tokens;

    public function __construct()
    {
        $this->tokens = [];
    }

    /**
     * Add a token inside the parentheses in the format of: key="value"
     * @param string $key A string key.
     * @param mixed $value A value that will be stringified.
     * @return \App\Helpers\Swagger\ParenthesesBuilder Returns the builder object.
     */
    public function addKeyValue(string $key, mixed $value): ParenthesesBuilder
    {
        $valueString = strval($value);
        // strings need to be wrapped in quotes
        if (is_string($value)) {
            $valueString = "\"{$value}\"";
        // convert bools to strings
        } elseif (is_bool($value)) {
            $valueString = ($value ? "true" : "false");
        }

        $assignment = "{$key}={$valueString}";
        return $this->addValue($assignment);
    }

    /**
     * Add a string token inside the parentheses.
     * @param string $value The token to be added.
     * @return \App\Helpers\Swagger\ParenthesesBuilder Returns the builder object.
     */
    public function addValue(string $value): ParenthesesBuilder
    {
        $this->tokens[] = $value;
        return $this;
    }

    public function toString(): string
    {
        return "(" . implode(", ", $this->tokens) . ")";
    }

    private static function spaces(int $count): string
    {
        return str_repeat(" ", $count);
    }

    private const CODEBASE_INDENTATION = 4;
    public function toMultilineString(int $initialIndentation): string
    {
        // do not add indentation to the first line
        $str = "(\n";
        foreach ($this->tokens as $token) {
            $str .= self::spaces($initialIndentation + self::CODEBASE_INDENTATION) . $token . ",\n";
        }
        $str .= self::spaces($initialIndentation) . ")";
        return $str;
    }
}
