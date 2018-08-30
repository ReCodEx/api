<?php

namespace App\Helpers\ExerciseConfig;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\NotFoundException;
use App\Helpers\Evaluation\IExercise;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
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
   * @var PipelinesCache
   */
  private $pipelinesCache;

  /**
   * Constructor
   * @param Loader $loader
   * @param PipelinesCache $pipelinesCache
   */
  public function __construct(Loader $loader, PipelinesCache $pipelinesCache) {
    $this->loader = $loader;
    $this->pipelinesCache = $pipelinesCache;
  }


  /**
   * Join pipelines and find variables which needs to be defined by either environment config or exercise config.
   * @param string[] $pipelinesIds identifications of pipelines
   * @param array $inputs same length array as given pipelines, sub-arrays contain ports indexed by variable name
   * @param array $references same length array as given pipelines, sub-arrays contain variables indexed by variable name
   * @throws ExerciseConfigException
   */
  private function joinPipelinesAndGetInputVariables(array $pipelinesIds, array& $inputs, array& $references) {
    $lastOutputs = [];
    foreach ($pipelinesIds as $pipelineId) {
      try {
        $pipeline = $this->pipelinesCache->getPipelineConfig($pipelineId);
      } catch (NotFoundException $e) {
        throw new ExerciseConfigException("Pipeline '$pipelineId' not found");
      }

      // load pipeline input variables
      $localInputs = [];
      foreach ($pipeline->getDataInBoxes() as $dataInBox) {
        foreach ($dataInBox->getOutputPorts() as $outputPort) {
          $localInputs[$outputPort->getVariable()] = $outputPort;
        }
      }

      // find reference variables and add them to inputs
      $localReferences = [];
      foreach ($pipeline->getVariablesTable()->getAll() as $variable) {
        if ($variable->isReference()) {
          $localReferences[$variable->getName()] = $variable;
        }
      }

      // load pipeline output variables
      $localOutputs = [];
      foreach ($pipeline->getDataOutBoxes() as $dataOutBox) {
        foreach ($dataOutBox->getInputPorts() as $inputPort) {
          $localOutputs[] = $inputPort->getVariable();
        }
      }

      // remove variables which join pipelines
      foreach ($lastOutputs as $variableName) {
        if (!array_key_exists($variableName, $localInputs)) {
          // output variable cannot be found in local input variables, this
          // means that output variable is not joining with any input variable,
          // at least for now
          continue;
        }

        unset($localInputs[$variableName]);
      }

      // apply local variables to output ones
      $inputs[] = $localInputs;
      $references[] = $localReferences;
      $lastOutputs = $localOutputs;
    }
  }

  /**
   * Get variables which exercise configuration should include for given
   * pipelines. During computations pipelines are merged and variables checked
   * against environment configuration. Only inputs are returned as result.
   * @param string[] $pipelinesIds identifications of pipelines
   * @param VariablesTable $environmentVariables
   * @return array
   * @throws ExerciseConfigException
   */
  public function getVariablesForExercise(array $pipelinesIds, VariablesTable $environmentVariables): array {

    // process input variables from pipelines
    $inputs = []; // pairs of pipeline identifier and port indexed by variable name
    $references = []; // pairs of pipeline identifier and variable indexed by variable name
    $this->joinPipelinesAndGetInputVariables($pipelinesIds, $inputs, $references);

    // go through inputs and assign them to result
    $result = [];
    for ($i = 0; $i < count($pipelinesIds); $i++) {
      $pipelineId = $pipelinesIds[$i];
      $inputPorts = $inputs[$i];
      $referenceVariables = $references[$i];

      // declare temporary result holder for this loop sake
      $pipelineVariables = [];

      // go through input ports
      foreach ($inputPorts as $variableName => $inputPort) {
        if ($environmentVariables->get($variableName)) {
          // variable is defined in environment variables
          continue;
        }

        if ($inputPort->isFile() && $inputPort->isArray()) {
          // port is file and also array, in exercise config if there should be
          // defined file as variable it is expected to be remote file, so the
          // remote file type is offered back to web-app
          $variable = (new Variable(VariableTypes::$REMOTE_FILE_ARRAY_TYPE))->setName($variableName);
        } else if ($inputPort->isFile() && !$inputPort->isArray()) {
          // port is file and not an array, in exercise config if there should be
          // defined file as variable it is expected to be remote file, so the
          // remote file type is offered back to web-app
          $variable = (new Variable(VariableTypes::$REMOTE_FILE_TYPE))->setName($variableName);
        } else {
          $variable = (new Variable($inputPort->getType()))->setName($variableName);
        }
        $pipelineVariables[] = $variable;
      }

      // go through reference variables
      foreach ($referenceVariables as $referenceVariable) {
        $variableName = $referenceVariable->getReference();
        if ($environmentVariables->get($variableName)) {
          // variable is defined in environment variables
          continue;
        }

        $variable = (new Variable($referenceVariable->getType()))->setName($variableName);
        $pipelineVariables[] = $variable;
      }

      // generate proper result structure
      $result[] = [
        "id" => $pipelineId,
        "variables" => $pipelineVariables
      ];
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
        $pipelinesIds = [];
        foreach ($env->getPipelines() as $pipeline) {
          $pipelines[] = $pipeline;
          $pipelinesIds[] = $pipeline->getId();
        }

        // join pipelines and return inputs from all of them
        $inputs = []; // pairs of pipeline identifier and port indexed by variable name
        $references = []; // pairs of pipeline identifier and variable indexed by variable name
        $this->joinPipelinesAndGetInputVariables($pipelinesIds, $inputs, $references);

        for ($i = 0; $i < count($pipelines); $i++) {
          $pipeline = $pipelines[$i];
          $inputPorts = $inputs[$i];

          // go through all inputs, references are no use to us
          foreach ($inputPorts as $varName => $inputPort) {
            if (!$inputPort->isFile()) {
              continue;
            }

            // now we have only file input ports... use them... for the glory of ReCodEx

            // try to find variable in environment config variable table
            $variable = $envConfig->get($varName);
            if ($variable === null) {
              // if variable was not found in environment config, peek into exercise config
              $variable = $pipeline->getVariablesTable()->get($varName);
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
