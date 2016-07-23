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
    $router = new RouteList('V1');

    $prefix = 'v1';
    $router[] = new Route($prefix, 'Default:default');

    self::createExercisesRoutes($router, "$prefix/exercises");
    self::createExerciseAssignmentsRoutes($router, "$prefix/exercise-assignments");
    self::createGroupsRoutes($router, "$prefix/groups");
    self::createSubmissionRoutes($router, "$prefix/submissions");
    self::createUploadedFilesRoutes($router, "$prefix/uploaded-files");
    self::createUsersRoutes($router, "$prefix/users");

    return $router;
  }

  private static function createExercisesRoutes($router, $prefix) {
    $router[] = new Route("$prefix", "Exercises:getAll");
    $router[] = new Route("$prefix/<id>", 'Exercises:detail');
  }

  private static function createExerciseAssignmentsRoutes($router, $prefix) {
    $router[] = new Route("$prefix", "ExerciseAssignments:getAll");
    $router[] = new Route("$prefix/<id>", 'ExerciseAssignments:detail');
    $router[] = new Route("$prefix/<id>/users/<userId>/submissions", 'ExerciseAssignments:submissions');
  }

  private static function createGroupsRoutes($router, $prefix) {
    $router[] = new Route("$prefix", "Groups:getAll");
    $router[] = new Route("$prefix/<id>", 'Groups:detail');
    $router[] = new Route("$prefix/<id>/members", 'Groups:members');
    $router[] = new Route("$prefix/<id>/assignments", 'Groups:assignments');
  }

  private static function createSubmissionRoutes($router, $prefix) {
    $router[] = new Route("$prefix", "Submissions:getAll");
    $router[] = new Route("$prefix/<id>", 'Submissions:detail');
  }

  private static function createUploadedFilesRoutes($router, $prefix) {
    $router[] = new Route("$prefix/upload", 'UploadedFiles:upload');
  }

  private static function createUsersRoutes($router, $prefix) {
    $router[] = new Route("$prefix", "Users:getAll");
    $router[] = new Route("$prefix/<id>", 'Users:detail');
    $router[] = new Route("$prefix/<id>/exercises", 'Users:exercises');
  }

}
