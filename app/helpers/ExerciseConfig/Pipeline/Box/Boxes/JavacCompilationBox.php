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

/**
 * Box which represents javac compilation unit.
 */
class JavacCompilationBox extends CompilationBox
{
    /** Type key */
    public static $JAVAC_TYPE = "javac";
    public static $JAVAC_BINARY = "/usr/bin/javac";
    public static $COMPILATION_SUBDIR = 'compiled-classes';
    public static $CLASS_FILES_WILDCARD = "*.class";
    public static $CLASS_FILES_PORT_KEY = "class-files";
    public static $JAR_FILES_PORT_KEY = "jar-files";
    public static $DEFAULT_NAME = "Javac Compilation";

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
                new Port((new PortMeta())->setName(self::$ARGS_PORT_KEY)->setType(VariableTypes::$STRING_ARRAY_TYPE)),
                new Port(
                    (new PortMeta())->setName(self::$SOURCE_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)
                ),
                new Port(
                    (new PortMeta())->setName(self::$EXTRA_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)
                ),
                new Port((new PortMeta())->setName(self::$JAR_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE))
            );
            self::$defaultOutputPorts = array(
                new Port(
                    (new PortMeta())->setName(self::$CLASS_FILES_PORT_KEY)->setType(VariableTypes::$FILE_ARRAY_TYPE)
                )
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
        return self::$JAVAC_TYPE;
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
        // Create a separate directory where all *.class files will end up.
        $mkdirTask = new Task();
        $mkdirTask->setPriority(Priorities::$INITIATION);
        $mkdirTask->setType(TaskType::$INITIATION);
        $mkdirTask->setCommandBinary(TaskCommands::$MKDIR);
        $mkdirTask->setCommandArguments(
            [
                ConfigParams::$SOURCE_DIR . $this->getDirectory(
                ) . ConfigParams::$PATH_DELIM . self::$COMPILATION_SUBDIR
            ]
        );

        // Prepare compile task
        $compileTask = $this->compileBaseTask($params);
        $compileTask->setCommandBinary(self::$JAVAC_BINARY);

        $args = [];
        if ($this->hasInputPortValue(self::$ARGS_PORT_KEY)) {
            $args = $this->getInputPortValue(self::$ARGS_PORT_KEY)->getValue();
        }

        // First order of business -- make sure all *.class files will be yielded to prepared dir (but in eval box)
        $args[] = '-d';
        $args[] = ConfigParams::$EVAL_DIR . self::$COMPILATION_SUBDIR;

        // if there were some provided jar files, lets add them to the command line args
        $classpath = JavaUtils::constructClasspath($this->getInputPortValue(self::$JAR_FILES_PORT_KEY));
        $args = array_merge($args, $classpath);

        // originally output of javac was wildcard '*.class', this is no longer true
        // currently the whole directory with compiled classes is handed over to the
        // upcoming tasks, but the type of the port was not changed
        // Therefore this might require reimplementation in future!
        $this->getOutputPortValue(self::$CLASS_FILES_PORT_KEY)->setValue(self::$COMPILATION_SUBDIR);

        $compileTask->setCommandArguments(
            array_merge(
                $args,
                $this->getInputPortValue(self::$SOURCE_FILES_PORT_KEY)
                    ->getValue(ConfigParams::$EVAL_DIR),
                $this->getInputPortValue(self::$EXTRA_FILES_PORT_KEY)
                    ->getValue(ConfigParams::$EVAL_DIR)
            )
        );

        return [$mkdirTask, $compileTask];
    }
}
