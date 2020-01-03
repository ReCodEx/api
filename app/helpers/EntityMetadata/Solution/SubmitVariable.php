<?php

namespace App\Helpers\EntityMetadata\Solution;

use App\Exceptions\ParseException;

/**
 * Variable which was submitted by user during creation of solution.
 */
class SubmitVariable
{

    const NAME_KEY = "name";
    const VALUE_KEY = "value";

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $value;

    /**
     * SubmitVariable constructor.
     * @param array $data
     * @throws ParseException
     */
    public function __construct(array $data = [])
    {
        if (!array_key_exists(self::NAME_KEY, $data)) {
            throw new ParseException("Name is required in submitted variables");
        }
        $this->name = $data[self::NAME_KEY];

        if (!array_key_exists(self::VALUE_KEY, $data)) {
            throw new ParseException("Value is required in submitted variables");
        }
        $this->value = $data[self::VALUE_KEY];
    }

    /**
     * Get name of the submit variable.
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get value of the submit variable.
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    public function toArray(): array
    {
        return [
            self::NAME_KEY => $this->name,
            self::VALUE_KEY => $this->value
        ];
    }
}
