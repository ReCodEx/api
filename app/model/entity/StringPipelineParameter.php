<?php

namespace App\Model\Entity;

use App\Exceptions\InvalidApiArgumentException;
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
            throw new InvalidApiArgumentException($this->name, "String value expected");
        }

        $this->stringValue = $value;
    }
}
