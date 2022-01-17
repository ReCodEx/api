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
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Helper\QuestionHelper;

class RuntimeImport extends Command
{
    protected static $defaultName = 'runtimes:import';

    /** @var bool */
    private $nonInteractive = false;

    /** @var bool */
    private $silent = false;

    /** @var InputInterface|null */
    private $input = null;

    /** @var OutputInterface|null */
    private $output = null;

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
        ->addArgument('runtime', InputArgument::REQUIRED, 'ID of the runtime environment to be imported.')
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

    /**
     * Wrapper for confirmation question.
     * @param string $text message of the question
     * @param bool $default value when the query is confirmed hastily
     * @return bool true if the user confirmed the inquiry
     */
    protected function confirm(string $text, bool $default = false): bool
    {
        if (!$this->input || !$this->output) {
            throw new RuntimeException("The confirm() method may be invoked only when the command is executed.");
        }

        if ($this->nonInteractive) {
            return true; // assume "yes"
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion($text, $default);
        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Helper that convert numeric index into letter-based encoding.
     * (0 = a, ..., 25 = z, 26 = aa, 27 = ab, ...)
     * @param int $idx zero based index
     * @return string encoded representation
     */
    private static function indexToLetters(int $idx): string
    {
        $res = '';
        do {
            $letter = chr(($idx % 26) + ord('a'));
            $res = "$letter$res";
            $idx = (int)($idx / 26) - 1;
        } while ($idx >= 0);
        return $res;
    }

    /**
     * Perform a select inquery so the user chooses from given options.
     * @param string $text of the inquery
     * @param array $options to choose from
     * @param callable|null $renderer explicit to-string converter for options
     * @return mixed selected option value
     */
    protected function select(string $text, array $options, ?callable $renderer = null)
    {
        if (!$this->input || !$this->output) {
            throw new RuntimeException("The select() method may be invoked only when the command is executed.");
        }

        if (count($options) === 1) {
            return reset($options); // only one item to choose from
        }

        if ($this->nonInteractive) {
            throw new RuntimeException(
                "Unable preform the '$text' inquery in non-interactive mode. Operation aborted."
            );
        }

        // wrap the options into strings with a, b, c, d ... selection keys
        $internalOptions = [];
        $translateBack = [];
        foreach (array_values($options) as $idx => $option) {
            $key = self::indexToLetters($idx);
            $internalOptions[$key] = $renderer ? $renderer($option) : $option;
            $translateBack[$key] = $option;
        }

        // make the inquery
        QuestionHelper::disableStty();
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion($text, $options, 0);
        $question->setErrorMessage('Invalid input.');

        // translate the selection back to an option and report it
        $selectedKey = $helper->ask($this->input, $this->output, $question);
        return array_key_exists($selectedKey, $translateBack) ? $translateBack[$selectedKey] : null;
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
            $path = $prefix ? ("'" . join($prefix, "' / '") . "'") : '<root>';
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
            // TODO: ZipArchive::RDONLY flag would be nice here, but it requires PHP 7.4.3+
            $opened = $zip->open($fileName);
            if ($opened !== true) {
                throw new RuntimeException("Unable to open file '$fileName' for writing (code $opened).");
            }

            $manifest = self::loadManifest($zip);
            $targetPipelines = [];
            foreach ($manifest['pipelines'] as $pipelineData) {
                $targetPipelines[$pipelineData['id']] = $this->getTargetPipeline($pipelineData);
            }

            if (!$this->silent) {
                $this->printRuntimeUpdateInfo($manifest['runtime']);
                $this->printPipelineUpdateInfo($manifest['pipelines'], $targetPipelines);
            }

            // confirm the modifications
            if (!$this->nonInteractive && !$this->confirm("Do you wish to proceed with modifications?")) {
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

                // update config structure
                $pipelineConfigData = $this->loadPipeline($zip, $pipelineData['id'], $pipeline);
                $pipelineConfig = $pipeline->getPipelineConfig();
                $pipelineConfig->overridePipelineConfig($pipelineConfigData);
                $this->pipelines->persist($pipelineConfig);

                $this->updatePipelineSupplementaryFiles($zip, $pipelineData, $pipeline);
            }

            $this->runtimeEnvironments->commit();
            if (!$this->silent) {
                $output->writeln("Updates completed.");
            }

            $zip->close();
        } catch (Exception $e) {
            $this->runtimeEnvironments->rollback();
            $msg = $e->getMessage();
            $output->writeln("Error: $msg");
            return 1;
        }

        return Command::SUCCESS;
    }
}
