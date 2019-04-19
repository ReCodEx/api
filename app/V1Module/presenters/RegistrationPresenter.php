<?php

namespace App\V1Module\Presenters;

use App\Exceptions\FrontendErrorMappings;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\WrongCredentialsException;
use App\Exceptions\ForbiddenRequestException;
use App\Model\Entity\Login;
use App\Model\Entity\User;
use App\Model\Entity\Instance;
use App\Model\Repository\Groups;
use App\Model\Repository\Logins;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Instances;
use App\Model\View\UserViewFactory;
use App\Security\AccessManager;
use App\Exceptions\BadRequestException;
use App\Helpers\ExternalLogin\ExternalServiceAuthenticator;
use App\Helpers\EmailVerificationHelper;
use App\Helpers\RegistrationConfig;
use App\Security\Roles;
use App\Security\ACL\IUserPermissions;
use Nette\Http\IResponse;
use ZxcvbnPhp\Zxcvbn;

/**
 * Registration management endpoints
 */
class RegistrationPresenter extends BasePresenter {

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
   * @var Instances
   * @inject
   */
  public $instances;

  /**
   * @var Groups
   * @inject
   */
  public $groups;

  /**
   * @var ExternalServiceAuthenticator
   * @inject
   */
  public $externalServiceAuthenticator;

  /**
   * @var EmailVerificationHelper
   * @inject
   */
  public $emailVerificationHelper;

  /**
   * @var UserViewFactory
   * @inject
   */
  public $userViewFactory;

  /**
   * @var IUserPermissions
   * @inject
   */
  public $userAcl;

  /**
   * @var RegistrationConfig
   * @inject
   */
  public $registrationConfig;

  /**
   * Get an instance by its ID.
   * @param string $instanceId
   * @return Instance
   * @throws BadRequestException
   */
  protected function getInstance(string $instanceId): Instance {
    $instance = $this->instances->get($instanceId);
    if (!$instance) {
      throw new BadRequestException("Instance '$instanceId' does not exist.");
    } else if (!$instance->getIsOpen()) {
      throw new BadRequestException("This instance is not open, you cannot register here.");
    }

    return $instance;
  }

  public function checkCreateAccount() {
    if (!$this->registrationConfig->isEnabled()) {
      // If the registration is not enabled in general, creator must be logged in and have priviledges.
      if (!$this->userAcl->canCreate()) {
        throw new ForbiddenRequestException();
      }
    }
  }

  /**
   * Create a user account
   * @POST
   * @Param(type="post", name="email", validation="email", description="An email that will serve as a login name")
   * @Param(type="post", name="firstName", validation="string:2..", description="First name")
   * @Param(type="post", name="lastName", validation="string:2..", description="Last name")
   * @Param(type="post", name="password", validation="string:1..", msg="Password cannot be empty.", description="A password for authentication")
   * @Param(type="post", name="passwordConfirm", validation="string:1..", msg="Confirm Password cannot be empty.", description="A password confirmation")
   * @Param(type="post", name="instanceId", validation="string:1..", description="Identifier of the instance to register in")
   * @Param(type="post", name="degreesBeforeName", required=false, validation="string:1..", description="Degrees which is placed before user name")
   * @Param(type="post", name="degreesAfterName", required=false, validation="string:1..", description="Degrees which is placed after user name")
   * @throws BadRequestException
   * @throws WrongCredentialsException
   * @throws InvalidArgumentException
   */
  public function actionCreateAccount() {
    $req = $this->getRequest();

    // check if the email is free
    $email = trim($req->getPost("email"));
    // username is name of column which holds login identifier represented by email
    if ($this->logins->getByUsername($email) !== null) {
      throw new BadRequestException("This email address is already taken.");
    }

    $instanceId = $req->getPost("instanceId");
    $instance = $this->getInstance($instanceId);

    $degreesBeforeName = $req->getPost("degreesBeforeName") === null ? "" : $req->getPost("degreesBeforeName");
    $degreesAfterName = $req->getPost("degreesAfterName") === null ? "" : $req->getPost("degreesAfterName");

    // check given passwords
    $password = $req->getPost("password");
    $passwordConfirm = $req->getPost("passwordConfirm");
    if ($password !== $passwordConfirm) {
      throw new WrongCredentialsException(
        "Provided passwords do not match",
        FrontendErrorMappings::E400_102__WRONG_CREDENTIALS_PASSWORDS_NOT_MATCH
      );
    }

    $user = new User(
      $email,
      $req->getPost("firstName"),
      $req->getPost("lastName"),
      $degreesBeforeName,
      $degreesAfterName,
      Roles::STUDENT_ROLE,
      $instance
    );

    Login::createLogin($user, $email, $password);
    $this->users->persist($user);

    // email verification
    $this->emailVerificationHelper->process($user, true); // true = account has just been created

    // add new user to implicit groups
    foreach ($this->registrationConfig->getImplicitGroupsIds() as $groupId) {
      $group = $this->groups->findOrThrow($groupId);
      if (!$group->isArchived() && !$group->isOrganizational()) {
        $user->makeStudentOf($group);
      }
    }
    $this->groups->flush();

    // successful!
    $this->sendSuccessResponse([
      "user" => $this->userViewFactory->getFullUser($user),
      "accessToken" => $this->accessManager->issueToken($user)
    ], IResponse::S201_CREATED);
  }

  /**
   * Create an account authenticated with an external service (and link it with either a new user account or an existing one)
   * @POST
   * @Param(type="post", name="instanceId", validation="string:1..", description="Identifier of the instance to register in")
   * @Param(type="post", name="serviceId", validation="string:1..", description="Identifier of the authentication service")
   * @throws BadRequestException
   * @throws WrongCredentialsException
   */
  public function actionCreateAccountExt() {
    $req = $this->getRequest();
    $serviceId = $req->getPost("serviceId");
    $authType = $req->getPost("authType");

    $instanceId = $req->getPost("instanceId");
    $instance = $this->getInstance($instanceId);

    $authService = $this->externalServiceAuthenticator->findService($serviceId, $authType);
    $user = $this->externalServiceAuthenticator->register($authService, $instance, $req->getPost());

    // email verification
    if (!$user->isVerified()) {
      $this->emailVerificationHelper->process($user, true); // true = account has just been created
    }

    // successful!
    $this->sendSuccessResponse([
      "user" => $this->userViewFactory->getFullUser($user),
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
    $zxcvbn = new Zxcvbn();
    $passwordStrength = $zxcvbn->passwordStrength($password, [ $email, $emailParts[0] ]);

    $this->sendSuccessResponse([
      "usernameIsFree" => $user === null,
      "passwordScore" => $passwordStrength["score"]
    ]);
  }

}
