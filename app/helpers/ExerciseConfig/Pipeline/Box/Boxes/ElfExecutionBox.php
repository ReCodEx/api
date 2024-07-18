<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;

/**
 * Box which represents execution of custom compiled program in ELF format.
 */
class ElfExecutionBox extends ExecutionBox
{
    /** Type key */
    public static $BOX_TYPE = "elf-exec";
    public static $DEFAULT_NAME = "ELF Execution";

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
            self::$defaultInputPorts = Box::constructPorts([
                self::$BINARY_FILE_PORT_KEY => VariableTypes::$FILE_TYPE,
                self::$ARGS_PORT_KEY => VariableTypes::$STRING_ARRAY_TYPE,
                self::$STDIN_FILE_PORT_KEY => VariableTypes::$FILE_TYPE,
                self::$INPUT_FILES_PORT_KEY => VariableTypes::$FILE_ARRAY_TYPE,
                self::$SUCCESS_EXIT_CODES_PORT_KEY => VariableTypes::$STRING_ARRAY_TYPE,
            ]);
            self::$defaultOutputPorts = Box::constructPorts([
                self::$STDOUT_FILE_PORT_KEY => VariableTypes::$FILE_TYPE,
                self::$OUTPUT_FILE_PORT_KEY => VariableTypes::$FILE_TYPE,
            ]);
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
     * Compile box into set of low-level tasks.
     * @param CompilationParams $params
     * @return array
     * @throws ExerciseConfigException
     */
    public function compile(CompilationParams $params): array
    {
        $task = $this->compileBaseTask($params);
        $task->setCommandBinary(
            $this->getInputPortValue(self::$BINARY_FILE_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR)
        );
        if ($this->hasInputPortValue(self::$ARGS_PORT_KEY)) {
            $task->setCommandArguments($this->getInputPortValue(self::$ARGS_PORT_KEY)->getValue());
        }

        return [$task];
    }
}
