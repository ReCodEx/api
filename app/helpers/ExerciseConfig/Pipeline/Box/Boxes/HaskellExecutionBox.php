<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseCompilationSoftException;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\FrontendErrorMappings;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;

/**
 * Box which represents execution Haskell scripts. Input binary file is
 * pseudo-link between haskell compilation and this box.
 */
class HaskellExecutionBox extends ExecutionBox
{
    /** Type key */
    public static $BOX_TYPE = "haskell-exec";
    public static $HASKELL_BINARY = "/usr/bin/ghci";
    public static $DEFAULT_NAME = "Haskell Execution";

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
            self::$defaultInputPorts =  Box::constructPorts([
                self::$BINARY_FILE_PORT_KEY => VariableTypes::$FILE_TYPE,
                self::$SOURCE_FILES_PORT_KEY => VariableTypes::$FILE_ARRAY_TYPE,
                self::$STDIN_FILE_PORT_KEY => VariableTypes::$FILE_TYPE,
                self::$INPUT_FILES_PORT_KEY => VariableTypes::$FILE_ARRAY_TYPE,
                self::$ENTRY_POINT_KEY => VariableTypes::$STRING_TYPE,
                self::$EXTRA_FILES_PORT_KEY => VariableTypes::$FILE_ARRAY_TYPE,
                self::$SUCCESS_EXIT_CODES_PORT_KEY => VariableTypes::$STRING_ARRAY_TYPE,
            ]);
            self::$defaultOutputPorts =  Box::constructPorts([
                self::$STDOUT_FILE_PORT_KEY => VariableTypes::$FILE_TYPE,
                self::$OUTPUT_FILE_PORT_KEY => VariableTypes::$FILE_TYPE,
            ]);
        }
    }

    /**
     * HaskellExecutionBox constructor.
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
     * @throws ExerciseCompilationSoftException
     */
    public function compile(CompilationParams $params): array
    {
        $task = $this->compileBaseTask($params);
        $task->setCommandBinary(self::$HASKELL_BINARY);

        // check entry
        $entry = $this->getInputPortValue(self::$ENTRY_POINT_KEY)->getValue();
        if (!preg_match('/^([A-Z][a-zA-Z0-9_]*[.])?[a-z][a-zA-Z0-9_]*$/', $entry)) {
            throw new ExerciseCompilationSoftException(
                "Name of the entry-point contains illicit characters",
                FrontendErrorMappings::E400_406__EXERCISE_COMPILATION_BAD_ENTRY_POINT_NAME
            );
        }

        $task->setCommandArguments(
            array_merge(
                $this->getInputPortValue(self::$SOURCE_FILES_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR),
                $this->getInputPortValue(self::$EXTRA_FILES_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR),
                [
                    "-e",
                    "{$entry} ()"
                ]
            )
        );

        return [$task];
    }
}
