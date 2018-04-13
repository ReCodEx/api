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

    $router[] = self::createSecurityRoutes("$prefix/security");
    $router[] = self::createAuthRoutes("$prefix/login");
    $router[] = self::createBrokerReportsRoutes("$prefix/broker-reports");
    $router[] = self::createCommentsRoutes("$prefix/comments");
    $router[] = self::createExercisesRoutes("$prefix/exercises");
    $router[] = self::createAssignmentsRoutes("$prefix/exercise-assignments");
    $router[] = self::createGroupsRoutes("$prefix/groups");
    $router[] = self::createInstancesRoutes("$prefix/instances");
    $router[] = self::createReferenceSolutionsRoutes("$prefix/reference-solutions");
    $router[] = self::createAssignmentSolutionsRoutes("$prefix/assignment-solutions");
    $router[] = self::createSubmissionFailuresRoutes("$prefix/submission-failures");
    $router[] = self::createUploadedFilesRoutes("$prefix/uploaded-files");
    $router[] = self::createUsersRoutes("$prefix/users");
    $router[] = self::createEmailVerificationRoutes("$prefix/email-verification");
    $router[] = self::createForgottenPasswordRoutes("$prefix/forgotten-password");
    $router[] = self::createRuntimeEnvironmentsRoutes("$prefix/runtime-environments");
    $router[] = self::createHardwareGroupsRoutes("$prefix/hardware-groups");
    $router[] = self::createJobConfigRoutes("$prefix/job-config");
    $router[] = self::createPipelinesRoutes("$prefix/pipelines");
    $router[] = self::createSisRouter("$prefix/extensions/sis");

    return $router;
  }

  private static function createSecurityRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new PostRoute("$prefix/check", "Security:check");
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
    $router[] = new PostRoute("$prefix/issue-restricted-token", "Login:issueRestrictedToken");
    $router[] = new PostRoute("$prefix/takeover/<userId>", "Login:takeOver");
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
    $router[] = new DeleteRoute("$prefix/<threadId>/comment/<commentId>", "Comments:delete");
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
    $router[] = new PostRoute("$prefix/list", "Exercises:listByIds");
    $router[] = new GetRoute("$prefix/<id>", "Exercises:detail");
    $router[] = new DeleteRoute("$prefix/<id>", "Exercises:remove");
    $router[] = new PostRoute("$prefix/<id>", "Exercises:updateDetail");
    $router[] = new PostRoute("$prefix/<id>/validate", "Exercises:validate");
    $router[] = new PostRoute("$prefix/<id>/fork", "Exercises:forkFrom");
    $router[] = new GetRoute("$prefix/<id>/pipelines", "Exercises:getPipelines");
    $router[] = new PostRoute("$prefix/<id>/hardware-groups", "Exercises:hardwareGroups");
    $router[] = new PostRoute("$prefix/<id>/groups/<groupId>", "Exercises:attachGroup");
    $router[] = new DeleteRoute("$prefix/<id>/groups/<groupId>", "Exercises:detachGroup");

    $router[] = new GetRoute("$prefix/<id>/supplementary-files", "ExerciseFiles:getSupplementaryFiles");
    $router[] = new PostRoute("$prefix/<id>/supplementary-files", "ExerciseFiles:uploadSupplementaryFiles");
    $router[] = new DeleteRoute("$prefix/<id>/supplementary-files/<fileId>", "ExerciseFiles:deleteSupplementaryFile");
    $router[] = new GetRoute("$prefix/<id>/supplementary-files/download-archive", "ExerciseFiles:downloadSupplementaryFilesArchive");
    $router[] = new GetRoute("$prefix/<id>/attachment-files", "ExerciseFiles:getAttachmentFiles");
    $router[] = new PostRoute("$prefix/<id>/attachment-files", "ExerciseFiles:uploadAttachmentFiles");
    $router[] = new DeleteRoute("$prefix/<id>/attachment-files/<fileId>", "ExerciseFiles:deleteAttachmentFile");
    $router[] = new GetRoute("$prefix/<id>/attachment-files/download-archive", "ExerciseFiles:downloadAttachmentFilesArchive");

    $router[] = new GetRoute("$prefix/<id>/tests", "ExercisesConfig:getTests");
    $router[] = new PostRoute("$prefix/<id>/tests", "ExercisesConfig:setTests");
    $router[] = new GetRoute("$prefix/<id>/environment-configs", "ExercisesConfig:getEnvironmentConfigs");
    $router[] = new PostRoute("$prefix/<id>/environment-configs", "ExercisesConfig:updateEnvironmentConfigs");
    $router[] = new GetRoute("$prefix/<id>/config", "ExercisesConfig:getConfiguration");
    $router[] = new PostRoute("$prefix/<id>/config", "ExercisesConfig:setConfiguration");
    $router[] = new PostRoute("$prefix/<id>/config/variables", "ExercisesConfig:getVariablesForExerciseConfig");
    $router[] = new GetRoute("$prefix/<id>/environment/<runtimeEnvironmentId>/hwgroup/<hwGroupId>/limits", "ExercisesConfig:getHardwareGroupLimits");
    $router[] = new PostRoute("$prefix/<id>/environment/<runtimeEnvironmentId>/hwgroup/<hwGroupId>/limits", "ExercisesConfig:setHardwareGroupLimits");
    $router[] = new DeleteRoute("$prefix/<id>/environment/<runtimeEnvironmentId>/hwgroup/<hwGroupId>/limits", "ExercisesConfig:removeHardwareGroupLimits");
    $router[] = new GetRoute("$prefix/<id>/score-config", "ExercisesConfig:getScoreConfig");
    $router[] = new PostRoute("$prefix/<id>/score-config", "ExercisesConfig:setScoreConfig");

    return $router;
  }

  /**
   * Adds all Assignments endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createAssignmentsRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new PostRoute("$prefix", "Assignments:create");
    $router[] = new GetRoute("$prefix/<id>", "Assignments:detail");
    $router[] = new PostRoute("$prefix/<id>", "Assignments:updateDetail");
    $router[] = new DeleteRoute("$prefix/<id>", "Assignments:remove");
    $router[] = new GetRoute("$prefix/<id>/solutions", "Assignments:solutions");
    $router[] = new GetRoute("$prefix/<id>/best-solutions", "Assignments:bestSolutions");
    $router[] = new GetRoute("$prefix/<id>/download-best-solutions", "Assignments:downloadBestSolutionsArchive");
    $router[] = new GetRoute("$prefix/<id>/users/<userId>/solutions", "Assignments:userSolutions");
    $router[] = new GetRoute("$prefix/<id>/users/<userId>/best-solution", "Assignments:bestSolution");
    $router[] = new PostRoute("$prefix/<id>/validate", "Assignments:validate");
    $router[] = new PostRoute("$prefix/<id>/sync-exercise", "Assignments:syncWithExercise");

    $router[] = new GetRoute("$prefix/<id>/can-submit", "Submit:canSubmit");
    $router[] = new PostRoute("$prefix/<id>/submit", "Submit:submit");
    $router[] = new PostRoute("$prefix/<id>/resubmit-all", "Submit:resubmitAll");
    $router[] = new PostRoute("$prefix/<id>/pre-submit", "Submit:preSubmit");
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
    $router[] = new GetRoute("$prefix/all", "Groups:all");
    $router[] = new PostRoute("$prefix", "Groups:addGroup");
    $router[] = new PostRoute("$prefix/validate-add-group-data", "Groups:validateAddGroupData");
    $router[] = new GetRoute("$prefix/<id>", "Groups:detail");
    $router[] = new PostRoute("$prefix/<id>", "Groups:updateGroup");
    $router[] = new DeleteRoute("$prefix/<id>", "Groups:removeGroup");
    $router[] = new GetRoute("$prefix/<id>/subgroups", "Groups:subgroups");
    $router[] = new GetRoute("$prefix/<id>/members", "Groups:members");

    $router[] = new PostRoute("$prefix/<id>/organizational", "Groups:setOrganizational");
    $router[] = new PostRoute("$prefix/<id>/archived", "Groups:setArchived");

    $router[] = new GetRoute("$prefix/<id>/students", "Groups:students");
    $router[] = new GetRoute("$prefix/<id>/students/stats", "Groups:stats");
    $router[] = new GetRoute("$prefix/<id>/students/<userId>", "Groups:studentsStats");
    $router[] = new PostRoute("$prefix/<id>/students/<userId>", "Groups:addStudent");
    $router[] = new DeleteRoute("$prefix/<id>/students/<userId>", "Groups:removeStudent");

    $router[] = new GetRoute("$prefix/<id>/supervisors", "Groups:supervisors");
    $router[] = new PostRoute("$prefix/<id>/supervisors/<userId>", "Groups:addSupervisor");
    $router[] = new DeleteRoute("$prefix/<id>/supervisors/<userId>", "Groups:removeSupervisor");

    $router[] = new GetRoute("$prefix/<id>/admin", "Groups:admins");
    $router[] = new PostRoute("$prefix/<id>/admin", "Groups:addAdmin");
    $router[] = new DeleteRoute("$prefix/<id>/admin/<userId>", "Groups:removeAdmin");

    $router[] = new GetRoute("$prefix/<id>/assignments", "Groups:assignments");
    $router[] = new GetRoute("$prefix/<id>/exercises", "Groups:exercises");

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
    $router[] = new GetRoute("$prefix/exercise/<exerciseId>", "ReferenceExerciseSolutions:solutions");
    $router[] = new PostRoute("$prefix/exercise/<exerciseId>/pre-submit", "ReferenceExerciseSolutions:preSubmit");
    $router[] = new PostRoute("$prefix/exercise/<exerciseId>/submit", "ReferenceExerciseSolutions:submit");
    $router[] = new PostRoute("$prefix/exercise/<exerciseId>/resubmit-all", "ReferenceExerciseSolutions:resubmitAll");

    $router[] = new GetRoute("$prefix/evaluation/<evaluationId>", "ReferenceExerciseSolutions:evaluation");
    $router[] = new GetRoute("$prefix/evaluation/<evaluationId>/download-result", "ReferenceExerciseSolutions:downloadResultArchive");

    $router[] = new DeleteRoute("$prefix/<solutionId>", "ReferenceExerciseSolutions:deleteReferenceSolution");
    $router[] = new PostRoute("$prefix/<id>/resubmit", "ReferenceExerciseSolutions:resubmit");
    $router[] = new GetRoute("$prefix/<solutionId>/evaluations", "ReferenceExerciseSolutions:evaluations");
    $router[] = new GetRoute("$prefix/<solutionId>/download-solution", "ReferenceExerciseSolutions:downloadSolutionArchive");
    return $router;
  }

  /**
   * Adds all AssignmentSolution endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createAssignmentSolutionsRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new GetRoute("$prefix/evaluation/<id>", "AssignmentSolutions:evaluation");
    $router[] = new GetRoute("$prefix/evaluation/<id>/download-result", "AssignmentSolutions:downloadResultArchive");

    $router[] = new GetRoute("$prefix/<id>", "AssignmentSolutions:solution");
    $router[] = new DeleteRoute("$prefix/<id>", "AssignmentSolutions:deleteSolution");
    $router[] = new PostRoute("$prefix/<id>/bonus-points", "AssignmentSolutions:setBonusPoints");
    $router[] = new GetRoute("$prefix/<id>/evaluations", "AssignmentSolutions:evaluations");
    $router[] = new PostRoute("$prefix/<id>/set-accepted", "AssignmentSolutions:setAcceptedSubmission");
    $router[] = new DeleteRoute("$prefix/<id>/unset-accepted", "AssignmentSolutions:unsetAcceptedSubmission");
    $router[] = new PostRoute("$prefix/<id>/resubmit", "Submit:resubmit");
    $router[] = new GetRoute("$prefix/<id>/download-solution", "AssignmentSolutions:downloadSolutionArchive");
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
    $router[] = new GetRoute("$prefix/supplementary-file/<id>/download", "UploadedFiles:downloadSupplementaryFile");
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
    $router[] = new PostRoute("$prefix", "Registration:createAccount");
    $router[] = new PostRoute("$prefix/ext", "Registration:createAccountExt");
    $router[] = new PostRoute("$prefix/validate-registration-data", "Registration:validateRegistrationData");
    $router[] = new PostRoute("$prefix/list", "Users:listByIds");

    $router[] = new GetRoute("$prefix/<id>", "Users:detail");
    $router[] = new PostRoute("$prefix/<id>/invalidate-tokens", "Users:invalidateTokens");
    $router[] = new DeleteRoute("$prefix/<id>", "Users:delete");
    $router[] = new GetRoute("$prefix/<id>/groups", "Users:groups");
    $router[] = new GetRoute("$prefix/<id>/groups/all", "Users:allGroups");
    $router[] = new GetRoute("$prefix/<id>/instances", "Users:instances");
    $router[] = new GetRoute("$prefix/<id>/exercises", "Users:exercises");
    $router[] = new PostRoute("$prefix/<id>", "Users:updateProfile");
    $router[] = new PostRoute("$prefix/<id>/settings", "Users:updateSettings");
    $router[] = new PostRoute("$prefix/<id>/create-local", "Users:createLocalAccount");
    return $router;
  }

  /**
   * All endpoints for email addresses verification.
   * @param string $prefix
   * @return RouteList
   */
  private static function createEmailVerificationRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new PostRoute("$prefix/verify", "EmailVerification:emailVerification");
    $router[] = new PostRoute("$prefix/resend", "EmailVerification:resendVerificationEmail");
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

  /**
   * Adds all Pipelines endpoints to given router.
   * @param string $prefix Route prefix
   * @return RouteList All endpoint routes
   */
  private static function createPipelinesRoutes(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new GetRoute("$prefix/boxes", "Pipelines:getDefaultBoxes");
    $router[] = new GetRoute("$prefix", "Pipelines:getPipelines");
    $router[] = new PostRoute("$prefix", "Pipelines:createPipeline");
    $router[] = new PostRoute("$prefix/<id>/fork", "Pipelines:forkPipeline");
    $router[] = new GetRoute("$prefix/<id>", "Pipelines:getPipeline");
    $router[] = new PostRoute("$prefix/<id>", "Pipelines:updatePipeline");
    $router[] = new DeleteRoute("$prefix/<id>", "Pipelines:removePipeline");
    $router[] = new PostRoute("$prefix/<id>/validate", "Pipelines:validatePipeline");
    $router[] = new GetRoute("$prefix/<id>/supplementary-files", "Pipelines:getSupplementaryFiles");
    $router[] = new PostRoute("$prefix/<id>/supplementary-files", "Pipelines:uploadSupplementaryFiles");
    return $router;
  }

  private static function createSisRouter(string $prefix): RouteList {
    $router = new RouteList();
    $router[] = new GetRoute("$prefix/status/", "Sis:status");
    $router[] = new GetRoute("$prefix/terms/", "Sis:getTerms");
    $router[] = new PostRoute("$prefix/terms/", "Sis:registerTerm");
    $router[] = new PostRoute("$prefix/terms/<id>", "Sis:editTerm");
    $router[] = new DeleteRoute("$prefix/terms/<id>", "Sis:deleteTerm");
    $router[] = new GetRoute("$prefix/users/<userId>/subscribed-groups/<year>/<term>/as-student", "Sis:subscribedGroups");
    $router[] = new GetRoute("$prefix/users/<userId>/supervised-courses/<year>/<term>", "Sis:supervisedCourses");
    $router[] = new GetRoute("$prefix/remote-courses/<courseId>/possible-parents", "Sis:possibleParents");
    $router[] = new PostRoute("$prefix/remote-courses/<courseId>/create", "Sis:createGroup");
    $router[] = new PostRoute("$prefix/remote-courses/<courseId>/bind", "Sis:bindGroup");
    $router[] = new DeleteRoute("$prefix/remote-courses/<courseId>/bindings/<groupId>", "Sis:unbindGroup");
    return $router;
  }
}
