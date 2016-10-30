<?php

namespace App\V1Module\Presenters;

use App\Model\Entity\Login;
use App\Model\Entity\User;
use App\Model\Entity\ExternalLogin;
use App\Model\Repository\Instances;
use App\Model\Repository\Logins;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Roles;
use App\Model\Entity\Role;
use App\Security\AccessManager;
use App\Exceptions\WrongCredentialsException;
use App\Exceptions\BadRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Helpers\ExternalLogin\AuthService;
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
   * @var ExternalLogins
   * @inject
   */
  public $externalLogins;

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
   * @var AuthService
   * @inject
   */
  public $authService;

  /**
   * Get a list of all users
   * @GET
   * @UserIsAllowed(users="view-all")
   */
  public function actionDefault() {
    $users = $this->users->findAll();
    $this->sendSuccessResponse($users);
  }

  /**
   * Create a user account
   * @POST
   * @Param(type="post", name="email", validation="email", description="An email that will serve as a login name")
   * @Param(type="post", name="firstName", validation="string:2..", description="First name")
   * @Param(type="post", name="lastName", validation="string:2..", description="Last name")
   * @Param(type="post", name="password", validation="string:1..", msg="Password cannot be empty.", description="A password for authentication")
   * @Param(type="post", name="instanceId", validation="string:1..", description="Identifier of the instance to register in")
   */
  public function actionCreateAccount() {
    $req = $this->getHttpRequest();

    // check if the email is free
    $email = $req->getPost("email");
    if ($this->users->getByEmail($email) !== NULL) {
      throw new BadRequestException("This email address is already taken.");
    }

    $role = $this->roles->get(Role::STUDENT);
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
   * Create an account authenticated with an external service
   * @POST
   * @Param(type="post", name="username", validation="string:2..", description="Login name")
   * @Param(type="post", name="password", validation="string:1..", msg="Password cannot be empty.", description="Authentication password")
   * @Param(type="post", name="instanceId", validation="string:1..", description="Identifier of the instance to register in")
   * @Param(type="post", name="serviceId", validation="string:1..", description="Identifier of the authentication service")
   */
  public function actionCreateAccountExt() {
    $req = $this->getHttpRequest();
    $serviceId = $req->getPost("serviceId");

    $role = $this->roles->get(Role::STUDENT);
    $instanceId = $req->getPost("instanceId");
    $instance = $this->instances->get($instanceId);
    if (!$instance) {
      throw new BadRequestException("Instance '$instanceId' does not exist.");
    }

    $username = $req->getPost("username");
    $password = $req->getPost("password");

    $authService = $this->authService->getById($serviceId);
    $externalData = $authService->getUser($username, $password); // throws if the user cannot be logged in
    $user = $this->externalLogins->getUser($serviceId, $externalData->getId());

    if ($user != NULL) {
      throw new BadRequestException("User is already registered.");
    }

    $user = $externalData->createEntity($instance, $role);
    $this->users->persist($user);

    $externalLogin = new ExternalLogin($user, $serviceId, $externalData->getId());
    $this->externalLogins->persist($externalLogin);

    // successful!
    $this->sendSuccessResponse([
      "user" => $user,
      "accessToken" => $this->accessManager->issueToken($user)
    ], IResponse::S201_CREATED);
  }

  /**
   * Check if the registered E-mail isn't already used and if the password is strong enough
   * @POST
   * @Param(type="post", name="email", description="E-mail address (login name)")
   * @Param(type="post", name="password", description="Authentication password")
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
   * Get details of a user account
   * @GET
   * @LoggedIn
   * @UserIsAllowed(users="view-all")
   */
  public function actionDetail(string $id) {
    $user = $this->users->findOrThrow($id);
    $this->sendSuccessResponse($user);
  }

  /**
   * Update the profile associated with a user account
   * @POST
   * @LoggedIn
   * @Param(type="post", name="email", validation="email", description="E-mail address")
   * @Param(type="post", name="firstName", validation="string:2..", description="First name")
   * @Param(type="post", name="lastName", validation="string:2..", description="Last name")
   * @Param(type="post", name="degreesBeforeName", validation="string:1..", description="Degrees before name")
   * @Param(type="post", name="degreesAfterName", validation="string:1..", description="Degrees after name")
   */
  public function actionUpdateProfile() {
    $req = $this->getHttpRequest();
    $email = $req->getPost("email");
    $firstName = $req->getPost("firstName");
    $lastName = $req->getPost("lastName");
    $degreesBeforeName = $req->getPost("degreesBeforeName");
    $degreesAfterName = $req->getPost("degreesAfterName");

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
        if (!$login->passwordsMatch($oldPassword)) {
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
   * Get a list of instances where a user is registered
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
   * Get a list of exercises authored by a user
   * @GET
   * @LoggedIn
   * @UserIsAllowed(users="view-exercises")
   */
  public function actionExercises(string $id) {
    $user = $this->users->findOrThrow($id);
    $this->sendSuccessResponse($user->getExercises()->getValues());
  }

}
