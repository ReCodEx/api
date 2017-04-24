<?php

namespace App\V1Module;

use Nette;
use Nette\Application\IRouter;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;
use App\V1Module\Router\GetRoute;
use App\V1Module\Router\PostRoute;
use App\V1Module\Router\DeleteRoute;
use App\V1Module\Router\PreflightRoute;


/**
 * Router factory for V1 module.
 */
class RouterFactory {

  use Nette\StaticClass;

  /**
   * Create router with all routes for V1 module.
   * @return IRouter
   */
  public static function createRouter() {
    $router = new RouteList("V1");

    $prefix = "v1";
    $router[] = new Route($prefix, "Default:default");
    $router[] = new PreflightRoute($prefix, "Default:preflight");

    $router[] = self::createAuthRoutes("$prefix/login");
    $router[] = self::createBrokerReportsRoutes("$prefix/broker-reports");
    $router[] = self::createCommentsRoutes("$prefix/comments");
    $router[] = self::createExercisesRoutes("$prefix/exercises");
    $router[] = self::createAssignmentsRoutes("$prefix/exercise-assignments");
    $router[] = self::createGroupsRoutes("$prefix/groups");
    $router[] = self::createInstancesRoutes("$prefix/instances");
    $router[] = self::createReferenceSolutionsRoutes("$prefix/reference-solutions");
    $router[] = self::createSubmissionRoutes("$prefix/submissions");
    $router[] = self::createSubmissionFailuresRoutes("$prefix/submission-failures");
    $router[] = self::createUploadedFilesRoutes("$prefix/uploaded-files");
    $router[] = self::createUsersRoutes("$prefix/users");
    $router[] = self::createForgottenPasswordRoutes("$prefix/forgotten-password");
    $router[] = self::createRuntimeEnvironmentsRoutes("$prefix/runtime-environments");
    $router[] = self::createHardwareGroupsRoutes("$prefix/hardware-groups");
    $router[] = self::createJobConfigRoutes("$prefix/job-config");

    return $router;
  }

  /**
   * Adds all Authentication endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createAuthRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new PostRoute("$prefix", "Login:default");
    $router[] = new PostRoute("$prefix/refresh", "Login:refresh");
    $router[] = new PostRoute("$prefix/<serviceId>[/<type>]", "Login:external");
    return $router;
  }

  /**
   * Adds all BrokerReports endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createBrokerReportsRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new PostRoute("$prefix/error", "BrokerReports:error");
    $router[] = new PostRoute("$prefix/job-status/<jobId>", "BrokerReports:jobStatus");
    return $router;
  }

  /**
   * Adds all Comments endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createCommentsRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new GetRoute("$prefix/<id>", "Comments:default");
    $router[] = new PostRoute("$prefix/<id>", "Comments:addComment");
    $router[] = new PostRoute("$prefix/<threadId>/comment/<commentId>/toggle", "Comments:togglePrivate");
    return $router;
  }

  /**
   * Adds all Exercises endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createExercisesRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new GetRoute("$prefix", "Exercises:");
    $router[] = new PostRoute("$prefix", "Exercises:create");
    $router[] = new GetRoute("$prefix/<id>", "Exercises:detail");
    $router[] = new DeleteRoute("$prefix/<id>", "Exercises:remove");
    $router[] = new PostRoute("$prefix/<id>", "Exercises:updateDetail");
    $router[] = new PostRoute("$prefix/<id>/runtime-configs", "Exercises:updateRuntimeConfigs");
    $router[] = new PostRoute("$prefix/<id>/validate", "Exercises:validate");
    $router[] = new PostRoute("$prefix/<id>/fork", "Exercises:forkFrom");
    $router[] = new GetRoute("$prefix/<id>/limits", "Exercises:getLimits");
    $router[] = new PostRoute("$prefix/<id>/limits", "Exercises:setLimits");
    $router[] = new GetRoute("$prefix/<id>/supplementary-files", "Exercises:getSupplementaryFiles");
    $router[] = new PostRoute("$prefix/<id>/supplementary-files", "Exercises:uploadSupplementaryFiles");
    $router[] = new GetRoute("$prefix/<id>/additional-files", "Exercises:getAdditionalFiles");
    $router[] = new PostRoute("$prefix/<id>/additional-files", "Exercises:uploadAdditionalFiles");

    return $router;
  }

  /**
   * Adds all Assignments endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createAssignmentsRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new GetRoute("$prefix", "Assignments:");
    $router[] = new PostRoute("$prefix", "Assignments:create");
    $router[] = new GetRoute("$prefix/<id>", "Assignments:detail");
    $router[] = new PostRoute("$prefix/<id>", "Assignments:updateDetail");
    $router[] = new DeleteRoute("$prefix/<id>", "Assignments:remove");
    $router[] = new GetRoute("$prefix/<id>/can-submit", "Assignments:canSubmit");
    $router[] = new GetRoute("$prefix/<id>/users/<userId>/submissions", "Assignments:submissions");
    $router[] = new GetRoute("$prefix/<id>/users/<userId>/best-submission", "Assignments:bestSubmission");
    $router[] = new PostRoute("$prefix/<id>/submit", "Assignments:submit");
    $router[] = new PostRoute("$prefix/<id>/validate", "Assignments:validate");
    return $router;
  }

  /**
   * Adds all Groups endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createGroupsRoutes(string $prefix): RouteList {
    $router = new RouteList();

    $router[] = new GetRoute("$prefix", "Groups:");
    $router[] = new PostRoute("$prefix", "Groups:addGroup");
    $router[] = new PostRoute("$prefix/validate-add-group-data", "Groups:validateAddGroupData");
    $router[] = new GetRoute("$prefix/<id>", "Groups:detail");
    $router[] = new PostRoute("$prefix/<id>", "Groups:updateGroup");
    $router[] = new DeleteRoute("$prefix/<id>", "Groups:removeGroup");
    $router[] = new GetRoute("$prefix/<id>/subgroups", "Groups:subgroups");
    $router[] = new GetRoute("$prefix/<id>/members", "Groups:members");

    $router[] = new GetRoute("$prefix/<id>/students", "Groups:students");
    $router[] = new GetRoute("$prefix/<id>/students/stats", "Groups:stats");
    $router[] = new GetRoute("$prefix/<id>/students/<userId>", "Groups:studentsStats");
    $router[] = new PostRoute("$prefix/<id>/students/<userId>", "Groups:addStudent");
    $router[] = new DeleteRoute("$prefix/<id>/students/<userId>", "Groups:removeStudent");

    $router[] = new GetRoute("$prefix/<id>/supervisors", "Groups:supervisors");
    $router[] = new PostRoute("$prefix/<id>/supervisors/<userId>", "Groups:addSupervisor");
    $router[] = new DeleteRoute("$prefix/<id>/supervisors/<userId>", "Groups:removeSupervisor");

    $router[] = new GetRoute("$prefix/<id>/admin", "Groups:admin");
    $router[] = new PostRoute("$prefix/<id>/admin", "Groups:makeAdmin");

    $router[] = new GetRoute("$prefix/<id>/assignments", "Groups:assignments");

    return $router;
  }

  /**
   * Adds all Instances endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createInstancesRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new GetRoute("$prefix", "Instances:");
    $router[] = new PostRoute("$prefix", "Instances:createInstance");
    $router[] = new GetRoute("$prefix/<id>", "Instances:detail");
    $router[] = new PostRoute("$prefix/<id>", "Instances:updateInstance");
    $router[] = new DeleteRoute("$prefix/<id>", "Instances:deleteInstance");
    $router[] = new GetRoute("$prefix/<id>/groups", "Instances:groups");
    $router[] = new GetRoute("$prefix/<id>/users", "Instances:users");
    $router[] = new GetRoute("$prefix/<id>/licences", "Instances:licences");
    $router[] = new PostRoute("$prefix/<id>/licences", "Instances:createLicence");
    $router[] = new PostRoute("$prefix/licences/<licenceId>", "Instances:updateLicence");
    $router[] = new DeleteRoute("$prefix/licences/<licenceId>", "Instances:deleteLicence");
    return $router;
  }

  /**
   * Adds all ReferenceSolutions endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createReferenceSolutionsRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new GetRoute("$prefix/exercise/<exerciseId>", "ReferenceExerciseSolutions:exercise");
    $router[] = new PostRoute("$prefix/exercise/<exerciseId>", "ReferenceExerciseSolutions:createReferenceSolution");
    $router[] = new PostRoute("$prefix/exercise/<exerciseId>/evaluate", "ReferenceExerciseSolutions:evaluateForExercise");
    $router[] = new PostRoute("$prefix/<id>/evaluate", "ReferenceExerciseSolutions:evaluate");
    $router[] = new GetRoute("$prefix/evaluation/<evaluationId>/download-result", "ReferenceExerciseSolutions:downloadResultArchive");
    return $router;
  }

  /**
   * Adds all Submission endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createSubmissionRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new GetRoute("$prefix", "Submissions:");
    $router[] = new GetRoute("$prefix/<id>", "Submissions:evaluation");
    $router[] = new PostRoute("$prefix/<id>", "Submissions:setBonusPoints");
    $router[] = new GetRoute("$prefix/<id>/set-accepted", "Submissions:setAcceptedSubmission");
    $router[] = new GetRoute("$prefix/<id>/download-result", "Submissions:downloadResultArchive");
    return $router;
  }

  /**
   * Adds all Submission failures endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createSubmissionFailuresRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new GetRoute("$prefix", "SubmissionFailures:");
    $router[] = new GetRoute("$prefix/unresolved", "SubmissionFailures:unresolved");
    $router[] = new GetRoute("$prefix/submission/<id>", "SubmissionFailures:listBySubmission");
    $router[] = new GetRoute("$prefix/<id>", "SubmissionFailures:detail");
    $router[] = new PostRoute("$prefix/<id>/resolve", "SubmissionFailures:resolve");
    return $router;
  }

  /**
   * Adds all UploadedFiles endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createUploadedFilesRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new PostRoute("$prefix", "UploadedFiles:upload");
    $router[] = new GetRoute("$prefix/<id>", "UploadedFiles:detail");
    $router[] = new GetRoute("$prefix/<id>/download", "UploadedFiles:download");
    $router[] = new GetRoute("$prefix/<id>/content", "UploadedFiles:content");
    return $router;
  }

  /**
   * Adds all Users endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createUsersRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new GetRoute("$prefix", "Users:");
    $router[] = new PostRoute("$prefix", "Users:createAccount");
    $router[] = new PostRoute("$prefix/ext", "Users:createAccountExt");
    $router[] = new PostRoute("$prefix/validate-registration-data", "Users:validateRegistrationData");
    $router[] = new GetRoute("$prefix/<id>", "Users:detail");
    $router[] = new GetRoute("$prefix/<id>/groups", "Users:groups");
    $router[] = new GetRoute("$prefix/<id>/instances", "Users:instances");
    $router[] = new GetRoute("$prefix/<id>/exercises", "Users:exercises");
    $router[] = new PostRoute("$prefix/<id>", "Users:updateProfile");
    $router[] = new PostRoute("$prefix/<id>/settings", "Users:updateSettings");
    return $router;
  }

  /**
   * Adds all ForgottenPassword endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createForgottenPasswordRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new PostRoute("$prefix", "ForgottenPassword:");
    $router[] = new PostRoute("$prefix/change", "ForgottenPassword:change");
    $router[] = new PostRoute("$prefix/validate-password-strength", "ForgottenPassword:validatePasswordStrength");
    return $router;
  }

  /**
   * Adds all RuntimeEnvironment endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createRuntimeEnvironmentsRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new GetRoute("$prefix", "RuntimeEnvironments:");
    return $router;
  }

  /**
   * Adds all HardwareGroups endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createHardwareGroupsRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new GetRoute("$prefix", "HardwareGroups:");
    return $router;
  }

  /**
   * Adds all JobConfigPresenter endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createJobConfigRoutes(string $prefix) {
    $router = new RouteList();
    $router[] = new PostRoute("$prefix/validate", "JobConfig:validate");
    return $router;
  }

}
