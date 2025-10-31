<?php

namespace App\Console;

use App\Model\Repository\RuntimeEnvironments;
use App\Model\Repository\Pipelines;
use App\Model\Repository\ExerciseConfigs;
use App\Model\Entity\RuntimeEnvironment;
use App\Helpers\ExerciseConfig\Helper;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\VariablesTable;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

#[AsCommand(
    name: 'runtimes:fix-config-variables',
    description: 'Scan exercise configs of given runtime environment and attempt to fix the variables. ' .
        'The variables lists are extracted from pipelines, new variables are added (with defaults), ' .
        'unidentified variables are removed.'
)]
class FixConfigVariables extends BaseCommand
{
    /** @var bool */
    private $silent = false;

    /** @var array */
    private $adds = [];

    /** @var array */
    private $removals = [];

    // injections

    /** @var RuntimeEnvironments */
    private $runtimeEnvironments;

    /** @var Pipelines */
    private $pipelines;

    /** @var ExerciseConfigs */
    private $exerciseConfigs;

    /** @var Loader */
    public $exerciseConfigLoader;

    /** @var Helper */
    public $exerciseConfigHelper;

    public function __construct(
        RuntimeEnvironments $runtimeEnvironments,
        Pipelines $pipelines,
        ExerciseConfigs $exerciseConfigs,
        Loader $exerciseConfigLoader,
        Helper $exerciseConfigHelper
    ) {
        parent::__construct();
        $this->runtimeEnvironments = $runtimeEnvironments;
        $this->pipelines = $pipelines;
        $this->exerciseConfigs = $exerciseConfigs;
        $this->exerciseConfigLoader = $exerciseConfigLoader;
        $this->exerciseConfigHelper = $exerciseConfigHelper;
    }

    protected function configure()
    {
        $this->addArgument(
            'runtime',
            InputArgument::REQUIRED,
            'Identifier of the runtime environment of which the exercises will be updated.'
        )->addOption(
            'yes',
            'y',
            InputOption::VALUE_NONE,
            "Assume 'yes' to all inquiries (run in non-interactive mode)"
        )->addOption(
            'silent',
            's',
            InputOption::VALUE_NONE,
            "Silent mode (no outputs except for errors)"
        );
    }

    private function writeln(...$lines): void
    {
        if ($this->silent) {
            return;
        }

        foreach ($lines as $line) {
            $this->output->writeln($line);
        }
    }


    /**
     * Load pipelines for selected runtime.
     * @param RuntimeEnvironment $runtime selected runtime
     * @return array id => pipeline entity
     */
    private function loadPipelines(RuntimeEnvironment $runtime): array
    {
        $runtimePipelines = [];
        foreach ($this->pipelines->findAll() as $pipeline) {
            if ($pipeline->getRuntimeEnvironments()->contains($runtime)) {
                $runtimePipelines[$pipeline->getId()] = $pipeline;
                $this->adds[$pipeline->getId()] = [];
                $this->removals[$pipeline->getId()] = [];
            }
        }
        return $runtimePipelines;
    }

    /**
     * Join given pipelines and extract expected variables for that particular configuration.
     * @param string[] $pipelineIds list of pipeline ids to participate
     * @param VariablesTable $environmentVariables variables already defined in the environment
     * @return array pipeline id => [ name => Variable ]
     */
    private function getExpectedVariablesForPipelines(array $pipelineIds, VariablesTable $environmentVariables): array
    {
        // prepare variable lists for each pipeline
        $expectedVariables = $this->exerciseConfigHelper->getVariablesForExercise(
            $pipelineIds,
            $environmentVariables
        );

        // transform the result, so arrays are indexed by names/ids
        $result = [];
        foreach ($expectedVariables as $vars) {
            $id = $vars['id'] ?? null;
            if (!$id) {
                continue;
            }

            $resVars = [];
            foreach ($vars['variables'] ?? [] as $var) {
                $name = $var->getName();
                if ($name) {
                    $resVars[$name] = $var;
                }
            }
            $result[$id] = $resVars;
        }

        return $result;
    }

    /**
     * Generate all possible pipeline combinations for given runtime and aggregate expected variables for each pipeline.
     * @param RuntimeEnvironment $runtime selected runtime
     * @return array pipeline id => [ name => Variable ]
     */
    private function getExpectedVariables(RuntimeEnvironment $runtime): array
    {
        $pipelines = $this->loadPipelines($runtime);
        $environmentVariables = $this->exerciseConfigLoader->loadVariablesTable($runtime->getParsedVariables());

        // sort out the pipelines
        $compilationPipelines = [];
        $executionPipelines = [];
        foreach ($pipelines as $id => $pipeline) {
            $params = $pipeline->getParametersValues(true);
            if ($params['isCompilationPipeline']) {
                $compilationPipelines[] = $id;
            } else {
                $executionPipelines[] = $id;
            }
        }

        // create all sorts of combinations which may appear in exercises
        $result = [];
        foreach ($executionPipelines as $execId) {
            $pipelineIds = [...$compilationPipelines, $execId];
            $variables = $this->getExpectedVariablesForPipelines($pipelineIds, $environmentVariables);
            foreach ($variables as $id => $vars) {
                $result[$id] = $vars;
            }
        }
        return $result;
    }

    /**
     * Fix a variables in one pipeline configuration based on expected variables.
     * @param string $pipelineId
     * @param array $variables to be fixed
     * @param array $expectedVariables
     * @param array $adds accumulator for add-updates statistics
     * @param array $removals accumulator for remove-updates statistics
     * @param array $errors accumulator of errors (key is used to aggregate errors
     *                      so the same error is not reported multiple times)
     * @return array updated variables
     */
    private function fixVariables(
        string $pipelineId,
        array $variables,
        array $expectedVariables,
        array &$adds,
        array &$removals,
        array &$errors
    ): array {
        $remove = [];
        foreach ($variables as $idx => $var) {
            $name = $var['name'];
            $type = $var['type'];
            if (array_key_exists($name, $expectedVariables)) {
                $exType = $expectedVariables[$name]->getType();
                if ($type !== $exType) {
                    $errors["$pipelineId $name"] = "Variable '$name' in configuration of pipeline '$pipelineId' " .
                        "type mismatch ($exType expected, but $type found).";
                }
                unset($expectedVariables[$name]);
            } else {
                $removals[$pipelineId][$name] = true;
                $remove[] = $idx;
            }
        }

        if (!$expectedVariables && !$remove) {
            return $variables; // nothing to add or remove (return identical array)
        }

        // remaining expected variables (to be inserted)
        foreach ($expectedVariables as $name => $var) {
            $adds[$pipelineId][$name] = true;
            $variables[] = [
                'name' => $name,
                'type' => $var->getType(),
                'value' => $var->isArray() ? [] : ''
            ];
        }

        // remove all unexpected variables
        foreach ($remove as $idx) {
            unset($variables[$idx]);
        }

        return array_values($variables); // consolidate indices
    }

    /**
     * Fix whole exercise configuration.
     * @param array $tests tests substructure of the exercise config to be updated
     * @param string $runtimeId which runtime is of interest
     * @return array list of errors (empty list on success)
     */
    private function fixTestsPipelines(array &$tests, string $runtimeId, array $expectedVariables)
    {
        $adds = $removals = $errors = [];
        foreach ($tests as &$test) {
            if (empty($test['environments'][$runtimeId]['pipelines'])) {
                continue;
            }

            foreach ($test['environments'][$runtimeId]['pipelines'] as &$pipeline) {
                $pipelineId = $pipeline['name'] ?? null;
                if (!$pipelineId || !array_key_exists($pipelineId, $expectedVariables)) {
                    continue;
                }

                $pipeline['variables'] = $this->fixVariables(
                    $pipelineId,
                    $pipeline['variables'],
                    $expectedVariables[$pipelineId],
                    $adds,
                    $removals,
                    $errors
                );
            }
            unset($pipeline); // just to make sure a reference is not accidentally used
        }
        unset($test); // just to make sure a reference is not accidentally used

        // consolidate update statistics
        foreach ($adds as $pid => $names) {
            foreach ($names as $name => $_) {
                $this->adds[$pid][$name]++;
            }
        }

        foreach ($removals as $pid => $names) {
            foreach ($names as $name => $_) {
                $this->removals[$pid][$name]++;
            }
        }

        return array_values($errors);
    }

    /*
     * Finally, the main function of command!
     */

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // just to save time (we do not have to pass this down to every other method invoked)
        $this->input = $input;
        $this->output = $output;

        $this->nonInteractive = $input->getOption('yes');
        $this->silent = $input->getOption('silent');
        $runtimeId = $input->getArgument('runtime');

        if (!$this->confirm("Attempting to fix variables all exercises with $runtimeId. Do you wish to proceed? ")) {
            return Command::SUCCESS;
        }

        try {
            $this->exerciseConfigs->beginTransaction();

            $runtime = $this->runtimeEnvironments->findOrThrow($runtimeId);
            $expectedVariables = $this->getExpectedVariables($runtime);

            // take all configs one by one regardless of their exercises
            $configs = $this->exerciseConfigs->findAll();
            $updated = $failed = 0;
            foreach ($configs as $configEntity) {
                $configId = $configEntity->getId();
                $config = $configEntity->getParsedConfig();
                if (!in_array($runtimeId, $config['environments'] ?? []) || empty($config['tests'])) {
                    continue;
                }

                // try to fix the config and get a list of changes...
                $errors = $this->fixTestsPipelines($config['tests'], $runtimeId, $expectedVariables);
                $configEntity->overrideConfig($config);
                $this->exerciseConfigs->persist($configEntity);

                if ($errors) {
                    ++$failed;
                    $output->writeln("Unable to fix variables in configuration '$configId':");
                    foreach ($errors as $error) {
                        $output->writeln("\t$error");
                    }
                }

                if (++$updated % 100 === 0) {
                    $this->writeln("($updated configs processed already)");
                }
            }

            $this->exerciseConfigs->commit();

            // print info
            $this->writeln("Total $updated configs processed, $failed configs were not entirely fixed.");
            foreach ($this->adds as $pid => $adds) {
                foreach ($adds as $name => $count) {
                    $this->writeln("Variable $name (pipeline $pid) added ($count x)");
                }
            }
            foreach ($this->removals as $pid => $removals) {
                foreach ($removals as $name => $count) {
                    $this->writeln("Variable $name (pipeline $pid) removed ($count x)");
                }
            }
        } catch (Exception $e) {
            $this->exerciseConfigs->rollback();
            $msg = $e->getMessage();
            $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $stderr->writeln("Error: $msg");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
