<?php

namespace App\V1Module\Presenters;

use App\Model\Entity\Group;
use App\Model\Entity\Instance;
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
use App\Helpers\ExternalLogin\ExternalServiceAuthenticator;
use Nette\Http\IResponse;
use App\Security\AccessToken;

use ZxcvbnPhp\Zxcvbn;

/**
 * User management endpoints
 */
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
   * @var ExternalServiceAuthenticator
   * @inject
   */
  public $externalServiceAuthenticator;

  /**
   * Get an instance by its ID.
   * @param string $instanceId
   * @return Instance
   * @throws BadRequestException
   */
  public function getInstance(string $instanceId): Instance {
    $instance = $this->instances->get($instanceId);
    if (!$instance) {
      throw new BadRequestException("Instance '$instanceId' does not exist.");
    } else if (!$instance->getIsOpen()) {
      throw new BadRequestException("This instance is not open, you cannot register here.");
    }

    return $instance;
  }

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
   * @Param(type="post", name="degreesBeforeName", required=false, validation="string:1..", description="Degrees which is placed before user name")
   * @Param(type="post", name="degreesAfterName", required=false, validation="string:1..", description="Degrees which is placed after user name")
   */
  public function actionCreateAccount() {
    $req = $this->getRequest();

    // check if the email is free
    $email = $req->getPost("email");
    if ($this->users->getByEmail($email) !== NULL) {
      throw new BadRequestException("This email address is already taken.");
    }

    $role = $this->roles->get(Role::STUDENT);
    $instanceId = $req->getPost("instanceId");
    $instance = $this->getInstance($instanceId);

    $degreesBeforeName = $req->getPost("degreesBeforeName") === NULL ? "" : $req->getPost("degreesBeforeName");
    $degreesAfterName = $req->getPost("degreesAfterName") === NULL ? "" : $req->getPost("degreesAfterName");

    $user = new User(
      $email,
      $req->getPost("firstName"),
      $req->getPost("lastName"),
      $degreesBeforeName,
      $degreesAfterName,
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
   * @Param(type="post", name="instanceId", validation="string:1..", description="Identifier of the instance to register in")
   * @Param(type="post", name="serviceId", validation="string:1..", description="Identifier of the authentication service")
   */
  public function actionCreateAccountExt() {
    $req = $this->getRequest();
    $serviceId = $req->getPost("serviceId");
    $authType = $req->getPost("authType");

    $role = $this->roles->get(Role::STUDENT);
    $instanceId = $req->getPost("instanceId");
    $instance = $this->getInstance($instanceId);

    $authService = $this->externalServiceAuthenticator->findService($serviceId, $authType);
    $externalData = $authService->getUser($req->getPost()); // throws if the user cannot be logged in
    $user = $this->externalLogins->getUser($serviceId, $externalData->getId());

    if ($user !== NULL) {
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
    $req = $this->getRequest();
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
   * @param string $id Identifier of the user
   */
  public function actionDetail(string $id) {
    $user = $this->users->findOrThrow($id);
    $this->sendSuccessResponse($user);
  }

  /**
   * Update the profile associated with a user account
   * @POST
   * @LoggedIn
   * @Param(type="post", name="firstName", validation="string:2..", description="First name")
   * @Param(type="post", name="lastName", validation="string:2..", description="Last name")
   * @Param(type="post", name="degreesBeforeName", description="Degrees before name")
   * @Param(type="post", name="degreesAfterName", description="Degrees after name")
   * @Param(type="post", name="email", description="New email address", required=FALSE)
   */
  public function actionUpdateProfile() {
    $req = $this->getRequest();
    $firstName = $req->getPost("firstName");
    $lastName = $req->getPost("lastName");
    $degreesBeforeName = $req->getPost("degreesBeforeName");
    $degreesAfterName = $req->getPost("degreesAfterName");

    // fill user with all provided datas
    $user = $this->getCurrentUser();

    // change the email only of the user wants to
    $email = $req->getPost("email");
    if ($email && strlen($email) > 0) {
      $user->setEmail($email);
    }

    $user->setFirstName($firstName);
    $user->setLastName($lastName);
    $user->setDegreesBeforeName($degreesBeforeName);
    $user->setDegreesAfterName($degreesAfterName);

    // make changes permanent
    $this->users->flush();

    $this->sendSuccessResponse($user);
  }

  /**
   * Update internal authentication system user account.
   * @POST
   * @LoggedIn
   * @Param(type="post", name="email", description="New email address", required=FALSE)
   * @Param(type="post", name="password", required=FALSE, validation="string:1..", description="Old password of current user")
   * @Param(type="post", name="newPassword", required=FALSE, validation="string:1..", description="New password of current user")
   */
  public function actionUpdateLogin() {
    $req = $this->getRequest();
    $oldPassword = $req->getPost("password");
    $newPassword = $req->getPost("newPassword");

    // fill user with all provided datas
    $login = $this->logins->findCurrent();
    $user = $this->getCurrentUser();

    if ($login === NULL) {
      throw new BadRequestException("User is not authenticated through system internal mechanism");
    }

    // change the email only of the user wants to
    $email = $req->getPost("email");
    if ($email && strlen($email) > 0) {
      $user->setEmail($email);
      // do not forget to change local login (if any)
      if ($login) {
        $login->setUsername($email);
      }
    }

    // passwords need to be handled differently
    if ($newPassword) {
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
    }

    // make password changes permanent
    $this->logins->flush();
    $this->users->flush();

    $this->sendSuccessResponse($user);
  }

  /**
   * Update the profile settings
   * @POST
   * @LoggedIn
   * @Param(type="post", name="darkTheme", validation="bool", description="Flag if dark theme is used", required=FALSE)
   * @Param(type="post", name="vimMode", validation="bool", description="Flag if vim keybinding is used", required=FALSE)
   * @Param(type="post", name="openedSidebar", validation="bool", description="Flag if the sidebar of the web-app should be opened by default.", required=FALSE)
   * @Param(type="post", name="defaultLanguage", validation="string", description="Default language of UI", required=FALSE)
   */
  public function actionUpdateSettings() {
    $req = $this->getRequest();
    $user = $this->getCurrentUser();
    $settings = $user->getSettings();

    $darkTheme = $req->getPost("darkTheme") !== NULL
      ? filter_var($req->getPost("darkTheme"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getDarkTheme();
    $vimMode = $req->getPost("vimMode") !== NULL
      ? filter_var($req->getPost("vimMode"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getVimMode();
    $openedSidebar = $req->getPost("openedSidebar") !== NULL
      ? filter_var($req->getPost("openedSidebar"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getOpenedSidebar();
    $defaultLanguage = $req->getPost("defaultLanguage") !== NULL ? $req->getPost("defaultLanguage") : $settings->getDefaultLanguage();

    $settings->setDarkTheme($darkTheme);
    $settings->setVimMode($vimMode);
    $settings->setOpenedSidebar($openedSidebar);
    $settings->setDefaultLanguage($defaultLanguage);

    $this->users->persist($user);
    $this->sendSuccessResponse($user);
  }

  /**
   * Get a list of groups for a user
   * @GET
   * @LoggedIn
   * @UserIsAllowed(users="view-groups")
   * @param string $id Identifier of the user
   */
  public function actionGroups(string $id) {
    $user = $this->users->findOrThrow($id);
    $this->sendSuccessResponse([
      "supervisor" => $user->getGroupsAsSupervisor()->getValues(),
      "student" => $user->getGroupsAsStudent()->getValues(),
      "stats" => $user->getGroupsAsStudent()->map(
        function (Group $group) use ($user) {
          $stats = $group->getStudentsStats($user);
          return array_merge([
            "id" => $group->getId(),
            "name" => $group->getName()
          ], $stats);
        }
      )->getValues()
    ]);
  }

  /**
   * Get a list of instances where a user is registered
   * @GET
   * @LoggedIn
   * @UserIsAllowed(users="view-instances")
   * @param string $id Identifier of the user
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
   * @param string $id Identifier of the user
   */
  public function actionExercises(string $id) {
    $user = $this->users->findOrThrow($id);
    $this->sendSuccessResponse($user->getExercises()->getValues());
  }

}
