<?php

namespace App\V1Module\Presenters;

use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\MalformedJobConfigException;
use App\Helpers\JobConfig\Storage;

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

    $errors = [];
    try {
      $this->jobConfigStorage->parseJobConfig($config);
    } catch (MalformedJobConfigException $e) {
      $parserException = $e->getOriginalException();
      while ($parserException != NULL) {
        $errors[] = [
          "message" => $parserException->getMessage(),
          "line" => $parserException->getParsedLine(),
          "snippet" => $parserException->getSnippet()
        ];
        $parserException = $parserException->getPrevious();
      }
    } catch (JobConfigLoadingException $e) {
      $errors[] = [
        "message" => $e->getMessage()
      ];
    }

    $this->sendSuccessResponse($errors);
  }
}
