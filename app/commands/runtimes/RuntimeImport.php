<?php

namespace App\Console;

use App\Model\Repository\RuntimeEnvironments;
use App\Model\Repository\Pipelines;
use App\Model\Repository\UploadedFiles;
use App\Model\Entity\RuntimeEnvironment;
use App\Model\Entity\Pipeline;
use App\Model\Entity\SupplementaryExerciseFile;
use App\Helpers\TmpFilesHelper;
use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\ZipFileStorage;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Validator as ConfigValidator;
use ZipArchive;
use DateTime;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class RuntimeImport extends BaseCommand
{
    protected static $defaultName = 'runtimes:import';

    /** @var bool */
    private $silent = false;

    // injections

    /** @var RuntimeEnvironments */
    private $runtimeEnvironments;

    /** @var Pipelines */
    private $pipelines;

    /** @var UploadedFiles */
    private $uploadedFiles;

    /** @var TmpFilesHelper */
    private $tmpFilesHelper;

    /** @var FileStorageManager */
    private $fileManager;

    /** @var Loader */
    private $exerciseConfigLoader;

    /** @var ConfigValidator */
    private $configValidator;

    public function __construct(
        RuntimeEnvironments $runtimeEnvironments,
        Pipelines $pipelines,
        UploadedFiles $uploadedFiles,
        FileStorageManager $fileManager,
        TmpFilesHelper $tmpFilesHelper,
        Loader $exerciseConfigLoader,
        ConfigValidator $configValidator
    ) {
        parent::__construct();
        $this->runtimeEnvironments = $runtimeEnvironments;
        $this->pipelines = $pipelines;
        $this->uploadedFiles = $uploadedFiles;
        $this->fileManager = $fileManager;
        $this->tmpFilesHelper = $tmpFilesHelper;
        $this->exerciseConfigLoader = $exerciseConfigLoader;
        $this->configValidator = $configValidator;
    }

    protected function configure()
    {
        $this->setName(self::$defaultName)->setDescription(
            'Import runtime environment and its pipelines from a ZIP package.'
        )
        ->addArgument(
            'zipFile',
            InputArgument::REQUIRED,
            'Path to the ZIP package from which the data will be loaded.'
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

    /*
     * Declarations for schema validation
     */

    private const MANIFEST_SCHEMA = [
        'pkgVersion' => 'integer',
        'runtime' => [
            'id' => 'string',
            'name' => 'string',
            'longName' => 'string',
            'extensions' => 'string',
            'platform' => 'string',
            'description' => 'string',
            'defaultVariables' => 'array',
        ],
        'pipelines' => 'array',
    ];

    private const PIPELINE_SCHEMA = [
        'id' => 'string',
        'name' => 'string',
        'version' => 'integer',
        'createdAt' => 'integer',
        'updatedAt' => 'integer',
        'description' => 'string',
        'supplementaryFiles' => 'array',
        'parameters' => [
            'isCompilationPipeline' => 'boolean',
            'isExecutionPipeline' => 'boolean',
            'judgeOnlyPipeline' => 'boolean',
            'producesStdout' => 'boolean',
            'producesFiles' => 'boolean',
            'hasEntryPoint' => 'boolean',
            'hasExtraFiles' => 'boolean',
            'hasSuccessExitCodes' => 'boolean',
        ],
    ];

    private const SUPPLEMENTARY_FILE_SCHEMA = [
        'name' => 'string',
        'uploadedAt' => 'integer',
        'size' => 'integer',
        'hash' => 'string',
    ];

    /**
     * Helper function that validates structure against a schema.
     * @param array $obj structure to be validated
     * @param array $schema structure descriptor
     * @param array $prefix list of keys that lead to $obj, so we can properly report errors in nested structures
     * @throws RuntimeException if validation fails
     */
    protected static function validate(array $obj, array $schema, array $prefix = []): void
    {
        foreach ($schema as $key => $desc) {
            // check existence
            $path = $prefix ? ("'" . join("' / '", $prefix) . "'") : '<root>';
            if (!array_key_exists($key, $obj)) {
                throw new RuntimeException("Property '$key' is missing in $path structure.");
            }

            // check type
            $type = is_array($desc) ? 'array' : $desc;
            $actualType = gettype($obj[$key]);
            if ($actualType !== $type) {
                throw new RuntimeException(
                    "Type of property '$key' (of $path) is $actualType, but $type was expected."
                );
            }

            // check substructures recursively
            if (is_array($desc)) {
                self::validate($obj[$key], $desc, array_merge($prefix, [$key]));
            }
        }
    }

    /**
     * Load JSON file from ZIP package and parse it.
     * @param ZipArchive $zip
     * @param string $entry which will be loaded
     * @return array parsed JSON structure
     */
    protected static function loadZipJson(ZipArchive $zip, string $entry): array
    {
        $str = $zip->getFromName($entry);
        if (!$str) {
            throw new RuntimeException("No $entry file is present in the ZIP package.");
        }

        $json = json_decode($str, true, 512, JSON_THROW_ON_ERROR);
        if (!$json) {
            throw new RuntimeException("The $entry file is not a valid JSON file.");
        }

        return $json;
    }

    /**
     * Load the manifest JSON file and validate its structure and the presence of referred files in the ZIP.
     * @param ZipArchive $zip
     * @return array loaded manifest structure
     * @throws RuntimeException if loading or validation fails
     */
    protected static function loadManifest(ZipArchive $zip): array
    {
        $manifest = self::loadZipJson($zip, 'manifest.json');

        // schema validation
        self::validate($manifest, self::MANIFEST_SCHEMA);
        if ($manifest['pkgVersion'] !== 1) {
            throw new RuntimeException(
                "Invalid package version (only version 1 is accepted by current implementation)."
            );
        }
        foreach ($manifest['pipelines'] as $pidx => $pipeline) {
            self::validate($pipeline, self::PIPELINE_SCHEMA, ['pipelines', $pidx]);
            foreach ($pipeline['supplementaryFiles'] as $fidx => $file) {
                self::validate(
                    $file,
                    self::SUPPLEMENTARY_FILE_SCHEMA,
                    ['pipelines', $pidx, 'supplementaryFiles', $fidx]
                );
            }
        }

        // verify existence of pipelines and supplementary files
        foreach ($manifest['pipelines'] as $pipeline) {
            $pipelineEntry = $pipeline['id'] . '.json';
            if ($zip->statName($pipelineEntry) === false) {
                throw new RuntimeException("Pipeline structure file $pipelineEntry is missing in the package.");
            }

            foreach ($pipeline['supplementaryFiles'] as $file) {
                $fileEntry = $pipeline['id'] . '/' . $file['name'];
                $stats = $zip->statName($fileEntry);
                if ($stats === false || $stats['size'] !== $file['size']) {
                    throw new RuntimeException("Supplementary file $fileEntry is missing or corrupted.");
                }
            }
        }

        return $manifest;
    }

    /**
     * Load the pipeline structure from its JSON file and validate it.
     * @param ZipArchive $zip
     * @param string $id of the pipeline
     * @param Pipeline $pipelineEntity
     * @return array pipeline structure
     * @throws \App\Exceptions\ExerciseConfigException
     */
    protected function loadPipeline(ZipArchive $zip, string $id, Pipeline $pipelineEntity): array
    {
        $pipeline = self::loadZipJson($zip, "$id.json");

        // validate pipeline configuration
        $pipelineConfig = $this->exerciseConfigLoader->loadPipeline($pipeline);
        $oldConfig = $pipelineEntity->getPipelineConfig();
        $this->configValidator->validatePipeline($pipelineEntity, $pipelineConfig); // throws on error

        return $pipeline;
    }

    /**
     * Print out how the runtime will be updated.
     * @param array $data loaded runtime structure to be written
     */
    protected function printRuntimeUpdateInfo(array $data): void
    {
        $id = $data['id'];
        $runtime = $this->runtimeEnvironments->get($id);
        if ($runtime) {
            $old = [
                'name' => $runtime->getName(),
                'longName' => $runtime->getLongName(),
                'extensions' => $runtime->getExtensions(),
                'platform' => $runtime->getPlatform(),
                'description' => $runtime->getDescription(),
            ];
            $this->output->writeln("Runtime $id already exists, the followig fields will be updated:");
            foreach ($old as $key => $value) {
                if ($value !== $data[$key]) {
                    $this->output->writeln("\t[$key]: $value => $data[$key]");
                }
            }
            $this->output->writeln('');
        } else {
            $this->output->writeln("Runtime $id will be created.");
            $this->output->writeln('');
        }
    }

    /**
     * Helper function that converts pipeline entity into human-readable string to be printed out.
     * @param Pipeline|null $pipeline to be rendered as string
     * @return string
     */
    private static function renderPipelineStr(?Pipeline $pipeline): string
    {
        if ($pipeline === null) {
            return "<none, create new pipeline instead>";
        }

        $name = $pipeline->getName();
        $author = $pipeline->getAuthor();
        $author = $author ? $author->getName() : 'global';
        $version = $pipeline->getVersion();

        $environments = array_map(function ($env) {
            return $env->getId();
        }, $pipeline->getRuntimeEnvironments()->getValues());
        $environments = $environments ? join(', ', $environments) : '--';

        return "$name ($author, v$version); used in $environments";
    }

    /**
     * List the pipelines to be written and how they affect existing pipelines.
     * @param array $pipelines loaded pipeline metadata
     * @param array $targetPipelines matched existing pipeline entites
     */
    protected function printPipelineUpdateInfo(array $pipelines, array $targetPipelines): void
    {
        $this->output->writeln("Pipelines:");
        foreach ($pipelines as $pipeline) {
            $id = $pipeline['id'];
            $name = $pipeline['name'];
            $target = $targetPipelines[$id] ?? null;

            $this->output->writeln("- $id\t{$pipeline['name']} (v{$pipeline['version']})");
            if ($target) {
                $this->output->writeln("\t...will overwrite pipeline:");
                $this->output->writeln("\t" . self::renderPipelineStr($target));
            } else {
                $this->output->writeln("\t...will be created.");
            }
        }
    }

    /**
     * Update existing runtime environment or create a new one.
     * @param array $data loaded JSON structure with runtime data
     * @return RuntimeEnvironment entity object (already persisted)
     */
    protected function updateRuntime(array $data): RuntimeEnvironment
    {
        $id = $data['id'];
        $runtime = $this->runtimeEnvironments->get($id);
        if ($runtime) { // if does not exist, create new
            $runtime->setName($data['name']);
            $runtime->setLongName($data['longName']);
            $runtime->setExtensions($data['extensions']);
            $runtime->setPlatform($data['platform']);
            $runtime->setDescription($data['description']);
            $runtime->setDefaultVariables($data['defaultVariables']);

            // remove all existing pipelines from this environment
            foreach ($this->pipelines->getRuntimeEnvironmentPipelines($id) as $pipeline) {
                $pipeline->removeRuntimeEnvironment($runtime);
                $this->pipelines->persist($pipeline);
            }
        } else {
            $runtime = new RuntimeEnvironment(
                $id,
                $data['name'],
                $data['longName'],
                $data['extensions'],
                $data['platform'],
                $data['description'],
                $data['defaultVariables']
            );
        }

        $this->runtimeEnvironments->persist($runtime);
        return $runtime;
    }

    /**
     * Write the pipeline metadata, parameters, and runtime affiliation (not the config).
     * @param Pipeline|null $pipeline to be updated, if null, new pipeline is created
     * @param array $data JSON structure with pipeline metadata and parameters
     * @return Pipeline entity that was created or updated
     */
    protected function updatePipeline(?Pipeline $pipeline, array $data, RuntimeEnvironment $runtime): Pipeline
    {
        if (!$pipeline) {
            $pipeline = Pipeline::create(null);
        }

        $pipeline->setName($data['name']);
        $pipeline->overrideVersion($data['version']);
        $pipeline->overrideCreatedAt(DateTime::createFromFormat('U', $data['createdAt']));
        $pipeline->overrideUpdatedAt(DateTime::createFromFormat('U', $data['updatedAt']));
        $pipeline->setDescription($data['description']);
        $pipeline->setParameters($data['parameters']);

        $pipeline->overrideCreatedFrom(null);
        $pipeline->addRuntimeEnvironment($runtime);

        // make sure list of supplementary files is empty (they will be added later)
        foreach ($pipeline->getSupplementaryEvaluationFiles()->toArray() as $file) {
            $this->uploadedFiles->remove($file, false);
        }
        $pipeline->getSupplementaryEvaluationFiles()->clear();

        $this->pipelines->persist($pipeline);
        return $pipeline;
    }

    /**
     * Find out existing pipeline entity that corresponds to given loaded record.
     * If ambigous, the user is interactively prompted to select the right pipeline.
     * @param array $data pipeline structure loaded from manifest
     * @return Pipeline|null matching DB entity or null, if no match is found
     */
    protected function getTargetPipeline(array $data): ?Pipeline
    {
        $pipeline = $this->pipelines->get($data['id']);
        if ($pipeline) {
            return $pipeline; // exact match by ID, what more could we hoped for
        }

        $matchingPipelines = $this->pipelines->findByName($data['name']);
        if ($matchingPipelines) {
            if (count($matchingPipelines) > 1) {
                // ambiguous, we need to select the right one
                $matchingPipelines[] = null; // add empty option (new pipeline) at the end
                return $this->select(
                    "Pipeline names are ambiguous. Which pipeline should be overwritten with '{$data['name']}'?",
                    $matchingPipelines,
                    [self::class, 'renderPipelineStr']
                );
            } else {
                return reset($matchingPipelines);
            }
        }

        return null; // if everything fails, new pipeline is created
    }

    /**
     * Save supplementary files in file storage and update their DB records associated with pipeline.
     * @param ZipArchive $zip
     * @param array $data of pipeline loaded from manifest
     * @param Pipeline $pipeline entity to which the supplementary files are associated
     */
    protected function updatePipelineSupplementaryFiles(ZipArchive $zip, array $data, Pipeline $pipeline): void
    {
        // copy all files from zip to hash storage
        $id = $data['id'];
        foreach ($data['supplementaryFiles'] as &$supFile) {
            $tmp = $this->tmpFilesHelper->createTmpFile('rexcmd');
            ZipFileStorage::extractZipEntryToFile($zip, '', "$id/{$supFile['name']}", $tmp);
            $supFile['hash'] = $this->fileManager->storeSupplementaryFile($tmp, true); // true = move (to save time)
        }
        unset($supFile); // safely dispose of a reference

        // create new supplementary files records
        foreach ($data['supplementaryFiles'] as $supFile) {
            $file = new SupplementaryExerciseFile(
                $supFile['name'],
                DateTime::createFromFormat('U', $supFile['uploadedAt']),
                $supFile['size'],
                $supFile['hash'],
                null, // user
                null, // exercise
                $pipeline
            );
            $this->uploadedFiles->persist($file, false);
        }

        $this->pipelines->persist($pipeline);
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

        try {
            $this->runtimeEnvironments->beginTransaction();
            $fileName = $input->getArgument('zipFile');

            // open the ZIP archive for reading
            $zip = new ZipArchive();
            $opened = $zip->open($fileName, ZipArchive::RDONLY);
            if ($opened !== true) {
                throw new RuntimeException("Unable to open file '$fileName' for reading (code $opened).");
            }

            $manifest = self::loadManifest($zip);
            $targetPipelines = []; // manifest pipelineId => existing entity (or null)
            foreach ($manifest['pipelines'] as $pipelineData) {
                $targetPipelines[$pipelineData['id']] = $this->getTargetPipeline($pipelineData);
            }

            if (!$this->silent) {
                $this->printRuntimeUpdateInfo($manifest['runtime']);
                $this->printPipelineUpdateInfo($manifest['pipelines'], $targetPipelines);
            }

            // confirm the modifications
            if (!$this->nonInteractive && !$this->confirm("Do you wish to proceed with modifications? ")) {
                $this->runtimeEnvironments->rollback();
                $zip->close();
                return Command::SUCCESS;
            }

            // let's do the modifications...
            $runtime = $this->updateRuntime($manifest['runtime']);

            foreach ($manifest['pipelines'] as $pipelineData) {
                // select pipeline entity for update
                $pipeline = $targetPipelines[$pipelineData['id']] ?? null;
                $pipeline = $this->updatePipeline($pipeline, $pipelineData, $runtime);

                // files must go first, so they are up to date for pipeline verification
                $this->updatePipelineSupplementaryFiles($zip, $pipelineData, $pipeline);

                // update config structure
                $pipelineConfigData = $this->loadPipeline($zip, $pipelineData['id'], $pipeline);
                $pipelineConfig = $pipeline->getPipelineConfig();
                $pipelineConfig->overridePipelineConfig($pipelineConfigData);
                $this->pipelines->persist($pipelineConfig);
            }

            $this->runtimeEnvironments->commit();
            if (!$this->silent) {
                $output->writeln("Updates completed.");
            }

            $zip->close();
        } catch (Exception $e) {
            $this->runtimeEnvironments->rollback();
            $msg = $e->getMessage();
            $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $stderr->writeln("Error: $msg");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
