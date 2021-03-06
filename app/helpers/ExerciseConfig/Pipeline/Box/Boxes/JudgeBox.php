<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Exceptions\ExerciseCompilationException;
use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\BoxCategories;
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
 * Box which represents judge evaluation.
 */
class JudgeBox extends Box
{
    /** Type key */
    public static $JUDGE_TYPE = "judge";
    public static $ARGS_PORT_KEY = "args";
    public static $JUDGE_TYPE_PORT_KEY = "judge-type";
    public static $CUSTOM_JUDGE_PORT_KEY = "custom-judge";
    public static $ACTUAL_OUTPUT_PORT_KEY = "actual-output";
    public static $EXPECTED_OUTPUT_PORT_KEY = "expected-output";
    public static $DEFAULT_NAME = "ReCodEx Judge";

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
                new Port((new PortMeta())->setName(self::$JUDGE_TYPE_PORT_KEY)->setType(VariableTypes::$STRING_TYPE)),
                new Port((new PortMeta())->setName(self::$ACTUAL_OUTPUT_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)),
                new Port(
                    (new PortMeta())->setName(self::$EXPECTED_OUTPUT_PORT_KEY)->setType(VariableTypes::$FILE_TYPE)
                ),
                new Port((new PortMeta())->setName(self::$ARGS_PORT_KEY)->setType(VariableTypes::$STRING_ARRAY_TYPE)),
                new Port((new PortMeta())->setName(self::$CUSTOM_JUDGE_PORT_KEY)->setType(VariableTypes::$FILE_TYPE))
            );
            self::$defaultOutputPorts = array();
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
        return self::$JUDGE_TYPE;
    }

    public function getCategory(): string
    {
        return BoxCategories::$EVALUATION;
    }

    public function isOptimizable(): bool
    {
        return false; // judge boxes are not optimizable (like the execution boxes)
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
     * Determine and return judge execution binary.
     * @return array pair of binary and list of arguments
     * @throws ExerciseConfigException
     * @throws ExerciseCompilationException
     */
    private function getJudgeBinaryAndArgs(): array
    {
        if (
            $this->hasInputPortValue(self::$CUSTOM_JUDGE_PORT_KEY) &&
            !$this->getInputPortValue(self::$CUSTOM_JUDGE_PORT_KEY)->isEmpty()
        ) {
            // custom judge was provided
            return [$this->getInputPortValue(self::$CUSTOM_JUDGE_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR), []];
        }

        $judgeType = null;
        if ($this->hasInputPortValue(self::$JUDGE_TYPE_PORT_KEY)) {
            $judgeType = strtolower($this->getInputPortValue(self::$JUDGE_TYPE_PORT_KEY)->getValue());
        }

        // Translation of judge type to command and args. The first item is the default.
        static $judgeTypes = null;
        if ($judgeTypes === null) {
            // TODO - this is work in progress, a more elaborate way to define recodex-token-judge parameters will be devised soon
            // TODO - shuffle-rows and shuffle-all versions are not implemented yet in recodex-token-judge
            // Note: recodex-token-judge is the new judge, which provides more verbose output for the logs
            $commonArgs = ['--log-limit', '4k', '--ignore-trailing-whitespace'];
            $commonArgsLineEnds = ['--log-limit', '4k', '--ignore-line-ends'];
            $judgeTypes = [
                'recodex-judge-normal' => [ConfigParams::$JUDGES_DIR . 'recodex-token-judge', $commonArgs],
                // default token judge (respecting newlines)
                'recodex-judge-float' => [
                    ConfigParams::$JUDGES_DIR . 'recodex-token-judge',
                    array_merge($commonArgs, ['--numeric'])
                ],
                // judge comparing float values with some margin of error
                'recodex-judge-normal-newline' => [
                    ConfigParams::$JUDGES_DIR . 'recodex-token-judge',
                    $commonArgsLineEnds
                ],
                // default token judge (which treats \n as normal whitespace)
                'recodex-judge-float-newline' => [
                    ConfigParams::$JUDGES_DIR . 'recodex-token-judge',
                    array_merge($commonArgsLineEnds, ['--numeric'])
                ],
                // judge comparing float values (which treats \n as normal whitespace)

                'recodex-judge-shuffle' => [
                    ConfigParams::$JUDGES_DIR . 'recodex-token-judge',
                    array_merge($commonArgs, ['--shuffled-tokens'])
                ],
                // judge ignoring order of tokens on a line
                'recodex-judge-shuffle-rows' => [ConfigParams::$JUDGES_DIR . 'recodex-judge-shuffle', ['-r']],
                // judge ignoring order of rows
                'recodex-judge-shuffle-all' => [ConfigParams::$JUDGES_DIR . 'recodex-judge-shuffle', ['-ir']],
                // judge ignoring order of tokens on a each line and order of rows
                'recodex-judge-shuffle-newline' => [
                    ConfigParams::$JUDGES_DIR . 'recodex-token-judge',
                    array_merge($commonArgsLineEnds, ['--shuffled-tokens'])
                ],
                // judge ignoring order of tokens (which treats \n ...)

                'recodex-judge-passthrough' => [ConfigParams::$JUDGES_DIR . 'recodex-judge-passthrough', []],
                // judge which writes its input file (first) to the stdout
                'diff' => ["/usr/bin/diff", []],
                // diff (binary-safe) judge
            ];
        }

        // judge type decision logic
        if (empty($judgeType)) {
            return reset($judgeTypes);
        } else {
            if (!empty($judgeTypes[$judgeType])) {
                return $judgeTypes[$judgeType];
            } else {
                throw new ExerciseCompilationException("Unknown judge type");
            }
        }
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
        $task = new Task();
        $task->setPriority(Priorities::$EVALUATION);
        $task->setType(TaskType::$EVALUATION);

        list($binary, $args) = $this->getJudgeBinaryAndArgs();
        $task->setCommandBinary($binary);

        // custom args
        if ($this->hasInputPortValue(self::$ARGS_PORT_KEY)) {
            $args = array_merge($args, $this->getInputPortValue(self::$ARGS_PORT_KEY)->getValue());
        }

        // classical args: expected actual
        $task->setCommandArguments(
            array_merge(
                $args,
                [
                    $this->getInputPortValue(self::$EXPECTED_OUTPUT_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR),
                    $this->getInputPortValue(self::$ACTUAL_OUTPUT_PORT_KEY)->getValue(ConfigParams::$EVAL_DIR)
                ]
            )
        );

        $sandbox = (new SandboxConfig())->setName(LinuxSandbox::$ISOLATE);
        $sandbox->setOutput(true);
        $task->setSandboxConfig($sandbox);

        return [$task];
    }
}
