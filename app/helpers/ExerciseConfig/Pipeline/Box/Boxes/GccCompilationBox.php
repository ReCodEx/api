<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\LinuxSandbox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskType;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;
use App\Helpers\JobConfig\SandboxConfig;
use App\Helpers\JobConfig\Tasks\Task;

/**
 * Box which represents gcc compilation unit.
 */
class GccCompilationBox extends CompilationBox
{
    /** Type key */
    public static $GCC_TYPE = "gcc";
    public static $GCC_BINARY_DEFAULT = "/usr/bin/gcc";
    public static $DEFAULT_NAME = "GCC Compilation";
    public static $CPP_EXT_FILTER = '/[.](cpp|c|cc|o|obj|a|so)$/i';

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
                new Port((new PortMeta())->setName(self::$COMPILER_EXEC_PATH_PORT_KEY)
                    ->setType(VariableTypes::$STRING_TYPE)),
                new Port((new PortMeta())->setName(self::$ARGS_PORT_KEY)->setType(VariableTypes::$STRING_ARRAY_TYPE)),
                new Port(
                    (new PortMeta())->setName(self::$SOURCE_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)
                ),
                new Port(
                    (new PortMeta())->setName(self::$EXTRA_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)
                )
            );
            self::$defaultOutputPorts = array(
                new Port((new PortMeta())->setName(self::$BINARY_FILE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE))
            );
        }
    }

    /**
     * JudgeNormalBox constructor.
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
        return self::$GCC_TYPE;
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
     */
    public function compile(CompilationParams $params): array
    {
        $task = $this->compileBaseTask($params);

        $task->setCommandBinary(
            $this->hasInputPortValue(self::$COMPILER_EXEC_PATH_PORT_KEY)
            ? $this->getInputPortValue(self::$COMPILER_EXEC_PATH_PORT_KEY)->getValue() : self::$GCC_BINARY_DEFAULT
        );

        $args = [];
        if ($this->hasInputPortValue(self::$ARGS_PORT_KEY)) {
            $args = $this->getInputPortValue(self::$ARGS_PORT_KEY)->getValue();
        }
        $task->setCommandArguments(
            array_merge(
                $args,
                $this->getInputPortFilesFiltered(
                    self::$SOURCE_FILES_PORT_KEY,
                    ConfigParams::$EVAL_DIR,
                    self::$CPP_EXT_FILTER
                ),
                $this->getInputPortFilesFiltered(
                    self::$EXTRA_FILES_PORT_KEY,
                    ConfigParams::$EVAL_DIR,
                    self::$CPP_EXT_FILTER
                ),
                [
                    "-o",
                    $this->getOutputPortValue(self::$BINARY_FILE_PORT_KEY)
                        ->getValue(ConfigParams::$EVAL_DIR)
                ]
            )
        );

        // check if file produced by compilation was successfully created
        $binary = $this->getOutputPortValue(self::$BINARY_FILE_PORT_KEY)->getDirPrefixedValue(
            ConfigParams::$SOURCE_DIR
        );
        $exists = $this->compileExistsTask([$binary]);

        return [$task, $exists];
    }
}
