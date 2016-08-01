<?php

namespace App\V1Module\Presenters;

use App\Model\Entity\User;
use App\Model\Repository\Users;

use App\Model\Entity\Login;
use App\Model\Repository\Logins;

use App\Model\Entity\Role;
use App\Model\Repository\Roles;

/**
 * @LoggedIn
 */
class UsersPresenter extends BasePresenter {

  /** @inject @var Logins */
  public $logins;

  /** @inject @var Roles */
  public $roles;

  /**
   * @GET
   */
  public function actionDefault() {
    $users = $this->users->findAll();
    $this->sendSuccessResponse($users);
  }

  /**
   * @POST
   * @RequiredField(type="post", name="email", validation="email")
   * @RequiredField(type="post", name="firstName", validation="string:2..")
   * @RequiredField(type="post", name="lastName", validation="string:2..")
   * @RequiredField(type="post", name="password", validation="string:8..")
   */
  public function actionCreateAccount() {
    $req = $this->getHttpRequest();

    $roleId = $req->get('role', 'student');
    $role = $this->roles->get($roleId);
    if (!$role) {
      throw new BadRequestException("Role '$roleId' does not exist.");
    }

    if ($role->hasLimitedRights() === FALSE
      && (!$this->user->isLoggedIn() || $this->user->isAllowed("role-$roleId", 'assign'))) {
      throw new ForbiddenRequestException("You are not allowed to assign the new user role '$roleId'");
    }

    $user = User::createUser(
      $req->get('email'),
      $req->get('firstName'),
      $req->get('lastName'),
      $req->get('degreesBeforeName', ''),
      $req->get('degreesAfterName', ''),
      $role
    );
    $login = Login::createLogin($user, $req->get('email'), $req->get('password'));

    $this->users->persist($user);
    $this->logins->persist($login);
  }

  /**
   * @GET
   */
  public function actionDetail(string $id) {
    $user = $this->findUserOrThrow($id);
    $this->sendSuccessResponse($user);
  }

  /**
   * @GET
   */
  public function actionGroups(string $id) {
    $user = $this->findUserOrThrow($id);
    $this->sendSuccessResponse([
      'supervisor' => $user->getGroupsAsSupervisor()->toArray(),
      'student' => $user->getGroupsAsStudent()->toArray()
    ]);
  }

  /**
   * @GET
   */
  public function actionExercises(string $id) {
    $user = $this->findUserOrThrow($id);
    $this->sendSuccessResponse($user->getUsersExercises()->toArray());
  }

}
