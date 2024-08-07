<?php

namespace App\Model\Entity;

use Exception;
use ReflectionClass;

trait FlagAccessor
{
    /**
     * Get arbitrary bool flag by its name.
     * @param string $name
     * @return bool
     */
    public function getFlag(string $name): bool
    {
        if (!property_exists($this, $name) || !is_bool($this->$name)) {
            throw new Exception("Attempting to set unknown flag '$name' in the {$this->getShortClassName()}.");
        }
        return $this->$name;
    }

    /**
     * Set arbitrary bool flag by its name.
     * @param string $name
     * @param bool $value
     */
    public function setFlag(string $name, bool $value)
    {
        if (!property_exists($this, $name) || !is_bool($this->$name)) {
            throw new Exception("Attempting to set unknown flag '$name' in the {$this->getShortClassName()}.");
        }
        $this->$name = $value;
    }

    public function canSetFlag(string $name, bool $value): bool
    {
        if (!property_exists($this, $name) || !is_bool($this->$name)) {
            return false;
        }

        $checkMethod = 'canSet' . ucfirst($name); // if method canSet<FlagName> exist, call it
        if (method_exists($this, "$checkMethod")) {
            return (bool)$this->$checkMethod($value);
        }

        return true; // no checker -> flag can be set
    }

    private function getShortClassName(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }
}
