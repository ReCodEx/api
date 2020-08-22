<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseCompilationException;
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
 * Box which will copy given files. If the filenames are same for the input and
 * output, box will compile to no-op.
 * @note Internal box which should not be necessary in pipelines.
 */
class CopyFilesToDirBox extends Box
{
    /** Type key */
    public static $COPY_TYPE = "copy-files";
    public static $COPY_PORT_IN_KEY = "in";
    public static $COPY_PORT_OUT_KEY = "out";
    public static $DEFAULT_NAME = "Copy multiple files";

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
                new Port((new PortMeta())->setName(self::$COPY_PORT_IN_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE))
            );
            self::$defaultOutputPorts = array(
                new Port((new PortMeta())->setName(self::$COPY_PORT_OUT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE))
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
     * @return CopyFilesToDirBox
     */
    public function setInputPort(Port $port): CopyFilesToDirBox
    {
        $this->meta->setInputPorts([$port]);
        return $this;
    }

    /**
     * Set output port of this box.
     * @param Port $port
     * @return CopyFilesToDirBox
     */
    public function setOutputPort(Port $port): CopyFilesToDirBox
    {
        $this->meta->setOutputPorts([$port]);
        return $this;
    }


    /**
     * Compile box into set of low-level tasks.
     * @param CompilationParams $params
     * @return array
     * @throws ExerciseConfigException
     * @throws ExerciseCompilationException
     */
    public function compile(CompilationParams $params): array
    {
        /**
         * @var Variable $inputVariable
         * @var Variable $outputVariable
         */
        $inputVariable = current($this->getInputPorts())->getVariableValue();
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

        if (count($inputVariable->getValue()) !== count($outputVariable->getValue())) {
            throw new ExerciseCompilationException("Different count of files (source vs dest) in copy box");
        }

        $inputs = array_values($inputVariable->getDirPrefixedValueAsArray(ConfigParams::$SOURCE_DIR));

        $tasks = [];
        for ($i = 0; $i < count($inputs); ++$i) {
            $task = new Task();
            $task->setPriority(Priorities::$DEFAULT);
            $task->setCommandBinary(TaskCommands::$COPY);
            $task->setCommandArguments(
                [
                    $inputs[$i],
                    ConfigParams::$SOURCE_DIR . $outputVariable->getDirectory()
                ]
            );
            $tasks[] = $task;
        }
        return $tasks;
    }
}
