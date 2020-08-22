<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;
use Nette\Utils\Strings;

/**
 * Box which represents execution python script.
 */
class Python3RunBox extends ExecutionBox
{
    /** Type key */
    public static $BOX_TYPE = "python3";
    public static $PYTHON3_BINARY = "/usr/bin/env";
    public static $PYTHON3_VERSION = "python3";
    public static $DEFAULT_NAME = "Python3 Execution";

    public static $PY_EXTENSION = ".py";

    private static $initialized = false;
    private static $defaultInputPorts;
    private static $defaultOutputPorts;

    /**
     * Static initializer.
     * @throws ExerciseConfigException
     */
    public static function init()
    {
        if (!self::$initialized) {
            self::$initialized = true;
            self::$defaultInputPorts = array(
                new Port((new PortMeta())->setName(self::$RUNNER_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
                new Port(
                    (new PortMeta())->setName(self::$SOURCE_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)
                ),
                new Port((new PortMeta())->setName(self::$ARGS_PORT_KEY)->setType(VariableTypes::$STRING_ARRAY_TYPE)),
                new Port((new PortMeta())->setName(self::$STDIN_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
                new Port(
                    (new PortMeta())->setName(self::$INPUT_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)
                ),
                new Port((new PortMeta())->setName(self::$ENTRY_POINT_KEY)->setType(VariableTypes::$FILE_TYPE)),
                new Port(
                    (new PortMeta())->setName(self::$EXTRA_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)
                ),
            );
            self::$defaultOutputPorts = array(
                new Port((new PortMeta())->setName(self::$STDOUT_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
                new Port((new PortMeta())->setName(self::$OUTPUT_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE))
            );
        }
    }

    /**
     * ElfExecutionBox constructor.
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
        return self::$BOX_TYPE;
    }

    /**
     * Get default input ports for this box.
     * @return array
     * @throws ExerciseConfigException
     */
    public function getDefaultInputPorts(): array
    {
        self::init();
        return self::$defaultInputPorts;
    }

    /**
     * Get default output ports for this box.
     * @return array
     * @throws ExerciseConfigException
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
     * Compile box into set of low-level tasks.
     * @param CompilationParams $params
     * @return array
     * @throws ExerciseConfigException
     */
    public function compile(CompilationParams $params): array
    {
        $task = $this->compileBaseTask($params);
        $task->setCommandBinary(self::$PYTHON3_BINARY);

        $entry = $this->getInputPortValue(self::$ENTRY_POINT_KEY)->getValue();
        $runner = $this->getInputPortValue(self::$RUNNER_FILE_PORT_KEY)->getValue();
        $args = [self::$PYTHON3_VERSION, $runner, $entry];
        if ($this->hasInputPortValue(self::$ARGS_PORT_KEY)) {
            $args = array_merge($args, $this->getInputPortValue(self::$ARGS_PORT_KEY)->getValue());
        }
        $task->setCommandArguments($args);

        return [$task];
    }
}
