<?php

namespace App\V1Module\Presenters;

use App\Model\Entity\Login;
use App\Model\Entity\User;
use App\Model\Repository\Instances;
use App\Model\Repository\Logins;
use App\Model\Repository\Roles;
use App\Security\AccessManager;
use App\Exceptions\WrongCredentialsException;
use App\Exceptions\BadRequestException;
use App\Exceptions\InvalidArgumentException;
use Nette\Http\IResponse;
use App\Security\AccessToken;

use ZxcvbnPhp\Zxcvbn;

class UsersPresenter extends BasePresenter {

  /**
   * @var Logins
   * @inject
   */
  public $logins;

  /**
   * @var AccessManager
   * @inject
   */
  public $accessManager;

  /**
   * @var Roles
   * @inject
   */
  public $roles;

  /**
   * @var Instances
   * @inject
   */
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
   * @POST
   * @LoggedIn
   * @Param(type="post", name="email", validation="email")
   * @Param(type="post", name="firstName", validation="string:2..")
   * @Param(type="post", name="lastName", validation="string:2..")
   * @Param(type="post", name="degreesBeforeName", validation="string:1..")
   * @Param(type="post", name="degreesAfterName", validation="string:1..")
   */
  public function actionUpdateProfile() {
    $req = $this->getHttpRequest();
    $email = $req->getPost("email");
    $firstName = $req->getPost("firstName");
    $lastName = $req->getPost("lastName");
    $degreesBeforeName = $req->getPost("degreesBeforeName");
    $degreesAfterName = $req->getPost("degreesAftername");

    $oldPassword = $req->getPost("oldPassword");
    $newPassword = $req->getPost("password");

    // fill user with all provided datas
    $login = $this->logins->findCurrent();
    $user = $this->users->findCurrentUserOrThrow();

    $user->setFirstName($firstName);
    $user->setLastName($lastName);
    $user->setEmail($email);
    $user->setDegreesBeforeName($degreesBeforeName);
    $user->setDegreesAfterName($degreesAfterName);

    // passwords need to be handled differently
    if ($login && $newPassword) {
      if ($oldPassword) {
        // old password was provided, just check it against the one from db
        if ($login->getPasswordHash() !== Login::hashPassword($oldPassword)) {
          throw new WrongCredentialsException("The old password is incorrect");
        }
        $login->setPasswordHash(Login::hashPassword($newPassword));
      } else if ($this->isInScope(AccessToken::SCOPE_CHANGE_PASSWORD)) {
        // user is in modify-password scope and can change password without providing old one
        $login->setPasswordHash(Login::hashPassword($newPassword));
      }

      // make password changes permanent
      $this->logins->persist($login);
      $this->logins->flush();
    }

    // make changes permanent
    $this->users->persist($user);
    $this->users->flush();

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
      "supervisor" => $user->getGroupsAsSupervisor()->getValues(),
      "student" => $user->getGroupsAsStudent()->getValues(),
      "stats" => $user->getGroupsAsStudent()->map(
        function ($group) use ($user) {
          return [
            "id" => $group->id,
            "name" => $group->name,
            "stats" => $group->getStudentsStats($user)
          ];
        }
      )->getValues()
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
    $this->sendSuccessResponse($user->getExercises()->getValues());
  }

}
