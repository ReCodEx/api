<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\BoxCategories;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\LinuxSandbox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskCommands;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskType;
use App\Helpers\JobConfig\SandboxConfig;
use App\Helpers\JobConfig\Tasks\Task;
use Nette\Utils\Random;
use Exception;

/**
 * Base box which represents custom compilation of usually user defined program.
 */
abstract class CompilationBox extends Box
{
    public static $ARGS_PORT_KEY = "args";
    public static $SOURCE_FILE_PORT_KEY = "source-file";
    public static $SOURCE_FILES_PORT_KEY = "source-files";
    public static $BINARY_FILE_PORT_KEY = "binary-file";
    public static $EXTRA_FILES_PORT_KEY = "extra-files";
    public static $RUNNER_FILE_PORT_KEY = "runner";
    public static $COMPILER_EXEC_PATH_PORT_KEY = "compiler-exec-path";
    public static $EXISTS_FAILED_MSG =
        "Compilation process completed correctly but no executable file was yielded. Unable to proceed with testing.";


    /**
     * CompilationBox constructor.
     * @param BoxMeta $meta
     */
    public function __construct(BoxMeta $meta)
    {
        parent::__construct($meta);
    }


    public function getCategory(): string
    {
        return BoxCategories::$INITIATION;
    }

    /**
     * Base compilation which creates task, set its type to execution and create
     * sandbox configuration.
     * @param CompilationParams $params
     * @return Task
     */
    protected function compileBaseTask(CompilationParams $params): Task
    {
        $task = new Task();
        $task->setPriority(Priorities::$INITIATION);
        $task->setType(TaskType::$INITIATION);

        $sandbox = (new SandboxConfig())->setName(LinuxSandbox::$ISOLATE)->setOutput(true);
        $task->setSandboxConfig($sandbox);

        if ($params->isDebug()) {
            // My debug, you bow to no one...
            $sandbox->setStderrToStdout(true);
            $stdoutRandom = "compilation." . Random::generate(20) . ".out";
            // all outputs are stored as carboncopies in results directory
            $sandbox->setCarboncopyStdout(ConfigParams::$RESULT_DIR . $stdoutRandom);
        }

        return $task;
    }

    /**
     * @param array $files
     * @return Task
     */
    protected function compileExistsTask(array $files): Task
    {
        $task = new Task();
        $task->setPriority(Priorities::$INITIATION);
        $task->setType(TaskType::$INITIATION);

        $task->setCommandBinary(TaskCommands::$EXISTS);
        $task->setCommandArguments(array_merge([self::$EXISTS_FAILED_MSG], $files));

        return $task;
    }


    /**
     * Get value of given port (assuming file[] type) and return its value filtered.
     * @param string $portName The port identifier.
     * @param string $baseDir Base directory (file prefix).
     * @param string|callable|mixed $filter (regular expression) or callable (filtering function).
     * @return mixed List of files (paths).
     */
    protected function getInputPortFilesFiltered(string $portName, string $baseDir, $filter)
    {
        $files = $this->getInputPortValue($portName)->getValue($baseDir);
        if (is_string($filter)) {
            $realFilter = function ($file) use ($filter) {
                return preg_match($filter, $file);
            };
        } else {
            if (is_callable($filter)) {
                $realFilter = $filter;
            } else {
                throw new Exception("Invalid type of file filter."); // this should never happen
            }
        }
        return array_filter($files, $realFilter);
    }
}
