<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\WrongCredentialsException;
use App\Model\Entity\Group;
use App\Model\Entity\Login;
use App\Model\Entity\User;
use App\Model\Repository\Logins;
use App\Exceptions\BadRequestException;
use App\Helpers\EmailVerificationHelper;
use App\Model\View\GroupViewFactory;
use App\Model\View\UserViewFactory;
use App\Security\ACL\IUserPermissions;
use App\Security\Roles;
use DateTime;

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
   * @var EmailVerificationHelper
   * @inject
   */
  public $emailVerificationHelper;

  /**
   * @var GroupViewFactory
   * @inject
   */
  public $groupViewFactory;

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

  public function checkDefault() {
    if (!$this->userAcl->canViewAll()) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Get a list of all users
   * @GET
   */
  public function actionDefault() {
    $users = $this->users->findAll();
    $this->sendSuccessResponse($this->userViewFactory->getUsers($users));
  }

  public function checkListByIds() {
    if (!$this->userAcl->canViewList()) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Get a list of users based on given ids.
   * @POST
   * @Param(type="post", name="ids", validation="array", description="Identifications of users")
   */
  public function actionListByIds() {
    $users = $this->users->findByIds($this->getRequest()->getPost("ids"));
    $users = array_filter($users, function (User $user) {
      return $this->userAcl->canViewPublicData($user);
    });
    $this->sendSuccessResponse($this->userViewFactory->getUsers($users));
  }

  public function checkDetail(string $id) {
    $user = $this->users->findOrThrow($id);
    if (!$this->userAcl->canViewPublicData($user)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Get details of a user account
   * @GET
   * @param string $id Identifier of the user
   */
  public function actionDetail(string $id) {
    $user = $this->users->findOrThrow($id);
    $this->sendSuccessResponse($this->userViewFactory->getUser($user));
  }

  public function checkDelete(string $id) {
    $user = $this->users->findOrThrow($id);
    if (!$this->userAcl->canDelete($user)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Delete a user account
   * @DELETE
   * @param string $id Identifier of the user
   * @throws ForbiddenRequestException
   */
  public function actionDelete(string $id) {
    $user = $this->users->findOrThrow($id);
    $this->users->remove($user);
    $this->sendSuccessResponse("OK");
  }

  public function checkUpdateProfile(string $id) {
    $user = $this->users->findOrThrow($id);

    if (!$this->userAcl->canUpdateProfile($user)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Update the profile associated with a user account
   * @POST
   * @param string $id Identifier of the user
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   * @Param(type="post", name="firstName", required=false, validation="string:2..", description="First name")
   * @Param(type="post", name="lastName", required=false, validation="string:2..", description="Last name")
   * @Param(type="post", name="degreesBeforeName", description="Degrees before name")
   * @Param(type="post", name="degreesAfterName", description="Degrees after name")
   * @Param(type="post", name="email", validation="email", description="New email address", required=false)
   * @Param(type="post", name="oldPassword", required=false, validation="string:1..", description="Old password of current user")
   * @Param(type="post", name="password", required=false, validation="string:1..", description="New password of current user")
   * @Param(type="post", name="passwordConfirm", required=false, validation="string:1..", description="Confirmation of new password of current user")
   * @Param(type="post", name="gravatarUrlEnabled", validation="bool", description="Enable or disable gravatar profile image")
   * @throws WrongCredentialsException
   * @throws NotFoundException
   */
  public function actionUpdateProfile(string $id) {
    $req = $this->getRequest();

    // fill user with provided data
    $user = $this->users->findOrThrow($id);
    $login = $this->logins->findCurrent();

    // change details in separate methods
    $this->changeUserEmail($user, $login, $req->getPost("email"));
    $this->changeFirstAndLastName($user, $req->getPost("firstName"), $req->getPost("lastName"));
    $passwordChanged = $this->changeUserPassword($login, $req->getPost("oldPassword"),
      $req->getPost("password"), $req->getPost("passwordConfirm"));

    $user->setDegreesBeforeName($req->getPost("degreesBeforeName"));
    $user->setDegreesAfterName($req->getPost("degreesAfterName"));
    $user->setGravatar(filter_var($req->getPost("gravatarUrlEnabled"), FILTER_VALIDATE_BOOLEAN));

    // make changes permanent
    $this->users->flush();
    $this->logins->flush();

    $this->sendSuccessResponse([
      "user" => $this->userViewFactory->getUser($user),
      "accessToken" => $passwordChanged ? $this->accessManager->issueRefreshedToken($this->getAccessToken()) : null
    ]);
  }

  /**
   * Change email if any given of the provided user.
   * @param User $user
   * @param Login|null $login
   * @param null|string $email
   * @throws BadRequestException
   * @throws InvalidArgumentException
   */
  private function changeUserEmail(User $user, ?Login $login, ?string $email) {
    $email = trim($email);
    if ($email === null || strlen($email) === 0) {
      return;
    }

    // check if there is not another user using provided email
    $userEmail = $this->users->getByEmail($email);
    if ($userEmail !== null && $userEmail->getId() !== $user->getId()) {
      throw new BadRequestException("This email address is already taken.");
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
      throw new InvalidArgumentException("Provided email is not in correct format");
    }

    $oldEmail = $user->getEmail();
    if (strtolower($oldEmail) !== strtolower($email)) {
      // old and new email are not same, we have to changed and verify it
      $user->setEmail($email);

      // do not forget to change local login (if any)
      if ($login) {
        $login->setUsername($email);
      }

      // email has to be re-verified
      $user->setVerified(false);
      $this->emailVerificationHelper->process($user);
    }
  }

  /**
   * Change firstname and second name and check if user can change them.
   * @param User $user
   * @param null|string $firstname
   * @param null|string $lastname
   * @throws ForbiddenRequestException
   */
  public function changeFirstAndLastName(User $user, ?string $firstname, ?string $lastname) {
    if (($firstname !== null || $lastname !== null) &&
        !$this->userAcl->canUpdatePersonalData($user)) {
      throw new ForbiddenRequestException("You cannot update personal data");
    }

    if ($firstname) {
      $user->setFirstName($firstname);
    }

    if ($lastname) {
      $user->setLastName($lastname);
    }
  }

  /**
   * Change password of user if provided.
   * @param Login|null $login
   * @param null|string $oldPassword
   * @param null|string $password
   * @param null|string $passwordConfirm
   * @throws InvalidArgumentException
   * @throws WrongCredentialsException
   */
  private function changeUserPassword(?Login $login, ?string $oldPassword,
      ?string $password, ?string $passwordConfirm): bool {

    if (!$login || (!$oldPassword && !$password && !$passwordConfirm)) {
      // password was not provided, or user is not logged as local one
      return false;
    }

    if (!$password || !$passwordConfirm) {
      // old password was provided but the new ones not, illegal state
      throw new InvalidArgumentException("New password was not provided");
    }

    // passwords need to be handled differently
    if ($login->passwordsMatch($oldPassword)) {
      // old password was provided, just check it against the one from db
      if ($password !== $passwordConfirm) {
        throw new WrongCredentialsException("Provided passwords do not match");
      }

      $login->changePassword($password);
      $login->getUser()->setTokenValidityThreshold(new DateTime());
    } else {
      throw new WrongCredentialsException("Your current password does not match");
    }

    return true;
  }

  public function checkUpdateSettings(string $id) {
    $user = $this->users->findOrThrow($id);

    if (!$this->userAcl->canUpdateProfile($user)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Update the profile settings
   * @POST
   * @param string $id Identifier of the user
   * @Param(type="post", name="darkTheme", validation="bool", description="Flag if dark theme is used", required=false)
   * @Param(type="post", name="vimMode", validation="bool", description="Flag if vim keybinding is used", required=false)
   * @Param(type="post", name="openedSidebar", validation="bool", description="Flag if the sidebar of the web-app should be opened by default.", required=false)
   * @Param(type="post", name="defaultLanguage", validation="string", description="Default language of UI", required=false)
   * @Param(type="post", name="useGravatar", validation="bool", description="Flag if the UI should display gravatars or not", required=false)
   * @Param(type="post", name="newAssignmentEmails", validation="bool", description="Flag if email should be sent to user when new assignment was created", required=false)
   * @Param(type="post", name="assignmentDeadlineEmails", validation="bool", description="Flag if email should be sent to user if assignment deadline is nearby", required=false)
   * @Param(type="post", name="submissionEvaluatedEmails", validation="bool", description="Flag if email should be sent to user when resubmission was evaluated", required=false)
   * @throws NotFoundException
   */
  public function actionUpdateSettings(string $id) {
    $req = $this->getRequest();
    $user = $this->users->findOrThrow($id);
    $settings = $user->getSettings();

    $darkTheme = $req->getPost("darkTheme") !== null
      ? filter_var($req->getPost("darkTheme"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getDarkTheme();
    $vimMode = $req->getPost("vimMode") !== null
      ? filter_var($req->getPost("vimMode"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getVimMode();
    $openedSidebar = $req->getPost("openedSidebar") !== null
      ? filter_var($req->getPost("openedSidebar"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getOpenedSidebar();
    $defaultLanguage = $req->getPost("defaultLanguage") !== null ? $req->getPost("defaultLanguage") : $settings->getDefaultLanguage();
    $newAssignmentEmails = $req->getPost("newAssignmentEmails") !== null
      ? filter_var($req->getPost("newAssignmentEmails"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getNewAssignmentEmails();
    $assignmentDeadlineEmails = $req->getPost("assignmentDeadlineEmails") !== null
      ? filter_var($req->getPost("assignmentDeadlineEmails"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getAssignmentDeadlineEmails();
    $submissionEvaluatedEmails = $req->getPost("submissionEvaluatedEmails") !== null
      ? filter_var($req->getPost("submissionEvaluatedEmails"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getSubmissionEvaluatedEmails();
    $useGravatar = $req->getPost("useGravatar") !== null
      ? filter_var($req->getPost("useGravatar"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getUseGravatar();

    $settings->setDarkTheme($darkTheme);
    $settings->setVimMode($vimMode);
    $settings->setOpenedSidebar($openedSidebar);
    $settings->setDefaultLanguage($defaultLanguage);
    $settings->setNewAssignmentEmails($newAssignmentEmails);
    $settings->setAssignmentDeadlineEmails($assignmentDeadlineEmails);
    $settings->setSubmissionEvaluatedEmails($submissionEvaluatedEmails);
    $settings->setUseGravatar($useGravatar);

    $this->users->persist($user);
    $this->sendSuccessResponse($this->userViewFactory->getUser($user));
  }

  public function checkCreateLocalAccount(string $id) {
    $user = $this->users->findOrThrow($id);
    if (!$this->userAcl->canCreateLocalAccount($user)) {
      throw new ForbiddenRequestException();
    }

    if ($user->hasLocalAccounts()) {
      throw new BadRequestException("User is already registered locally");
    }
  }

  /**
   * If user is registered externally, add local account as another login method.
   * Created password is empty and has to be changed in order to use it.
   * @POST
   * @param string $id
   * @throws InvalidArgumentException
   */
  public function actionCreateLocalAccount(string $id) {
    $user = $this->users->findOrThrow($id);

    Login::createLogin($user, $user->getEmail(), "");
    $this->users->flush();
    $this->sendSuccessResponse($this->userViewFactory->getUser($user));
  }

  public function checkGroups(string $id) {
    $user = $this->users->findOrThrow($id);

    if (!$this->userAcl->canViewGroups($user)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Get a list of non-archived groups for a user
   * @GET
   * @param string $id Identifier of the user
   */
  public function actionGroups(string $id) {
    $user = $this->users->findOrThrow($id);

    $asStudent = $user->getGroupsAsStudent()->filter(function (Group $group) {
      return !$group->isArchived();
    });

    $asSupervisor = $user->getGroupsAsSupervisor()->filter(function (Group $group) {
      return !$group->isArchived();
    });

    $this->sendSuccessResponse([
      "supervisor" => $this->groupViewFactory->getGroups($asSupervisor->getValues()),
      "student" => $this->groupViewFactory->getGroups($asStudent->getValues()),
      "stats" => $user->getGroupsAsStudent()->map(
        function (Group $group) use ($user) {
          return $this->groupViewFactory->getStudentsStats($group, $user);
        }
      )->getValues(),
    ]);
  }

  public function checkAllGroups(string $id) {
    $user = $this->users->findOrThrow($id);

    if (!$this->userAcl->canViewGroups($user)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Get a list of all groups for a user
   * @GET
   * @param string $id Identifier of the user
   */
  public function actionAllGroups(string $id) {
    $user = $this->users->findOrThrow($id);
    $this->sendSuccessResponse([
      "supervisor" => $this->groupViewFactory->getGroups($user->getGroupsAsSupervisor()->getValues(), false),
      "student" => $this->groupViewFactory->getGroups($user->getGroupsAsStudent()->getValues(), false)
    ]);
  }

  public function checkInstances(string $id) {
    $user = $this->users->findOrThrow($id);

    if (!$this->userAcl->canViewInstances($user)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Get a list of instances where a user is registered
   * @GET
   * @param string $id Identifier of the user
   */
  public function actionInstances(string $id) {
    $user = $this->users->findOrThrow($id);

    $this->sendSuccessResponse([
      $user->getInstance() // @todo change when the user can be member of multiple instances
    ]);
  }

  public function checkExercises(string $id) {
    $user = $this->users->findOrThrow($id);

    if (!$this->userAcl->canViewExercises($user)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Get a list of exercises authored by a user
   * @GET
   * @param string $id Identifier of the user
   */
  public function actionExercises(string $id) {
    $user = $this->users->findOrThrow($id);
    $this->sendSuccessResponse($user->getExercises()->getValues());
  }

  public function checkSetRole(string $id) {
    $user = $this->users->findOrThrow($id);
    if (!$this->userAcl->canSetRole($user)) {
      throw new ForbiddenRequestException();
    }

    if ($this->getCurrentUser() === $user) {
      throw new ForbiddenRequestException("You cannot change your role");
    }
  }

  /**
   * Set a given role to the given user.
   * @POST
   * @param string $id Identifier of the user
   * @Param(type="post", name="role", validation="bool", description="Role which should be assigned to the user")
   * @throws InvalidArgumentException
   * @throws NotFoundException
   */
  public function actionSetRole(string $id) {
    $user = $this->users->findOrThrow($id);
    $role = $this->getRequest()->getPost("role");
    // validate role
    if (!Roles::validateRole($role)) {
      throw new InvalidArgumentException("role", "Unknown user role '$role'");
    }

    $user->setRole($role);
    $this->users->flush();
    $this->sendSuccessResponse($user);
  }

  public function checkInvalidateTokens(string $id) {
    $user = $this->users->findOrThrow($id);

    if (!$this->userAcl->canInvalidateTokens($user)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Invalidate all existing tokens issued for given user
   * @POST
   * @param string $id Identifier of the user
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionInvalidateTokens(string $id) {
    $user = $this->users->findOrThrow($id);
    $user->setTokenValidityThreshold(new DateTime());
    $this->users->flush();
    $token = $this->getAccessToken();

    $this->sendSuccessResponse([
      "accessToken" => $this->accessManager->issueRefreshedToken($token)
    ]);
  }

}
