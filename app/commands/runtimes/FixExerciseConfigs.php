<?php

namespace App\Console;

use App\Model\Repository\RuntimeEnvironments;
use App\Model\Repository\Pipelines;
use App\Model\Repository\ExerciseConfigs;
use App\Model\Entity\Pipeline;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class FixExerciseConfigs extends BaseCommand
{
    protected static $defaultName = 'runtimes:fixExerciseConfigs';

    /** @var bool */
    private $silent = false;

    /** @var array */
    private $pipelinesCache = [];

    /** @var array */
    private $runtimePipelines = [];

    // injections

    /** @var RuntimeEnvironments */
    private $runtimeEnvironments;

    /** @var Pipelines */
    private $pipelines;

    /** @var ExerciseConfigs */
    private $exerciseConfigs;

    public function __construct(
        RuntimeEnvironments $runtimeEnvironments,
        Pipelines $pipelines,
        ExerciseConfigs $exerciseConfigs
    ) {
        parent::__construct();
        $this->runtimeEnvironments = $runtimeEnvironments;
        $this->pipelines = $pipelines;
        $this->exerciseConfigs = $exerciseConfigs;
    }

    protected function configure()
    {
        $this->setName(self::$defaultName)->setDescription(
            'Scan exercise configs of given runtime environment and attempts to fix them. ' .
            'This feature may be used when runtime was updated and some of its pipelines replaced.'
        )
        ->addArgument(
            'runtime',
            InputArgument::REQUIRED,
            'Identifier of the runtime environment of which the exercises will be updated.'
        )
        ->addOption(
            'yes',
            'y',
            InputOption::VALUE_NONE,
            "Assume 'yes' to all inquiries (run in non-interactive mode)"
        )
        ->addOption(
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
     * Populate internal pipeline cache and pipeline list for selected runtime.
     * @param string $runtimeId selected runtime
     */
    private function loadPipelines(string $runtimeId): void
    {
        $runtime = $this->runtimeEnvironments->findOrThrow($runtimeId);
        $this->pipelinesCache = [];
        $this->runtimePipelines = [];
        foreach ($this->pipelines->findAll() as $pipeline) {
            $this->pipelinesCache[$pipeline->getId()] = $pipeline;
            if ($pipeline->getRuntimeEnvironments()->contains($runtime)) {
                $this->runtimePipelines[$pipeline->getId()] = $pipeline;
            }
        }
    }

    /**
     * Compare parameters of two pipelines, return true if they match.
     * @param Pipeline $p1
     * @param Pipeline $p2
     * @return bool
     */
    private static function pipelinesMatch(Pipeline $p1, Pipeline $p2): bool
    {
        $params1 = $p1->getParametersValues(true);
        $params2 = $p2->getParametersValues(true);

        if (count($params1) !== count($params2)) {
            return false;
        }

        foreach ($params1 as $key => $value) {
            if ($value !== $params2[$key]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find matching pipeline to the given one, but one that is from selected runtime.
     * The pipelines are matched by parameters (e.g., compilation/execution...).
     * @param string $id of a pipeline to be replaced
     * @return string|null id of a matching pipeline, null if no such pipeline exists
     */
    private function findMatchingRuntimePipeline(string $id): ?string
    {
        $pipeline = $this->pipelinesCache[$id];
        if (!$pipeline) {
            return null;
        }

        // filter a list of matching candidates
        $candidates = [];
        foreach ($this->runtimePipelines as $p) {
            if (self::pipelinesMatch($pipeline, $p)) {
                $candidates[] = $p;
            }
        }

        // exactly one candidate should be on the list, otherwise no match can be declared
        return count($candidates) === 1 ? $candidates[0]->getId() : null;
    }


    /**
     * Updates exercise config by replacing invalid pipeline ids with correct matching ones.
     * @param array $tests tests substructure of the exercise config to be updated
     * @param string $runtimeId which runtime is of interest
     * @return array|bool list of pipeline updates [ oldId => newId ], false on error
     */
    private function fixTestsPipelines(array &$tests, string $runtimeId)
    {
        $changes = [];

        foreach ($tests as &$test) {
            if (empty($test['environments'][$runtimeId]['pipelines'])) {
                continue;
            }

            foreach ($test['environments'][$runtimeId]['pipelines'] as &$pipeline) {
                $oldId = $pipeline['name'] ?? null;
                if (!$oldId || array_key_exists($oldId, $this->runtimePipelines)) {
                    continue;
                }

                if (!array_key_exists($oldId, $this->pipelinesCache)) {
                    return false; // something is odd, the pipeline ID is not known!
                }

                if (!$this->pipelinesCache[$oldId]->isGlobal()) {
                    continue; // ignore custom pipelines
                }

                $newId = $this->findMatchingRuntimePipeline($oldId);
                if (!$newId) {
                    return false;
                }

                $pipeline['name'] = $newId;
                $changes[$oldId] = $newId;
            }
            unset($pipeline); // just to make sure a reference is not accidentaly used
        }
        unset($test); // just to make sure a reference is not accidentaly used

        return $changes;
    }

    /*
     * Finally, the main function of command!
     */

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // just to save time (we do not have to pass this down to every other method invoked)
        $this->input = $input;
        $this->output = $output;

        $this->nonInteractive = $input->getOption('yes');
        $this->silent = $input->getOption('silent');
        $runtimeId = $input->getArgument('runtime');

        if (!$this->confirm("Attempting to fix exercise configurations for $runtimeId. Do you wish to proceed? ")) {
            return Command::SUCCESS;
        }

        try {
            $this->exerciseConfigs->beginTransaction();

            // make sure internal pipeline caches are populated
            $this->loadPipelines($runtimeId);

            // take all configs one by one regardles of their exercises
            $configs = $this->exerciseConfigs->findAll();
            $updated = $failed = 0;
            foreach ($configs as $configEntity) {
                $configId = $configEntity->getId();
                $config = $configEntity->getParsedConfig();
                if (!in_array($runtimeId, $config['environments'] ?? []) || empty($config['tests'])) {
                    continue;
                }

                // try to fix the config and get a list of changes...
                $changes = $this->fixTestsPipelines($config['tests'], $runtimeId);

                if ($changes === false) {
                    // error occured
                    $this->writeln("Cannot fix exercise config $configId, pipelines cannot be matched.");
                    ++$failed;
                } elseif ($changes) {
                    // some changes are present -> save updated config and report
                    $configEntity->overrideConfig($config);
                    $this->exerciseConfigs->persist($configEntity);

                    $this->writeln("Exercise config $configId has been fixed:");
                    foreach ($changes as $oldId => $newId) {
                        $this->writeln("\t$oldId => $newId");
                    }
                    ++$updated;
                }
            }

            $this->exerciseConfigs->commit();
            $this->writeln("Total $updated configs were updated, $failed configs are still possibly broken.");
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
