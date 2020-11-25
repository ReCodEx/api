<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\BoxCategories;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskCommands;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableTypes;
use App\Helpers\JobConfig\Tasks\Task;

/**
 * Box which will copy given file. If the filename is same for the input and
 * output box will compile to no-op.
 * @note Internal box which should not be necessary in pipelines.
 */
class CopyFileToDirBox extends Box
{
    /** Type key */
    public static $COPY_TYPE = "copy-file";
    public static $COPY_PORT_IN_KEY = "in";
    public static $COPY_PORT_OUT_KEY = "out";
    public static $DEFAULT_NAME = "Copy file";

    private static $initialized = false;
    private static $defaultInputPorts;
    private static $defaultOutputPorts;

    /**
     * Static initializer.
     */
    public static function init()
    {
        if (!self::$initialized) {
            self::$initialized = true;
            self::$defaultInputPorts = array(
                new Port((new PortMeta())->setName(self::$COPY_PORT_IN_KEY)->setType(VariableTypes::$FILE_TYPE))
            );
            self::$defaultOutputPorts = array(
                new Port((new PortMeta())->setName(self::$COPY_PORT_OUT_KEY)->setType(VariableTypes::$FILE_TYPE))
            );
        }
    }


    /**
     * Constructor.
     * @param BoxMeta $meta
     */
    public function __construct(BoxMeta $meta)
    {
        parent::__construct($meta);
    }


    /**
     * Get type of this box.
     * @return string
     */
    public function getType(): string
    {
        return self::$COPY_TYPE;
    }

    public function getCategory(): string
    {
        return BoxCategories::$INNER;
    }

    /**
     * Get default input ports for this box.
     * @return array
     */
    public function getDefaultInputPorts(): array
    {
        self::init();
        return self::$defaultInputPorts;
    }

    /**
     * Get default output ports for this box.
     * @return array
     */
    public function getDefaultOutputPorts(): array
    {
        self::init();
        return self::$defaultOutputPorts;
    }

    /**
     * Get default name of this box.
     * @return string
     */
    public function getDefaultName(): string
    {
        return self::$DEFAULT_NAME;
    }

    /**
     * Set input port of this box.
     * @param Port $port
     * @return CopyFileToDirBox
     */
    public function setInputPort(Port $port): CopyFileToDirBox
    {
        $this->meta->setInputPorts([$port]);
        return $this;
    }

    /**
     * Set output port of this box.
     * @param Port $port
     * @return CopyFileToDirBox
     */
    public function setOutputPort(Port $port): CopyFileToDirBox
    {
        $this->meta->setOutputPorts([$port]);
        return $this;
    }


    /**
     * Compile box into set of low-level tasks.
     * @param CompilationParams $params
     * @return array
     * @throws ExerciseConfigException
     */
    public function compile(CompilationParams $params): array
    {
        /** @var Variable $inputVariable */
        $inputVariable = current($this->getInputPorts())->getVariableValue();
        /** @var Variable $outputVariable */
        $outputVariable = current($this->getOutputPorts())->getVariableValue();

        // check for emptiness or same values, in those cases nothing has to be done
        if (
            ($inputVariable->getDirectory() === $outputVariable->getDirectory()) ||
            ($inputVariable->isEmpty() && $outputVariable->isEmpty())
        ) {
            return [];
        }

        // output variable is empty, that means it was not known during creation of copy box
        // so set it now from the input variable
        if ($outputVariable->isEmpty()) {
            $outputVariable->setValue($inputVariable->getValue());
        }

        $task = new Task();
        $task->setPriority(Priorities::$DEFAULT);
        $task->setCommandBinary(TaskCommands::$COPY);
        $task->setCommandArguments(
            [
                $inputVariable->getDirPrefixedValue(ConfigParams::$SOURCE_DIR),
                ConfigParams::$SOURCE_DIR . $outputVariable->getDirectory()
            ]
        );
        return [$task];
    }
}
