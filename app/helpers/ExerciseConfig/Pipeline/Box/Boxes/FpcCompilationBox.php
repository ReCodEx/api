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
 * Box which represents fpc compilation unit.
 */
class FpcCompilationBox extends CompilationBox
{
    /** Type key */
    public static $FPC_TYPE = "fpc";
    public static $FPC_BINARY = "/usr/bin/fpc";
    public static $DEFAULT_NAME = "FreePascal Compilation";

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
        return self::$FPC_TYPE;
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
        $tasks = [];

        $sourceFiles = $this->getInputPortValue(self::$SOURCE_FILES_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR);
        $extraFiles = $this->getInputPortValue(self::$EXTRA_FILES_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR);
        foreach (array_merge($sourceFiles, $extraFiles) as $sourceFile) {
            $task = $this->compileBaseTask($params);
            $task->setCommandBinary(self::$FPC_BINARY);

            $args = [];
            if ($this->hasInputPortValue(self::$ARGS_PORT_KEY)) {
                $args = $this->getInputPortValue(self::$ARGS_PORT_KEY)->getValue();
            }
            $args = array_merge(
                $args,
                [
                    $sourceFile,
                    "-o" . $this->getOutputPortValue(self::$BINARY_FILE_PORT_KEY)
                        ->getValue(ConfigParams::$EVAL_DIR)
                ]
            );
            $task->setCommandArguments($args);

            // add task to whole lotta of tasks
            $tasks[] = $task;
        }

        // check if file produced by compilation was successfully created
        $binary = $this->getOutputPortValue(self::$BINARY_FILE_PORT_KEY)->getDirPrefixedValue(
            ConfigParams::$SOURCE_DIR
        );
        $exists = $this->compileExistsTask([$binary]);

        return array_merge($tasks, [$exists]);
    }
}
