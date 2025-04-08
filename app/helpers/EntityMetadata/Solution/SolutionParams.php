<?php

namespace App\Helpers\EntityMetadata\Solution;

use App\Exceptions\ParseException;

/**
 * Contains information submitted usually by user who submitted solution, these
 * data can be used in further resubmitting of the solution.
 */
class SolutionParams
{
    public const VARIABLES_KEY = "variables";

    /**
     * @var SubmitVariable[]
     */
    private $variables = [];

    /**
     * SolutionParams constructor.
     * @param array $data
     * @throws ParseException
     */
    public function __construct($data = [])
    {
        if (!is_array($data)) {
            return;
        }

        if (array_key_exists(self::VARIABLES_KEY, $data) && is_array($data[self::VARIABLES_KEY])) {
            foreach ($data[self::VARIABLES_KEY] as $variable) {
                $submitVariable = new SubmitVariable($variable);
                $this->variables[$submitVariable->getName()] = $submitVariable;
            }
        }
    }

    /**
     * @return SubmitVariable[]
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * @param string $name
     * @return null|SubmitVariable
     */
    public function getVariable(string $name): ?SubmitVariable
    {
        if (!array_key_exists($name, $this->variables)) {
            return null;
        }

        return $this->variables[$name];
    }

    /**
     * Add submit variable to params.
     * @param SubmitVariable $variable
     */
    public function addVariable(SubmitVariable $variable)
    {
        $this->variables[$variable->getName()] = $variable;
    }

    public function toArray(): array
    {
        if (empty($this->variables)) {
            return [];
        }

        $result = [self::VARIABLES_KEY => []];
        foreach ($this->variables as $variable) {
            $result[self::VARIABLES_KEY][] = $variable->toArray();
        }
        return $result;
    }
}
