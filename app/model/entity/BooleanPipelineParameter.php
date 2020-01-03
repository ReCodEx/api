<?php

namespace App\Model\Entity;

use App\Exceptions\InvalidArgumentException;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class BooleanPipelineParameter extends PipelineParameter
{
    /**
     * @ORM\Column(type="boolean")
     */
    protected $booleanValue;

    public function getValue()
    {
        return $this->booleanValue;
    }

    public function setValue($value)
    {
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($value === null) {
            throw new InvalidArgumentException(sprintf("Invalid value for parameter %s", $this->name));
        }

        $this->booleanValue = $value;
    }
}
