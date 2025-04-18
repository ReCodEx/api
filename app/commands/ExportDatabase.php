<?php

namespace App\Console;

use App\Model\Entity\HardwareGroup;
use App\Model\Entity\Pipeline;
use App\Model\Entity\PipelineConfig;
use App\Model\Entity\RuntimeEnvironment;
use App\Model\Repository\HardwareGroups;
use App\Model\Repository\Pipelines;
use App\Model\Repository\RuntimeEnvironments;
use Nette\Neon\Neon;
use Nette\Utils\FileSystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Export data from database into Doctrine Fixtures. Exported data are stored in
 * YAML file in fixtures/generated directory. Also, 'db:export' command is
 * registered to provide convenient usage of this function.
 */
class ExportDatabase extends Command
{
    protected static $defaultName = 'db:export';

    private const EXTENSION = ".neon";
    private const PARAMETERS_KEY = "parameters";

    /**
     * @var RuntimeEnvironments
     */
    private $runtimeEnvironments;

    /**
     * @var Pipelines
     */
    private $pipelines;

    /**
     * @var HardwareGroups
     */
    private $hardwareGroups;

    /**
     * Constructor
     * @param RuntimeEnvironments $runtimeEnvironments
     * @param Pipelines $pipelines
     * @param HardwareGroups $hardwareGroups
     */
    public function __construct(
        RuntimeEnvironments $runtimeEnvironments,
        Pipelines $pipelines,
        HardwareGroups $hardwareGroups
    ) {
        parent::__construct();
        $this->runtimeEnvironments = $runtimeEnvironments;
        $this->pipelines = $pipelines;
        $this->hardwareGroups = $hardwareGroups;
    }

    /**
     * Register the 'db:export' command in the framework
     */
    protected function configure()
    {
        $this->setName('db:export')->setDescription('Export some of the data from database.');
    }

    /**
     * Execute the database exporting.
     * @param InputInterface $input Console input, not used
     * @param OutputInterface $output Console output for logging
     * @return int 0 on success, 1 on error
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fixtureDir = __DIR__ . '/../../fixtures/generated/';
        FileSystem::createDir($fixtureDir);

        // export data from database
        $this->exportRuntimes($fixtureDir);
        $this->exportPipelines($fixtureDir);
        $this->exportHardwareGroups($fixtureDir);

        $output->writeln('<info>[OK] - DB:EXPORT</info>');
        return 0;
    }

    /**
     * Helper function which will encode array like input to neon formatted string.
     * @param mixed $content
     * @return string
     */
    private function encodeResult($content): string
    {
        return Neon::encode($content, true);
    }

    /**
     * Replace CRLF newlines with the Unix ones.
     * @param string $content
     * @return string
     */
    private function correctDbNewlines(string $content): string
    {
        return preg_replace('~\r\n?~', "\n", $content);
    }

    /**
     * All strings in nelmio/alice are evaluated, that means characters like $,
     * [ or @ are interpreted. To overcome this behavior (for example if storing
     * markdown or yaml) it is recommended to use parameters which are not
     * evaluated and therefore can contain any string. Note that escaping
     * aforementioned characters does not work as expected, so parameters are
     * the way to go.
     * @param string $name identification of the parameter
     * @param string $value value of the parameter
     * @param array $parameters reference to parameters array to which parameter
     * should be added
     * @return string reference name pointing to the correct parameter
     */
    private function addParameterAndGetReference(string $name, string $value, array &$parameters): string
    {
        $paramName = "param_" . $name;
        $parameters[$paramName] = $value;
        return "<{" . $paramName . "}>";
    }

    private function exportHardwareGroups(string $fixtureDir)
    {
        $content = [];
        $parameters = [];
        $content[self::PARAMETERS_KEY] = [];
        $content[HardwareGroup::class] = [];

        foreach ($this->hardwareGroups->findBy([], ["id" => "ASC"]) as $group) {
            /** @var HardwareGroup $group */

            $constructArr = [];
            $constructArr[] = $group->getId();
            $constructArr[] = $group->getName();
            $constructArr[] = $this->correctDbNewlines($group->getDescription());
            $constructArr[] = $this->addParameterAndGetReference(
                $group->getId(),
                $this->correctDbNewlines($group->getMetadataString()),
                $parameters
            );

            $groupArr = [];
            $groupArr["__construct"] = $constructArr;

            $content[HardwareGroup::class][$group->getId()] = $groupArr;
        }

        $content[self::PARAMETERS_KEY] = $parameters;
        FileSystem::write($fixtureDir . "10-hwGroups" . self::EXTENSION, $this->encodeResult($content));
    }

    private function exportRuntimes($fixtureDir)
    {
        $content = [];
        $parameters = [];
        $content[self::PARAMETERS_KEY] = [];
        $content[RuntimeEnvironment::class] = [];

        foreach ($this->runtimeEnvironments->findBy([], ["id" => "ASC"]) as $runtime) {
            /** @var RuntimeEnvironment $runtime */

            $constructArr = [];
            $constructArr[] = $runtime->getId();
            $constructArr[] = $runtime->getName();
            $constructArr[] = $runtime->getLongName();
            $constructArr[] = $this->addParameterAndGetReference(
                "extension_" . $runtime->getId(),
                $runtime->getExtensions(),
                $parameters
            );
            $constructArr[] = $runtime->getPlatform();
            $constructArr[] = $runtime->getDescription();
            $constructArr[] = $this->addParameterAndGetReference(
                "defVariables_" . $runtime->getId(),
                $this->correctDbNewlines($runtime->getDefaultVariables()),
                $parameters
            );

            $runtimeArr = [];
            $runtimeArr["__construct"] = $constructArr;

            $content[RuntimeEnvironment::class][$runtime->getId()] = $runtimeArr;
        }

        $content[self::PARAMETERS_KEY] = $parameters;
        FileSystem::write($fixtureDir . "10-runtimes" . self::EXTENSION, $this->encodeResult($content));
    }

    private function exportPipelines($fixtureDir)
    {
        $content = [];
        $parameters = [];
        $content[self::PARAMETERS_KEY] = [];
        $content[PipelineConfig::class] = [];
        $content[Pipeline::class] = [];

        // pipelines cache... first we have to process pipeline configurations
        // indexed by pipeline config fixtures identification
        $pipelines = [];

        $index = 0;
        foreach ($this->pipelines->findBy(["author" => null], ["name" => "ASC"]) as $pipeline) {
            /** @var Pipeline $pipeline */

            $index++;
            $configId = "pipelineConfig" . $index;
            $config = $pipeline->getPipelineConfig();
            $pipelines[$configId] = $pipeline;

            // create yaml config
            $constructArr = [];
            $constructArr[] = $this->addParameterAndGetReference(
                "config_" . $configId,
                $this->correctDbNewlines($config->getPipelineConfig()),
                $parameters
            );
            $constructArr[] = "@demoAdmin";

            $configArr = [];
            $configArr["__construct"] = $constructArr;
            $content[PipelineConfig::class][$configId] = $configArr;
        }

        $index = 0;
        foreach ($pipelines as $configId => $pipeline) {
            $index++;
            $pipelineId = "pipeline" . $index;

            $constructArr = [];
            $constructArr["create"] = [];
            $constructArr["create"][] = null;

            $pipelineArr = [];
            $pipelineArr["__construct"] = $constructArr;
            $pipelineArr["name"] = $this->addParameterAndGetReference(
                "name_" . $pipelineId,
                $pipeline->getName(),
                $parameters
            );
            $pipelineArr["description"] = $this->addParameterAndGetReference(
                "description_" . $pipelineId,
                $this->correctDbNewlines($pipeline->getDescription()),
                $parameters
            );
            $pipelineArr["pipelineConfig"] = "@" . $configId;

            $pipelineArr["runtimeEnvironments"] = array_map(
                function (RuntimeEnvironment $env) {
                    return sprintf("@%s", $env->getId());
                },
                $pipeline->getRuntimeEnvironments()->getValues()
            );

            $content[Pipeline::class][$pipelineId] = $pipelineArr;

            foreach ($pipeline->getParameters() as $parameter) {
                $content[get_class($parameter)][sprintf("pipeline%d_%s", $index, $parameter->getName())] = [
                    "__construct" => [
                        "@pipeline" . $index,
                        $parameter->getName(),
                    ],
                    "value" => $parameter->getValue(),
                ];
            }
        }

        $content[self::PARAMETERS_KEY] = $parameters;
        FileSystem::write($fixtureDir . "15-pipelines" . self::EXTENSION, $this->encodeResult($content));
    }
}
