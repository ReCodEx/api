<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskCommands;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskType;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\VariableTypes;
use App\Helpers\JobConfig\Tasks\Task;
use Nette\Utils\Strings;
use Nette\Utils\Random;

/**
 * Extracts zip (or other) archive(s) into a directory.
 * If multiple archives are given, they are extracted all in one directory (with overwrites).
 */
class ExtractBox extends CompilationBox
{
    /** Type key */
    public static $BOX_TYPE = "extract";
    public static $ARCHIVE_FILES_PORT_KEY = "archives";
    public static $TARGET_DIR_PORT_KEY = "out-dir";
    public static $DEFAULT_NAME = "Extract Files from Archive";

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
            self::$defaultInputPorts = Box::constructPorts([
                self::$ARCHIVE_FILES_PORT_KEY => VariableTypes::$FILE_ARRAY_TYPE,
            ]);
            self::$defaultOutputPorts = Box::constructPorts([
                self::$TARGET_DIR_PORT_KEY => VariableTypes::$FILE_TYPE,
            ]);
        }
    }

    /**
     * ExtractBox constructor.
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
        $targetDir = $this->getOutputPortValue(self::$TARGET_DIR_PORT_KEY);
        if ($targetDir->isEmpty()) {
            // name of the directory is empty, so just make up a random one
            $targetDir->setValue("extract_" . Random::generate(20));
        }
        $to = $targetDir->getDirPrefixedValue(ConfigParams::$SOURCE_DIR);

        // we must ensure the target directory exists
        $mkdirTask = new Task();
        $mkdirTask->setPriority(Priorities::$INITIATION);
        $mkdirTask->setType(TaskType::$INITIATION);
        $mkdirTask->setCommandBinary(TaskCommands::$MKDIR);
        $mkdirTask->setCommandArguments([ $to ]);
        $tasks = [ $mkdirTask ];

        // create extract task for each input (archive) file
        $archives = $this->getInputPortValue(self::$ARCHIVE_FILES_PORT_KEY)
            ->getDirPrefixedValueAsArray(ConfigParams::$SOURCE_DIR);
        foreach ($archives as $archive) {
            $task = new Task();
            $task->setPriority(Priorities::$DEFAULT);
            $task->setCommandBinary(TaskCommands::$EXTRACT);
            $task->setCommandArguments([ $archive, $to ]);
            $tasks[] = $task;
        }

        return $tasks;
    }
}
