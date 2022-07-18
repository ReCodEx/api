<?php

namespace App\Helpers\ExerciseConfig;

use App\Helpers\ExerciseConfig\Pipeline\Box\Box;
use App\Helpers\ExerciseConfig\Pipeline\Box\DataInBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\DataOutBox;
use JsonSerializable;
use App\Helpers\Yaml;

/**
 * Represents pipeline which contains list of boxes.
 */
class Pipeline implements JsonSerializable
{
    /** Name of the boxes key */
    const BOXES_KEY = "boxes";
    /** Name of the variables key */
    const VARIABLES_KEY = "variables";


    /**
     * @var array
     */
    protected $dataInBoxes = array();
    /**
     * @var array
     */
    protected $dataOutBoxes = array();
    /**
     * @var array
     */
    protected $otherBoxes = array();
    /**
     * Contains all boxes including DataIn and DataOut.
     * @var array
     */
    protected $boxes = array();

    /**
     * @var VariablesTable
     */
    protected $variablesTable;


    /**
     * Pipeline constructor.
     */
    public function __construct()
    {
        $this->variablesTable = new VariablesTable();
    }

    /**
     * True if internal list contains box identified with given key.
     * @param string $key
     * @return bool
     */
    public function contains(string $key): bool
    {
        return array_key_exists($key, $this->boxes);
    }

    /**
     * Returns box with specified key, if there is none, return null.
     * @param string $key
     * @return Box|null
     */
    public function get(string $key): ?Box
    {
        if (array_key_exists($key, $this->boxes)) {
            return $this->boxes[$key];
        }

        return null;
    }

    /**
     * If list contains box with the same name as the given one, original box
     * is replaced by the new one.
     * @param Box $box
     * @return Pipeline
     */
    public function set(Box $box): Pipeline
    {
        if ($box instanceof DataInBox) {
            unset($this->dataOutBoxes[$box->getName()]);
            unset($this->otherBoxes[$box->getName()]);
            $this->dataInBoxes[$box->getName()] = $box;
        } else {
            if ($box instanceof DataOutBox) {
                unset($this->dataInBoxes[$box->getName()]);
                unset($this->otherBoxes[$box->getName()]);
                $this->dataOutBoxes[$box->getName()] = $box;
            } else {
                unset($this->dataInBoxes[$box->getName()]);
                unset($this->dataOutBoxes[$box->getName()]);
                $this->otherBoxes[$box->getName()] = $box;
            }
        }

        $this->boxes[$box->getName()] = $box;
        return $this;
    }

    /**
     * Remove box with given key.
     * @param string $key
     * @return Pipeline
     */
    public function remove(string $key): Pipeline
    {
        unset($this->dataInBoxes[$key]);
        unset($this->dataOutBoxes[$key]);
        unset($this->otherBoxes[$key]);
        unset($this->boxes[$key]);
        return $this;
    }

    /**
     * Get all boxes in pipeline.
     * @return Box[]
     */
    public function getAll(): array
    {
        return $this->boxes;
    }

    /**
     * Get input data boxes for this pipeline.
     * @return DataInBox[]
     */
    public function getDataInBoxes(): array
    {
        return $this->dataInBoxes;
    }

    /**
     * Get output data boxes for this pipeline.
     * @return DataOutBox[]
     */
    public function getDataOutBoxes(): array
    {
        return $this->dataOutBoxes;
    }

    /**
     * Get boxes list which excludes data boxes.
     * @return Box[]
     */
    public function getOtherBoxes(): array
    {
        return $this->otherBoxes;
    }

    /**
     * Return count of the boxes in this pipeline.
     * @return int
     */
    public function size(): int
    {
        return count($this->boxes);
    }

    /**
     * Get variables table.
     * @return VariablesTable
     */
    public function getVariablesTable(): VariablesTable
    {
        return $this->variablesTable;
    }

    /**
     * Set variables table.
     * @param VariablesTable $variablesTable
     * @return Pipeline
     */
    public function setVariablesTable(VariablesTable $variablesTable): Pipeline
    {
        $this->variablesTable = $variablesTable;
        return $this;
    }


    /**
     * Creates and returns properly structured array representing this object.
     * @return array
     */
    public function toArray(): array
    {
        $data = [];

        $data[self::BOXES_KEY] = array();
        foreach ($this->boxes as $value) {
            $data[self::BOXES_KEY][] = $value->toArray();
        }

        $data[self::VARIABLES_KEY] = $this->variablesTable->toArray();

        return $data;
    }

    /**
     * Serialize the config.
     * @return string
     */
    public function __toString(): string
    {
        return Yaml::dump($this->toArray());
    }

    /**
     * Enable automatic serialization to JSON
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
