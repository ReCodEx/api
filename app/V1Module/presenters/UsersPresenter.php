<?php

namespace App\V1Module\Presenters;

use App\Model\Entity\Login;
use App\Model\Entity\Role;
use App\Model\Entity\User;
use App\Model\Repository\Instances;
use App\Model\Repository\Logins;
use App\Model\Repository\Roles;
use App\Model\Repository\Users;
use App\Security\AccessManager;

use App\Exceptions\BadRequestException;
use Nette\Http\IResponse;

use ZxcvbnPhp\Zxcvbn;

class UsersPresenter extends BasePresenter {

  /** @inject @var Logins */
  public $logins;

  /** @inject @var AccessManager */
  public $accessManager;

  /** @inject @var Roles */
  public $roles;

  /** @inject @var Instances */
  public $instances;

  /**
   * @GET
   * @UserIsAllowed(users="view-all")
   */
  public function actionDefault() {
    $users = $this->users->findAll();
    $this->sendSuccessResponse($users);
  }

  /**
   * @POST
   * @Param(type="post", name="email", validation="email")
   * @Param(type="post", name="firstName", validation="string:2..")
   * @Param(type="post", name="lastName", validation="string:2..")
   * @Param(type="post", name="password", validation="string:1..", msg="Password cannot be empty.")
   * @Param(type="post", name="instanceId", validation="string:1..")
   */
  public function actionCreateAccount() {
    $req = $this->getHttpRequest();

    // check if the email is free
    $email = $req->getPost("email");
    if ($this->users->getByEmail($email) !== NULL) {
      throw new BadRequestException("This email address is already taken.");
    }

    $roleId = $req->getPost("role", "student");
    $role = $this->roles->get($roleId);
    if (!$role) {
      throw new BadRequestException("Role '$roleId' does not exist.");
    }

    if ($role->hasLimitedRights() === FALSE
      && (!$this->user->isLoggedIn() || $this->user->isAllowed("role-$roleId", "assign"))) {
      throw new ForbiddenRequestException("You are not allowed to assign the new user role '$roleId'");
    }

    $instance = $this->instances->get($req->getPost("instanceId"));
    if (!$instance) {
      throw new BadRequestException("Such instance does not exist.");
    }

    $user = new User(
      $email,
      $req->getPost("firstName"),
      $req->getPost("lastName"),
      $req->getPost("degreesBeforeName", ""),
      $req->getPost("degreesAfterName", ""),
      $role,
      $instance
    );
    $login = Login::createLogin($user, $email, $req->getPost("password"));

    $this->users->persist($user);
    $this->logins->persist($login);

    // successful!
    $this->sendSuccessResponse([
      "user" => $user,
      "accessToken" => $this->accessManager->issueToken($user)
    ], IResponse::S201_CREATED);
  }

  /**
   * @POST
   * @Param(type="post", name="email")
   * @Param(type="post", name="password")
   */
  public function actionValidateRegistrationData() {
    $req = $this->getHttpRequest();
    $email = $req->getPost("email");
    $emailParts = explode("@", $email);
    $password = $req->getPost("password");

    $user = $this->users->getByEmail($email);
    $zxcvbn = new Zxcvbn;
    $passwordStrength = $zxcvbn->passwordStrength($password, [ $email, $emailParts[0] ]);

    $this->sendSuccessResponse([
      "usernameIsFree" => $user === NULL,
      "passwordScore" => $passwordStrength["score"]
    ]);
  }

  /**
   * @GET
   * @LoggedIn
   * @UserIsAllowed(users="view-all")
   */
  public function actionDetail(string $id) {
    $user = $this->users->findOrThrow($id);
    $this->sendSuccessResponse($user);
  }

  /**
   * @GET
   * @LoggedIn
   * @UserIsAllowed(users="view-groups")
   */
  public function actionGroups(string $id) {
    $user = $this->users->findOrThrow($id);
    $this->sendSuccessResponse([
      "supervisor" => $user->getGroupsAsSupervisor()->toArray(),
      "student" => $user->getGroupsAsStudent()->toArray(),
      "stats" => $user->getGroupsAsStudent()->map(
        function ($group) use ($user) {
          return [
            "id" => $group->id,
            "name" => $group->name,
            "stats" => $group->getStudentsStats($user)
          ];
        }
      )->toArray()
    ]);
  }

  /**
   * @GET
   * @LoggedIn
   * @UserIsAllowed(users="view-instances")
   */
  public function actionInstances(string $id) {
    $user = $this->users->findOrThrow($id);
    $this->sendSuccessResponse([
      $user->getInstance() // @todo change when the user can be member of multiple instances
    ]);
  }

  /**
   * @GET
   * @LoggedIn
   * @UserIsAllowed(users="view-exercises")
   */
  public function actionExercises(string $id) {
    $user = $this->users->findOrThrow($id);
    $this->sendSuccessResponse($user->getExercises()->toArray());
  }

}
