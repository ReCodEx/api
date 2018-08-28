<?php

namespace App\Helpers\ExerciseConfig;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\NotFoundException;
use App\Helpers\Evaluation\IExercise;
use App\Helpers\Wildcards;
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
  public function __construct(Loader $loader, Pipelines $pipelines) {
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
   * @throws NotFoundException
   */
  public function getEnvironmentsForFiles(IExercise $exercise, array $files): array {
    $envStatuses = [];

    $envConfigs = [];
    foreach ($exercise->getRuntimeEnvironments() as $environment) {
      $exerciseEnvironment = $exercise->getExerciseEnvironmentConfigByEnvironment($environment);
      if ($exerciseEnvironment === null) {
        throw new ExerciseConfigException("Environment config not found for environment '{$environment->getId()}'");
      }

      $parsedConfig = $exerciseEnvironment->getParsedVariablesTable();
      $envConfig = $this->loader->loadVariablesTable($parsedConfig);
      $envConfigs[$environment->getId()] = $envConfig;
      $envStatuses[$environment->getId()] = true;
    }

    $config = $this->loader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());
    foreach ($config->getTests() as $testId => $test) {
      foreach ($exercise->getRuntimeEnvironments() as $environment) {
        $envConfig = $envConfigs[$environment->getId()];
        $env = $test->getEnvironment($environment->getId());
        if ($env === null) {
          throw new ExerciseConfigException("Environment '{$environment->getId()}' not found in test '$testId'");
        }

        // load all pipelines for this test and environment
        $pipelines = [];
        foreach ($env->getPipelines() as $pipeline) {
          $pipelineId = $pipeline->getId();
          $pipelineEntity = $this->pipelines->findOrThrow($pipelineId);
          $pipelineConfig = $this->loader->loadPipeline($pipelineEntity->getPipelineConfig()->getParsedPipeline());
          $pipelines[$pipelineId] = $pipelineConfig;
        }

        // join pipelines and return inputs from all of them
        $inputs = []; // pairs of pipeline identifier and port indexed by variable name
        $references = []; // pairs of pipeline identifier and variable indexed by variable name
        $this->joinPipelinesAndGetInputVariables($pipelines, $inputs, $references);

        // go through all inputs, references are no use to us
        foreach ($inputs as $varName => $input) {
          if (!$input[1]->isFile()) {
            continue;
          }

          // now we have only file input ports... use them... for the glory of ReCodEx

          // try to find variable in environment config variable table
          $variable = $envConfig->get($varName);
          if ($variable === null) {
            // if variable was not found in environment config, peek into exercise config
            $variable = $env->getPipeline($input[0])->getVariablesTable()->get($varName);
            // TODO: get pipeline has to be removed and something else used...
          }

          // somethings fishy here
          if ($variable === null) {
            throw new ExerciseConfigException("Variable '{$varName}' not found in environment or exercise config");
          }

          // we only seek for the local file variables, not the remote ones...
          // although port might be of type file, variables assigned to it
          // may have remote-file type in case the file should be downloaded
          if (!$variable->isFile()) {
            continue;
          }

          // everything is gonna be ok... now we can do wildcard matching against all variable values
          $matched = true;
          if (false) {
            // just to screw with phpstan which has bug current 0.7 version
            // well... this is just ugly hack :-) I am quite surprised that it
            // worked, but good for me I guess ¯\_(ツ)_/¯
            $matched = false;
          }

          foreach ($variable->getValueAsArray() as $value) {
            $matchedValue = false;
            foreach ($files as $file) {
              if (Wildcards::match($value, $file)) {
                $matchedValue = true;
                break;
              }
            }

            // none of the files matched wildcard in value
            if ($matchedValue === false) {
              $matched = false;
              break;
            }
          }

          // none of the files matched the wildcard from variable values,
          // this means whole environment could not be matched
          if ($matched === false) {
            $envStatuses[$environment->getId()] = false;
          }
        }
      }
    }

    // go through all environment statuses and if environment is suitable for given files,
    // return it in resulting array
    $result = [];
    foreach ($envStatuses as $envId => $status) {
      if ($status) {
        $result[] = $envId;
      }
    }

    return $result;
  }

  private function findSubmitVariablesInVariablesTable(VariablesTable $table) {
    $variables = [];
    foreach ($table->getAll() as $variable) {
      if (!$variable->isReference()) {
        continue;
      }

      $variables[$variable->getReference()] = $variable->getType();
    }

    return $variables;
  }

  /**
   * Get list of submit variables which should be given by user on submit of solution.
   * Variables are divided by environments.
   * @param IExercise $exercise
   * @return array
   * @throws ExerciseConfigException
   */
  public function getSubmitVariablesForExercise(IExercise $exercise): array {
    $envResults = [];
    foreach ($exercise->getRuntimeEnvironments() as $environment) {
      $exerciseEnvironment = $exercise->getExerciseEnvironmentConfigByEnvironment($environment);
      if ($exerciseEnvironment === null) {
        throw new ExerciseConfigException("Environment config not found for environment '{$environment->getId()}'");
      }

      $parsedConfig = $exerciseEnvironment->getParsedVariablesTable();
      $variablesTable = $this->loader->loadVariablesTable($parsedConfig);
      $envResults[$environment->getId()] = $this->findSubmitVariablesInVariablesTable($variablesTable);
    }

    $config = $this->loader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());
    foreach ($config->getTests() as $testId => $test) {
      foreach ($exercise->getRuntimeEnvironments() as $environment) {
        $env = $test->getEnvironment($environment->getId());
        if ($env === null) {
          throw new ExerciseConfigException("Environment '{$environment->getId()}' not found in test '$testId'");
        }

        foreach ($env->getPipelines() as $pipeline) {
          $variables = $this->findSubmitVariablesInVariablesTable($pipeline->getVariablesTable());
          $envResults[$environment->getId()] = array_merge($envResults[$environment->getId()], $variables);
        }
      }
    }

    $result = [];
    foreach ($envResults as $envId => $envVars) {
      $variables = [];
      foreach ($envVars as $varName => $varType) {
        $variables[] = [
          "name" => $varName,
          "type" => $varType
        ];
      }

      $result[] = [
        "runtimeEnvironmentId" => $envId,
        "variables" => $variables
      ];
    }

    return $result;
  }

}
