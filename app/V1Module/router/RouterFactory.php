<?php

namespace App\V1Module;

use Nette;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;

class RouterFactory {

  use Nette\StaticClass;

  /**
    * @return Nette\Application\IRouter
    */
  public static function createRouter() {
    $router = new RouteList("V1");

    $prefix = "v1";
    $router[] = new Route($prefix, "Default:default");
    $router[] = new Route("$prefix/login", "Login:default");

    self::createCommentsRoutes($router, "$prefix/comments");
    self::createExercisesRoutes($router, "$prefix/exercises");
    self::createExerciseAssignmentsRoutes($router, "$prefix/exercise-assignments");
    self::createGroupsRoutes($router, "$prefix/groups");
    self::createInstancesRoutes($router, "$prefix/instances");
    self::createSubmissionRoutes($router, "$prefix/submissions");
    self::createUploadedFilesRoutes($router, "$prefix/uploaded-files");
    self::createUsersRoutes($router, "$prefix/users");

    return $router;
  }

  private static function createCommentsRoutes($router, $prefix) {
    $router[] = new Route("$prefix/<id>", "Comments:default");
    $router[] = new Route("$prefix/<id>/add", "Comments:addComment");
    $router[] = new Route("$prefix/<id>/toggle", "Comments:togglePrivate");
  }

  private static function createExercisesRoutes($router, $prefix) {
    $router[] = new Route("$prefix", "Exercises:");
    $router[] = new Route("$prefix/<id>", "Exercises:detail");
  }

  private static function createExerciseAssignmentsRoutes($router, $prefix) {
    $router[] = new Route("$prefix", "ExerciseAssignments:");
    $router[] = new Route("$prefix/<id>", "ExerciseAssignments:detail");
    $router[] = new Route("$prefix/<id>/users/<userId>/submissions", "ExerciseAssignments:submissions");
    $router[] = new Route("$prefix/<id>/submit", "ExerciseAssignments:submit");
  }

  private static function createGroupsRoutes($router, $prefix) {
    $router[] = new Route("$prefix", "Groups:");
    $router[] = new Route("$prefix/<id>", "Groups:detail");
    $router[] = new Route("$prefix/<id>/members", "Groups:members");
    $router[] = new Route("$prefix/<id>/students", "Groups:students");
    $router[] = new Route("$prefix/<id>/supervisors", "Groups:supervisors");
    $router[] = new Route("$prefix/<id>/assignments", "Groups:assignments");
  }

  private static function createInstancesRoutes($router, $prefix) {
    $router[] = new Route("$prefix", "Instances:");
    $router[] = new Route("$prefix/<id>", "Instances:detail");
    $router[] = new Route("$prefix/<id>/groups", "Instances:groups");
  }

  private static function createSubmissionRoutes($router, $prefix) {
    $router[] = new Route("$prefix", "Submissions:");
    $router[] = new Route("$prefix/<id>", "Submissions:evaluation");
  }

  private static function createUploadedFilesRoutes($router, $prefix) {
    $router[] = new Route("$prefix/upload", "UploadedFiles:upload");
    $router[] = new Route("$prefix/<id>", "UploadedFiles:detail");
    $router[] = new Route("$prefix/<id>/content", "UploadedFiles:content");
  }

  private static function createUsersRoutes($router, $prefix) {
    $router[] = new Route("$prefix", "Users:");
    $router[] = new Route("$prefix/create-account", "Users:createAccount");
    $router[] = new Route("$prefix/<id>", "Users:detail");
    $router[] = new Route("$prefix/<id>/groups", "Users:groups");
    $router[] = new Route("$prefix/<id>/exercises", "Users:exercises");
  }

}
