<?php

namespace App\V1Module\Presenters;

use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\MalformedJobConfigException;
use App\Helpers\JobConfig\Storage;
use App\Helpers\JobConfig\JobConfig;
use App\Helpers\JobConfig\Tasks\Task;

/**
 * Endpoints for job configuration manipulation and validation
 */
class JobConfigPresenter extends BasePresenter {

  /**
   * @var Storage @inject
   */
  public $jobConfigStorage;

  /**
   * Validate low-level job configuration in YAML and return list of error messages
   * @POST
   * @Param(type="post", name="jobConfig", description="Job configuration YAML", validation="string")
   */
  public function actionValidate() {
    $req = $this->getRequest();
    $config = $req->getPost("jobConfig");

    $error = [];
    try {
      $jobConfig = $this->jobConfigStorage->parseJobConfig($config);
      $diagramSrc = $this->createDiagramSource($jobConfig);
      $svgUrl = $this->createDiagramSvgUrl($diagramSrc);
      $this->sendSuccessResponse($svgUrl);
    } catch (MalformedJobConfigException $e) {
      $parserException = $e->getOriginalException();
      if ($parserException != NULL) {
        $error = [
          "message" => $parserException->getMessage(),
          "line" => $parserException->getParsedLine(),
          "snippet" => $parserException->getSnippet()
        ];
      }
    } catch (JobConfigLoadingException $e) {
      $error = [
        "message" => $e->getMessage()
      ];
    }

    $this->sendSuccessResponse($error);
  }

  private function createDiagramSource(JobConfig $config): string {
    $diagram = "blockdiag {";
    foreach ($config->getTasks() as $task) {
      foreach ($task->getDependencies() as $dep) {
        $diagram .= $dep . " -> " . $task->getId() . ";";
      }
    }
    $diagram .= "}";
    return $diagram;
  }

  // TODO:
  // TODO: Ugly undocumented exec call
  // TODO:
  private function createDiagramSvgUrl(string $source): string {
    //$src = base64_encode(gzcompress($source));
    //$urlBase = "http://interactive.blockdiag.com/image?compression=deflate&encoding=base64&src=";
    //return $urlBase . $src;
    $outputFile = tempnam(sys_get_temp_dir(), 'recodex_');
    shell_exec('echo "' . $source . '" | /usr/bin/blockdiag -T SVG -o "' . $outputFile . '" -');
    $diagram = file_get_contents($outputFile);
    unlink($outputFile);
    return $diagram;
  }
}
