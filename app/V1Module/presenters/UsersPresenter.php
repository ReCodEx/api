<?php

namespace App\V1Module\Presenters;

use App\Model\Entity\Group;
use App\Model\Repository\Logins;
use App\Exceptions\BadRequestException;
use App\Helpers\EmailVerificationHelper;

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
   * Get a list of all users
   * @GET
   * @UserIsAllowed(users="view-all")
   */
  public function actionDefault() {
    $users = $this->users->findAll();
    $this->sendSuccessResponse($users);
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

      // check if there is not another user using provided email
      $userEmail = $this->users->getByEmail($email);
      if ($userEmail !== NULL && $userEmail->getId() !== $user->getId()) {
        throw new BadRequestException("This email address is already taken.");
      }

      $user->setEmail($email); // @todo: The email address must be now validated

      // do not forget to change local login (if any)
      $login = $this->logins->findCurrent();
      if ($login) {
        $login->setUsername($email);
      }

      // email has to be re-verified
      $user->setVerified(FALSE);
      $this->emailVerificationHelper->process($user);
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
