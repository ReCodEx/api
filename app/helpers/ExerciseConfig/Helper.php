<?php

namespace App\Helpers\ExerciseConfig;
use App\Exceptions\ExerciseConfigException;
use App\Helpers\Evaluation\IExercise;
use App\Model\Repository\Pipelines;


/**
 * Helper class where some handy functions regarding exercise configuration
 * can be declared.
 */
class Helper {

  /**
   * @var Loader
   */
  private $loader;

  /**
   * @var Pipelines
   */
  private $pipelines;

  /**
   * Constructor
   * @param Loader $loader
   * @param Pipelines $pipelines
   */
  public function Helper(Loader $loader, Pipelines $pipelines) {
    $this->loader = $loader;
    $this->pipelines = $pipelines;
  }


  /**
   * Join pipelines and find variables which needs to be defined by either environment config or exercise config.
   * @param Pipeline[] $pipelines indexed by pipeline id
   * @param array $inputs Pairs of pipeline identifier and port indexed by variable name
   * @param array $references Pairs of pipeline identifier and variable indexed by variable name
   */
  private function joinPipelinesAndGetInputVariables(array $pipelines, array& $inputs, array& $references) {
    $outputs = []; // pairs of pipeline identifier and port indexed by variable name
    foreach ($pipelines as $pipelineId => $pipeline) {
      $result[$pipelineId] = [];

      // load pipeline input variables
      $localInputs = [];
      foreach ($pipeline->getDataInBoxes() as $dataInBox) {
        foreach ($dataInBox->getOutputPorts() as $outputPort) {
          $localInputs[$outputPort->getVariable()] = [$pipelineId, $outputPort];
        }
      }

      // find reference variables and add them to inputs
      foreach ($pipeline->getVariablesTable()->getAll() as $variable) {
        if ($variable->isReference()) {
          $references[$variable->getName()] = [$pipelineId, $variable];
        }
      }

      // load pipeline output variables
      $localOutputs = [];
      foreach ($pipeline->getDataOutBoxes() as $dataOutBox) {
        foreach ($dataOutBox->getInputPorts() as $inputPort) {
          $localOutputs[$inputPort->getVariable()] = [$pipelineId, $inputPort];
        }
      }

      // remove variables which join pipelines
      foreach (array_keys($outputs) as $variableName) {
        if (!array_key_exists($variableName, $localInputs)) {
          // output variable cannot be found in local input variables, this
          // means that output variable is not joining with any input variable,
          // at least for now
          continue;
        }

        unset($outputs[$variableName]);
        unset($localInputs[$variableName]);
      }

      // merge variables
      $inputs = array_merge($inputs, $localInputs);
      $outputs = array_merge($outputs, $localOutputs);
    }
  }

  /**
   * Get variables which exercise configuration should include for given
   * pipelines. During computations pipelines are merged and variables checked
   * against environment configuration. Only inputs are returned as result.
   * @param Pipeline[] $pipelines indexed by pipeline id
   * @param VariablesTable $environmentVariables
   * @return array
   * @throws ExerciseConfigException
   */
  public function getVariablesForExercise(array $pipelines, VariablesTable $environmentVariables): array {

    // process input variables from pipelines
    $inputs = []; // pairs of pipeline identifier and port indexed by variable name
    $references = []; // pairs of pipeline identifier and variable indexed by variable name
    $this->joinPipelinesAndGetInputVariables($pipelines, $inputs, $references);

    // initialize results array
    $result = [];
    foreach ($pipelines as $pipelineId => $pipeline) {
      $result[$pipelineId] = [];
    }

    // go through inputs and assign them to result
    foreach ($inputs as $variableName => $pair) {
      if ($environmentVariables->get($variableName)) {
        // variable is defined in environment variables
        continue;
      }

      $port = $pair[1];
      if ($port->isFile() && $port->isArray()) {
        // port is file and also array, in exercise config if there should be
        // defined file as variable it is expected to be remote file, so the
        // remote file type is offered back to web-app
        $variable = (new Variable(VariableTypes::$REMOTE_FILE_ARRAY_TYPE))->setName($variableName);
      } else if ($port->isFile() && !$port->isArray()) {
        // port is file and not an array, in exercise config if there should be
        // defined file as variable it is expected to be remote file, so the
        // remote file type is offered back to web-app
        $variable = (new Variable(VariableTypes::$REMOTE_FILE_TYPE))->setName($variableName);
      } else {
        $variable = (new Variable($port->getType()))->setName($variableName);
      }
      $result[$pair[0]][] = $variable;
    }
    foreach ($references as $pair) {
      $variableName = $pair[1]->getReference();
      if ($environmentVariables->get($variableName)) {
        // variable is defined in environment variables
        continue;
      }

      $variable = (new Variable($pair[1]->getType()))->setName($variableName);
      $result[$pair[0]][] = $variable;
    }
    return $result;
  }

  /**
   * Get list of runtime environments identifications for given exercise and
   * submitted files.
   * @param IExercise $exercise
   * @param string[] $files
   * @return string[]
   * @throws ExerciseConfigException
   */
  public function getEnvironmentsForFiles(IExercise $exercise, array $files): array {
    $envStatuses = [];

    $envConfigs = [];
    foreach ($exercise->getRuntimeEnvironments() as $environment) {
      $envConfig = $this->loader->loadVariablesTable($exercise->getExerciseEnvironmentConfigByEnvironment($environment));
      $envConfigs[$environment->getId()] = $envConfig;
      $envStatuses[$environment->getId()] = true;
    }

    $config = $this->loader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());
    foreach ($config->getTests() as $testId => $test) {
      foreach ($test->getEnvironments() as $envId => $env) {
        $envConfig = $envConfigs[$envId];

        // load all pipelines for this test and environment
        $pipelines = [];
        foreach ($env->getPipelines() as $pipelineId => $pipeline) {
          $pipelineEntity = $this->pipelines->findOrThrow($pipelineId);
          $pipelineConfig = $this->loader->loadPipeline($pipelineEntity->getPipelineConfig());
          $pipelines[$pipelineId] = $pipelineConfig;
        }

        // join pipelines and return inputs from all of them
        $inputs = []; // pairs of pipeline identifier and port indexed by variable name
        $references = []; // pairs of pipeline identifier and variable indexed by variable name
        $this->joinPipelinesAndGetInputVariables($pipelines, $inputs, $references);

        // TODO: go through inputs and find local files, then wildcard them and check them
      }
    }

    // go through all envirovnment statuses and if environment is suitable for given files,
    // return it in resulting array
    $result = [];
    foreach ($envStatuses as $envId => $status) {
      if ($status) {
        $result[] = $envId;
      }
    }

    return $result;
  }

}
