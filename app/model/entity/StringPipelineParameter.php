<?php

namespace App\Model\Entity;

use App\Exceptions\InvalidArgumentException;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class StringPipelineParameter extends PipelineParameter
{
    /**
     * @ORM\Column(type="string")
     */
    protected $stringValue;

    public function getValue()
    {
        return $this->stringValue;
    }

    public function setValue($value)
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf("Invalid value for parameter %s", $this->name));
        }

        $this->stringValue = $value;
    }
}
