<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseCompilationException;
use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Pipeline\Ports\UndefinedPort;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\JobConfig\Tasks\Task;
use JsonSerializable;
use App\Helpers\Yaml;

/**
 * Abstract base class for all boxes which contains only basic meta information
 * about box. Defines constructor with one parameter which has to be called
 * from children one, that means children have to have constructor with the same
 * parameter in order to be constructed properly from BoxService. Also note that
 * BoxMeta information can be passed empty and after that fillDefaults function
 * called, this means validation of box metadata cannot be executed within
 * child constructor. Calling of validateMetadata is handled in BoxService,
 * which is the only proper way how to construct correct Box.
 */
abstract class Box implements JsonSerializable
{
    /**
     * Meta information about this box.
     * @var BoxMeta
     */
    protected $meta;

    /**
     * Directory in which box will be executed.
     * @note Set only during compilation after DirectoriesResolver service execution.
     * @var string
     */
    private $directory = null;

    /**
     * Box constructor.
     * @param BoxMeta $meta
     */
    public function __construct(BoxMeta $meta)
    {
        $this->meta = $meta;
        $this->meta->setType($this->getType());
    }


    /**
     * Get default input ports of some particular box.
     * Should be static property which is present only once for instance.
     */
    abstract public function getDefaultInputPorts(): array;

    /**
     * Get default output ports of some particular box.
     * Should be static property which is present only once for instance.
     */
    abstract public function getDefaultOutputPorts(): array;

    /**
     * Get default name of some particular box.
     * Should be static property which is present only once for instance.
     */
    abstract public function getDefaultName(): string;

    /**
     * Get type identifier of this box.
     * @return string
     */
    abstract public function getType(): string;

    /**
     * Get human readable category of this box.
     * @return string
     */
    abstract public function getCategory(): string;


    /**
     * Whether the box can be optimized (merged with another box with equal functionality).
     * Boxes are optimizable by default, but specific boxes may prevent this behavior if necessary.
     * @return bool
     */
    public function isOptimizable(): bool
    {
        return true;
    }

    /**
     * Compile box into set of low-level tasks.
     * @param CompilationParams $params
     * @return Task[]
     * @throws ExerciseCompilationException in case of any error
     */
    abstract public function compile(CompilationParams $params): array;


    /**
     * When listing default boxes which are available, there has to be somehow
     * filled default values, like names of the ports and values.
     */
    public function fillDefaults()
    {
        $this->meta->setName($this->getDefaultName());
        $this->meta->setInputPorts($this->getDefaultInputPorts());
        $this->meta->setOutputPorts($this->getDefaultOutputPorts());
    }

    /**
     * Check loaded metadatas which for now should include only validation of
     * ports. Called after construction in Box factory.
     * @throws ExerciseConfigException
     */
    public function validateMetadata()
    {
        $defaultInPorts = $this->getDefaultInputPorts();
        $defaultOutPorts = $this->getDefaultOutputPorts();

        // different number of input ports
        if (count($defaultInPorts) !== count($this->getInputPorts())) {
            throw new ExerciseConfigException("Number of input ports is not the same in box '{$this->getName()}'");
        }

        // different number of output ports
        if (count($defaultOutPorts) !== count($this->getOutputPorts())) {
            throw new ExerciseConfigException("Number of output ports is not the same in box '{$this->getName()}'");
        }

        // check if all default input ports are present and have same type
        foreach ($defaultInPorts as $defaultInPort) {
            $inPort = $this->meta->getInputPort($defaultInPort->getName());
            if (!$inPort || (!($inPort->getType() === $defaultInPort->getType()))) {
                // input port is missing or types of port and default port are not the
                // same, but if types are not the same and default port is undefined
                // there can be any type in the input port
                throw new ExerciseConfigException(
                    "Default input port '{$defaultInPort->getName()}' missing or malformed in box '{$this->getName()}'"
                );
            }
        }

        // check if all default output ports are present and have same type
        foreach ($defaultOutPorts as $defaultOutPort) {
            $outPort = $this->meta->getOutputPort($defaultOutPort->getName());
            if (!$outPort || (!($outPort->getType() === $defaultOutPort->getType()))) {
                // output port is missing or types of port and default port are not the
                // same, but if types are not the same and default port is undefined
                // there can be any type in the output port
                throw new ExerciseConfigException(
                    "Default output port '{$defaultOutPort->getName()}' missing or malformed in box '"
                    . $this->getName() . "'"
                );
            }
        }
    }

    /**
     * Get name of this box.
     * @return null|string
     */
    public function getName(): ?string
    {
        return $this->meta->getName();
    }

    /**
     * Get directory in which this box will be executed.
     * @return null|string
     */
    public function getDirectory(): ?string
    {
        return $this->directory;
    }

    /**
     * Set directory of this box.
     * @param string $directory
     * @return Box
     */
    public function setDirectory(string $directory): Box
    {
        $this->directory = $directory;
        return $this;
    }

    /**
     * Get all ports of the box.
     * @return Port[]
     */
    public function getPorts(): array
    {
        return array_merge($this->meta->getInputPorts(), $this->meta->getOutputPorts());
    }

    /**
     * Get input ports of this box.
     * @return Port[]
     */
    public function getInputPorts(): array
    {
        return $this->meta->getInputPorts();
    }

    /**
     * Get input port of given name from this box.
     * @param string $port
     * @return Port|null
     */
    public function getInputPort(string $port): ?Port
    {
        return $this->meta->getInputPort($port);
    }

    /**
     * Get output ports of this box.
     * @return Port[]
     */
    public function getOutputPorts(): array
    {
        return $this->meta->getOutputPorts();
    }

    /**
     * Get output port of given name from this box.
     * @param string $port
     * @return Port|null
     */
    public function getOutputPort(string $port): ?Port
    {
        return $this->meta->getOutputPort($port);
    }

    /**
     * Check if input port with given name has filled variable value.
     * @param string $port
     * @return bool
     */
    protected function hasInputPortValue(string $port): bool
    {
        return $this->getInputPort($port)?->getVariableValue() !== null &&
            !$this->getInputPort($port)->getVariableValue()->isEmpty();
    }

    /**
     * Check if ouput port with given name has filled variable value.
     * @param string $port
     * @return bool
     */
    protected function hasOutputPortValue(string $port): bool
    {
        return $this->getOutputPort($port)?->getVariableValue() !== null &&
            !$this->getOutputPort($port)->getVariableValue()->isEmpty();
    }

    /**
     * Return variable value of input port with given name.
     * @param string $port
     * @return Variable|null
     */
    protected function getInputPortValue(string $port): ?Variable
    {
        return $this->getInputPort($port)->getVariableValue();
    }

    /**
     * Return variable value of output port with given name.
     * @param string $port
     * @return Variable|null
     */
    protected function getOutputPortValue(string $port): ?Variable
    {
        return $this->getOutputPort($port)->getVariableValue();
    }


    /**
     * Creates and returns properly structured array representing this object.
     * @return array
     */
    public function toArray(): array
    {
        return $this->meta->toArray();
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

    /**
     * Helper method to save typing when initializing box ports.
     * @param array $descriptor [ port name => variable type ]
     * @return Port[] array of constructed and initialized port objects
     */
    public static function constructPorts(array $descriptor): array
    {
        return array_map(function ($name) use ($descriptor) {
            return new Port((new PortMeta())->setName($name)->setType($descriptor[$name]));
        }, array_keys($descriptor));
    }
}
