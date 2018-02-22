<?php

namespace App\Console;

use App\Model\Entity\HardwareGroup;
use App\Model\Entity\Pipeline;
use App\Model\Entity\PipelineConfig;
use App\Model\Entity\RuntimeEnvironment;
use App\Model\Repository\HardwareGroups;
use App\Model\Repository\Pipelines;
use App\Model\Repository\RuntimeEnvironments;
use Kdyby\Doctrine\EntityManager;
use Nette\Neon\Encoder;
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
class ExportDatabase extends Command {

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
  public function __construct(RuntimeEnvironments $runtimeEnvironments,
      Pipelines $pipelines, HardwareGroups $hardwareGroups) {
    parent::__construct();
    $this->runtimeEnvironments = $runtimeEnvironments;
    $this->pipelines = $pipelines;
    $this->hardwareGroups = $hardwareGroups;
  }

  /**
   * Register the 'db:export' command in the framework
   */
  protected function configure() {
    $this->setName('db:export')->setDescription('Export some of the data from database.');
  }

  /**
   * Execute the database exporting.
   * @param InputInterface $input Console input, not used
   * @param OutputInterface $output Console output for logging
   * @return int 0 on success, 1 on error
   */
  protected function execute(InputInterface $input, OutputInterface $output) {

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
   * @param $content
   * @return string
   */
  private function encodeResult($content): string {
    return Neon::encode($content, Encoder::BLOCK);
  }

  /**
   * Replace CRLF newlines with the Unix ones.
   * @param string $content
   * @return string
   */
  private function correctDbNewlines(string $content): string {
    return preg_replace('~\r\n?~', "\n", $content);
  }

  private function exportHardwareGroups(string $fixtureDir) {
    $content = [];
    $content[HardwareGroup::class] = [];

    foreach ($this->hardwareGroups->findAll() as $group) {
      /** @var HardwareGroup $group */

      $constructArr = [];
      $constructArr[] = $group->getId();
      $constructArr[] = $group->getDescription();
      $constructArr[] = $group->getMetadataString();

      $groupArr = [];
      $groupArr["__construct"] = $constructArr;

      $content[HardwareGroup::class][$group->getId()] = $groupArr;
    }

    FileSystem::write($fixtureDir . "10-hwGroups.neon", $this->encodeResult($content));
  }

  private function exportRuntimes($fixtureDir) {
    $content = [];
    $content[RuntimeEnvironment::class] = [];

    foreach ($this->runtimeEnvironments->findAll() as $runtime) {
      /** @var RuntimeEnvironment $runtime */

      $constructArr = [];
      $constructArr[] = $runtime->getId();
      $constructArr[] = $runtime->getName();
      $constructArr[] = $runtime->getLanguage();
      $constructArr[] = $runtime->getExtensions();
      $constructArr[] = $runtime->getPlatform();
      $constructArr[] = $runtime->getDescription();
      $constructArr[] = $this->correctDbNewlines($runtime->getDefaultVariables());

      $runtimeArr = [];
      $runtimeArr["__construct"] = $constructArr;

      $content[RuntimeEnvironment::class][$runtime->getId()] = $runtimeArr;
    }

    FileSystem::write($fixtureDir . "10-runtimes.neon", $this->encodeResult($content));
  }

  private function exportPipelines($fixtureDir) {
    $content = [];
    $content[PipelineConfig::class] = [];
    $content[Pipeline::class] = [];

    // pipelines cache... first we have to process pipeline configurations
    // indexed by pipeline config fixtures identification
    $pipelines = [];

    $index = 0;
    foreach ($this->pipelines->findAll() as $pipeline) {
      /** @var Pipeline $pipeline */

      $index++;
      $configId = "pipelineConfig" . $index;
      $config = $pipeline->getPipelineConfig();
      $pipelines[$configId] = $pipeline;

      // create yaml config
      $constructArr = [];
      $constructArr[] = $this->correctDbNewlines($config->getPipelineConfig());
      $constructArr[] = "@demoAdmin";

      $configArr = [];
      $configArr["__construct"] = $constructArr;
      $content[PipelineConfig::class][$configId] = $configArr;
    }

    $index = 0;
    foreach ($pipelines as $configId => $pipeline) {
      $index++;

      $constructArr = [];
      $constructArr["create"] = [];
      $constructArr["create"][] = "@demoAdmin";

      $pipelineArr = [];
      $pipelineArr["__construct"] = $constructArr;
      $pipelineArr["name"] = $pipeline->getName();
      $pipelineArr["pipelineConfig"] = "@" . $configId;

      $pipelineArr["runtimeEnvironments"] = array_map(function (RuntimeEnvironment $env) {
        return sprintf("@%s", $env->getId());
      }, $pipeline->getRuntimeEnvironments()->getValues());

      $content[Pipeline::class]["pipeline" . $index] = $pipelineArr;

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

    FileSystem::write($fixtureDir . "15-pipelines.neon", $this->encodeResult($content));
  }

}
