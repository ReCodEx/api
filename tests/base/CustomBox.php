<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\BoxCategories;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\JobConfig\Tasks\Task;


/**
 * Customizable box whose ports can be modified. Default values for ports and
 * name are not implemented.
 * @note Should be used only for internal purposes.
 */
class CustomBox extends Box
{
    /** Type key */
    public static $CUSTOM_BOX_TYPE = "custom";
    /** Special custom box category */
    public static $CATEGORY = "custom";

    /**
     * For testing purposes.
     * @var Variable
     */
    private $inputVariable = null;

    /**
     * CustomBox constructor.
     * @param string $name
     */
    public function __construct(string $name = "")
    {
        parent::__construct((new BoxMeta())->setName($name));
    }


    /**
     * Set name of box.
     * @param string $name
     * @return CustomBox
     */
    public function setName(string $name): CustomBox
    {
        $this->meta->setName($name);
        return $this;
    }

    /**
     * Add input port of this box.
     * @param Port $port
     * @return CustomBox
     */
    public function addInputPort(Port $port): CustomBox
    {
        $this->meta->addInputPort($port);
        return $this;
    }

    /**
     * Clear input ports of this box.
     * @return CustomBox
     */
    public function clearInputPorts(): CustomBox
    {
        $this->meta->setInputPorts(array());
        return $this;
    }

    /**
     * Add output port of this box.
     * @param Port $port
     * @return CustomBox
     */
    public function addOutputPort(Port $port): CustomBox
    {
        $this->meta->addOutputPort($port);
        return $this;
    }

    /**
     * Clear output ports of this box.
     * @return CustomBox
     */
    public function clearOutputPorts(): CustomBox
    {
        $this->meta->setOutputPorts(array());
        return $this;
    }


    /**
     * Get remote variable.
     * @return Variable|null
     */
    public function getInputVariable(): ?Variable
    {
        return $this->inputVariable;
    }

    /**
     * Set remote variable corresponding to this box.
     * @param Variable|null $variable
     */
    public function setInputVariable(?Variable $variable)
    {
        $this->inputVariable = $variable;
    }


    /**
     * Get type of this box.
     * @return string
     */
    public function getType(): string
    {
        return self::$CUSTOM_BOX_TYPE;
    }

    public function getCategory(): string
    {
        return self::$CATEGORY;
    }

    /**
     * Get default input ports for this box.
     * @return array
     */
    public function getDefaultInputPorts(): array
    {
        return array();
    }

    /**
     * Get default output ports for this box.
     * @return array
     */
    public function getDefaultOutputPorts(): array
    {
        return array();
    }

    /**
     * Get default name of this box.
     * @return string
     */
    public function getDefaultName(): string
    {
        return "";
    }

    /**
     * Compile box into set of low-level tasks.
     * @param CompilationParams $params
     * @return array
     */
    public function compile(CompilationParams $params): array
    {
        return [];
    }

}
