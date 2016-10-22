<?php

namespace App\V1Module;

use Nette;
use Nette\Application\IRouter;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;
use App\V1Module\Router\GetRoute;
use App\V1Module\Router\PostRoute;
use App\V1Module\Router\PutRoute;
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

    self::createAuthRoutes($router, $prefix);
    self::createCommentsRoutes($router, "$prefix/comments");
    self::createBrokerReportsRoutes($router, "$prefix/broker-reports");
    self::createExercisesRoutes($router, "$prefix/exercises");
    self::createAssignmentsRoutes($router, "$prefix/exercise-assignments");
    self::createGroupsRoutes($router, "$prefix/groups");
    self::createInstancesRoutes($router, "$prefix/instances");
    self::createReferenceSolutionsRoutes($router, "$prefix/reference-solutions");
    self::createSubmissionRoutes($router, "$prefix/submissions");
    self::createUploadedFilesRoutes($router, "$prefix/uploaded-files");
    self::createUsersRoutes($router, "$prefix/users");
    self::createForgottenPasswordRoutes($router, "$prefix/forgotten-password");

    return $router;
  }

  /**
   * Adds all Authentication endpoints to given router.
   * @param type $router
   * @param type $prefix Route prefix
   */
  private static function createAuthRoutes($router, $prefix) {
    $router[] = new PostRoute("$prefix/login", "Login:default");
    $router[] = new PostRoute("$prefix/login/refresh", "Login:refresh");
    $router[] = new PostRoute("$prefix/login/<serviceId>", "Login:external");
  }

  /**
   * Adds all BrokerReports endpoints to given router.
   * @param type $router
   * @param type $prefix Route prefix
   */
  private static function createBrokerReportsRoutes($router, $prefix) {
    $router[] = new PostRoute("$prefix/error", "BrokerReports:error");
    $router[] = new PostRoute("$prefix/job-status/<jobId>", "BrokerReports:jobStatus");
  }

  /**
   * Adds all Comments endpoints to given router.
   * @param type $router
   * @param type $prefix Route prefix
   */
  private static function createCommentsRoutes($router, $prefix) {
    $router[] = new GetRoute("$prefix/<id>", "Comments:default");
    $router[] = new PostRoute("$prefix/<id>", "Comments:addComment");
    $router[] = new PostRoute("$prefix/<threadId>/comment/<commentId>/toggle", "Comments:togglePrivate");
  }

  /**
   * Adds all Exercises endpoints to given router.
   * @param type $router
   * @param type $prefix Route prefix
   */
  private static function createExercisesRoutes($router, $prefix) {
    $router[] = new GetRoute("$prefix", "Exercises:");
    $router[] = new GetRoute("$prefix/<id>", "Exercises:detail");
  }

  /**
   * Adds all Assignments endpoints to given router.
   * @param type $router
   * @param type $prefix Route prefix
   */
  private static function createAssignmentsRoutes($router, $prefix) {
    $router[] = new GetRoute("$prefix", "Assignments:");
    $router[] = new PostRoute("$prefix", "Assignments:create");
    $router[] = new GetRoute("$prefix/<id>", "Assignments:detail");
    $router[] = new PostRoute("$prefix/<id>", "Assignments:updateDetail");
    $router[] = new DeleteRoute("$prefix/<id>", "Assignments:remove");
    $router[] = new GetRoute("$prefix/<id>/can-submit", "Assignments:canSubmit");
    $router[] = new GetRoute("$prefix/<id>/users/<userId>/submissions", "Assignments:submissions");
    $router[] = new PostRoute("$prefix/<id>/submit", "Assignments:submit");
    $router[] = new GetRoute("$prefix/<id>/limits/<hardwareGroup>", "Assignments:getLimits");
    $router[] = new PostRoute("$prefix/<id>/limits/<hardwareGroup>", "Assignments:setLimits");
  }

  /**
   * Adds all Groups endpoints to given router.
   * @param type $router
   * @param type $prefix Route prefix
   */
  private static function createGroupsRoutes($router, $prefix) {
    $router[] = new GetRoute("$prefix", "Groups:");
    $router[] = new PostRoute("$prefix", "Groups:addGroup");
    $router[] = new PostRoute("$prefix/validate-add-group-data", "Groups:validateAddGroupData");
    $router[] = new GetRoute("$prefix/<id>", "Groups:detail");
    $router[] = new DeleteRoute("$prefix/<id>", "Groups:removeGroup");
    $router[] = new GetRoute("$prefix/<id>/subgroups", "Groups:subgroups");
    $router[] = new GetRoute("$prefix/<id>/members", "Groups:members");

    $router[] = new GetRoute("$prefix/<id>/students", "Groups:students");
    $router[] = new GetRoute("$prefix/<id>/students/stats", "Groups:stats");
    $router[] = new GetRoute("$prefix/<id>/students/<userId>", "Groups:studentsStats");
    $router[] = new GetRoute("$prefix/<id>/students/<userId>/best-results", "Groups:studentsBestResults");
    $router[] = new PostRoute("$prefix/<id>/students/<userId>", "Groups:addStudent");
    $router[] = new DeleteRoute("$prefix/<id>/students/<userId>", "Groups:removeStudent");

    $router[] = new GetRoute("$prefix/<id>/supervisors", "Groups:supervisors");
    $router[] = new PostRoute("$prefix/<id>/supervisors/<userId>", "Groups:addSupervisor");
    $router[] = new DeleteRoute("$prefix/<id>/supervisors/<userId>", "Groups:removeSupervisor");

    $router[] = new GetRoute("$prefix/<id>/admin", "Groups:admin");
    $router[] = new PostRoute("$prefix/<id>/admin", "Groups:makeAdmin");

    $router[] = new GetRoute("$prefix/<id>/assignments", "Groups:assignments");
  }

  /**
   * Adds all Instances endpoints to given router.
   * @param type $router
   * @param type $prefix Route prefix
   */
  private static function createInstancesRoutes($router, $prefix) {
    $router[] = new GetRoute("$prefix", "Instances:");
    $router[] = new PostRoute("$prefix", "Instances:createInstance");
    $router[] = new GetRoute("$prefix/<id>", "Instances:detail");
    $router[] = new PutRoute("$prefix/<id>", "Instances:updateInstance");
    $router[] = new DeleteRoute("$prefix/<id>", "Instances:deleteInstance");
    $router[] = new GetRoute("$prefix/<id>/groups", "Instances:groups");
    $router[] = new GetRoute("$prefix/<id>/users", "Instances:users");
    $router[] = new GetRoute("$prefix/<id>/licences", "Instances:licences");
    $router[] = new PostRoute("$prefix/<id>/licences", "Instances:createLicence");
    $router[] = new PutRoute("$prefix/<id>/licences/<licenceId>", "Instances:updateLicence");
    $router[] = new DeleteRoute("$prefix/<id>/licences/<licenceId>", "Instances:deleteLicence");
  }

  /**
   * Adds all ReferenceSolutions endpoints to given router.
   * @param type $router
   * @param type $prefix Route prefix
   */
  private static function createReferenceSolutionsRoutes($router, $prefix) {
    $router[] = new GetRoute("$prefix/<id>", "ReferenceExerciseSolutions:exercise");
    $router[] = new PostRoute("$prefix/<exerciseId>/evaluate/<id>", "ReferenceExerciseSolutions:evaluate");
  }

  /**
   * Adds all Submission endpoints to given router.
   * @param type $router
   * @param type $prefix Route prefix
   */
  private static function createSubmissionRoutes($router, $prefix) {
    $router[] = new GetRoute("$prefix", "Submissions:");
    $router[] = new GetRoute("$prefix/<id>", "Submissions:evaluation");
  }

  /**
   * Adds all UploadedFiles endpoints to given router.
   * @param type $router
   * @param type $prefix Route prefix
   */
  private static function createUploadedFilesRoutes($router, $prefix) {
    $router[] = new PostRoute("$prefix", "UploadedFiles:upload");
    $router[] = new GetRoute("$prefix/<id>", "UploadedFiles:detail");
    $router[] = new GetRoute("$prefix/<id>/download", "UploadedFiles:download");
    $router[] = new GetRoute("$prefix/<id>/content", "UploadedFiles:content");
  }

  /**
   * Adds all Users endpoints to given router.
   * @param type $router
   * @param type $prefix Route prefix
   */
  private static function createUsersRoutes($router, $prefix) {
    $router[] = new GetRoute("$prefix", "Users:");
    $router[] = new PostRoute("$prefix", "Users:createAccount");
    $router[] = new PostRoute("$prefix/validate-registration-data", "Users:validateRegistrationData");
    $router[] = new GetRoute("$prefix/<id>", "Users:detail");
    $router[] = new GetRoute("$prefix/<id>/groups", "Users:groups");
    $router[] = new GetRoute("$prefix/<id>/instances", "Users:instances");
    $router[] = new GetRoute("$prefix/<id>/exercises", "Users:exercises");
    $router[] = new PostRoute("$prefix/detail", "Users:updateProfile"); // TODO: maybe a bit different route
  }

  private static function createForgottenPasswordRoutes($router, $prefix) {
    $router[] = new PostRoute("$prefix", "ForgottenPassword:");
    $router[] = new PostRoute("$prefix/change", "ForgottenPassword:change");
    $router[] = new PostRoute("$prefix/validate-password-strength", "ForgottenPassword:validatePasswordStrength");
  }

}
