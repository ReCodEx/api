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
      $this->sendSuccessResponse("OK");
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
}
