<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\FrontendErrorMappings;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\WrongCredentialsException;
use App\Model\Entity\Group;
use App\Model\Entity\Login;
use App\Model\Entity\User;
use App\Model\Entity\UserUiData;
use App\Model\Repository\Logins;
use App\Exceptions\BadRequestException;
use App\Helpers\EmailVerificationHelper;
use App\Helpers\AnonymizationHelper;
use App\Model\View\ExerciseViewFactory;
use App\Model\View\GroupViewFactory;
use App\Model\View\InstanceViewFactory;
use App\Model\View\UserViewFactory;
use App\Security\ACL\IUserPermissions;
use App\Security\Roles;
use Nette\Security\Passwords;
use DateTime;

/**
 * User management endpoints
 */
class UsersPresenter extends BasePresenter
{

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
     * @var AnonymizationHelper
     * @inject
     */
    public $anonymizationHelper;

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

    /**
     * @var ExerciseViewFactory
     * @inject
     */
    public $exerciseViewFactory;

    /**
     * @var Roles
     * @inject
     */
    public $roles;

    /**
     * @var InstanceViewFactory
     * @inject
     */
    public $instanceViewFactory;

    /**
     * @var Passwords
     * @inject
     */
    public $passwordsService;


    public function checkDefault()
    {
        if (!$this->userAcl->canViewAll()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of all users matching given filters in given pagination rage.
     * The result conforms to pagination protocol.
     * @GET
     * @param int $offset Index of the first result.
     * @param int|null $limit Maximal number of results returned.
     * @param string|null $orderBy Name of the column (column concept). The '!' prefix indicate descending order.
     * @param array|null $filters Named filters that prune the result.
     * @param string|null $locale Currently set locale (used to augment order by clause if necessary),
     */
    public function actionDefault(
        int $offset = 0,
        int $limit = null,
        string $orderBy = null,
        array $filters = null,
        string $locale = null
    ) {
        $pagination = $this->getPagination(
            $offset,
            $limit,
            $locale,
            $orderBy,
            ($filters === null) ? [] : $filters,
            ['search', 'instanceId', 'roles']
        );
        $users = $this->users->getPaginated($pagination, $totalCount);
        $users = array_map(
            function (User $user) {
                return $this->userViewFactory->getUser($user);
            },
            $users
        );
        $this->sendPaginationSuccessResponse($users, $pagination, false, $totalCount);
    }

    public function checkListByIds()
    {
        if (!$this->userAcl->canViewList()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of users based on given ids.
     * @POST
     * @Param(type="post", name="ids", validation="array", description="Identifications of users")
     */
    public function actionListByIds()
    {
        $users = $this->users->findByIds($this->getRequest()->getPost("ids"));
        $users = array_filter(
            $users,
            function (User $user) {
                return $this->userAcl->canViewPublicData($user);
            }
        );
        $this->sendSuccessResponse($this->userViewFactory->getUsers($users));
    }

    public function checkDetail(string $id)
    {
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
    public function actionDetail(string $id)
    {
        $user = $this->users->findOrThrow($id);
        $this->sendSuccessResponse($this->userViewFactory->getUser($user));
    }

    public function checkDelete(string $id)
    {
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
    public function actionDelete(string $id)
    {
        $user = $this->users->findOrThrow($id);
        $this->anonymizationHelper->prepareUserForSoftDelete($user);
        $this->users->remove($user);
        $this->sendSuccessResponse("OK");
    }

    public function checkUpdateProfile(string $id)
    {
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
    public function actionUpdateProfile(string $id)
    {
        $req = $this->getRequest();

        // fill user with provided data
        $user = $this->users->findOrThrow($id);
        $login = $user->getLogin();

        // change details in separate methods
        $this->changeUserEmail($user, $req->getPost("email"));
        $this->changeFirstAndLastName($user, $req->getPost("firstName"), $req->getPost("lastName"));
        $passwordChanged = $this->changeUserPassword(
            $login,
            $req->getPost("oldPassword"),
            $req->getPost("password"),
            $req->getPost("passwordConfirm")
        );

        $user->setDegreesBeforeName($req->getPost("degreesBeforeName"));
        $user->setDegreesAfterName($req->getPost("degreesAfterName"));
        $user->setGravatar(filter_var($req->getPost("gravatarUrlEnabled"), FILTER_VALIDATE_BOOLEAN));

        // make changes permanent
        $this->users->flush();
        $this->logins->flush();

        $this->sendSuccessResponse(
            [
                "user" => $this->userViewFactory->getUser($user),
                "accessToken" => $passwordChanged && $user === $this->getCurrentUser()
                    ? $this->accessManager->issueRefreshedToken($this->getAccessToken())
                    : null
            ]
        );
    }

    /**
     * Change email if any given of the provided user.
     * @param User $user
     * @param null|string $email
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    private function changeUserEmail(User $user, ?string $email)
    {
        $email = trim($email);
        if (strlen($email) === 0) {
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
            if ($user->getLogin()) {
                $user->getLogin()->setUsername($email);
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
    public function changeFirstAndLastName(User $user, ?string $firstname, ?string $lastname)
    {
        if (
            ($firstname !== null || $lastname !== null) &&
            !$this->userAcl->canUpdatePersonalData($user)
        ) {
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
    private function changeUserPassword(
        ?Login $login,
        ?string $oldPassword,
        ?string $password,
        ?string $passwordConfirm
    ): bool {
        if (!$login || (!$oldPassword && !$password && !$passwordConfirm)) {
            // password was not provided, or user is not logged as local one
            return false;
        }

        if (!$password || !$passwordConfirm) {
            // old password was provided but the new ones not, illegal state
            throw new InvalidArgumentException("New password was not provided");
        }

        // passwords need to be handled differently
        if (
            $login->passwordsMatchOrEmpty($oldPassword, $this->passwordsService) ||
            (!$oldPassword && $this->userAcl->canForceChangePassword($login->getUser()))
        ) {
            // check if new passwords match each other
            if ($password !== $passwordConfirm) {
                throw new WrongCredentialsException(
                    "Provided passwords do not match",
                    FrontendErrorMappings::E400_102__WRONG_CREDENTIALS_PASSWORDS_NOT_MATCH
                );
            }

            $login->changePassword($password, $this->passwordsService);
            $login->getUser()->setTokenValidityThreshold(new DateTime());
        } else {
            throw new WrongCredentialsException(
                "Your current password does not match",
                FrontendErrorMappings::E400_103__WRONG_CREDENTIALS_CURRENT_PASSWORD_NOT_MATCH
            );
        }

        return true;
    }

    public function checkUpdateSettings(string $id)
    {
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
     * @Param(type="post", name="defaultPage", validation="string", description="Default page of the UI", required=false)
     * @Param(type="post", name="useGravatar", validation="bool", description="Flag if the UI should display gravatars or not", required=false)
     * @Param(type="post", name="newAssignmentEmails", validation="bool", description="Flag if email should be sent to user when new assignment was created", required=false)
     * @Param(type="post", name="assignmentDeadlineEmails", validation="bool", description="Flag if email should be sent to user if assignment deadline is nearby", required=false)
     * @Param(type="post", name="submissionEvaluatedEmails", validation="bool", description="Flag if email should be sent to user when resubmission was evaluated", required=false)
     * @Param(type="post", name="solutionCommentsEmails", validation="bool", description="Flag if email should be sent to user when new submission comment is added", required=false)
     * @Param(type="post", name="pointsChangedEmails", validation="bool", description="Flag if email should be sent to user when the points were awarded for assignment", required=false)
     * @Param(type="post", name="assignmentSubmitAfterAcceptedEmails", validation="bool", description="Flag if email should be sent to group supervisor if a student submits new solution for already accepted assignment", required=false)
     * @Param(type="post", name="assignmentSubmitAfterReviewedEmails", validation="bool", description="Flag if email should be sent to group supervisor if a student submits new solution for already reviewed and not accepted assignment", required=false)
     * @throws NotFoundException
     */
    public function actionUpdateSettings(string $id)
    {
        $req = $this->getRequest();
        $user = $this->users->findOrThrow($id);
        $settings = $user->getSettings();

        // handle boolean flags
        $knownBoolFlags = [
            "darkTheme",
            "vimMode",
            "openedSidebar",
            "useGravatar",
            "newAssignmentEmails",
            "assignmentDeadlineEmails",
            "submissionEvaluatedEmails",
            "solutionCommentsEmails",
            "assignmentCommentsEmails",
            "pointsChangedEmails",
            "assignmentSubmitAfterAcceptedEmails",
            "assignmentSubmitAfterReviewedEmails"
        ];

        foreach ($knownBoolFlags as $flag) {
            if ($req->getPost($flag) !== null) {
                $settings->setFlag($flag, filter_var($req->getPost($flag), FILTER_VALIDATE_BOOLEAN));
            }
        }

        // handle string flags
        if ($req->getPost("defaultLanguage") !== null) {
            $settings->setDefaultLanguage(trim($req->getPost("defaultLanguage")));
        }
        if ($req->getPost("defaultPage") !== null) {
            $defaultPage = trim($req->getPost("defaultPage"));
            $defaultPage = $defaultPage === '' ? null : $defaultPage;  // empty string is interpreted as null
            $settings->setDefaultPage($defaultPage);
        }

        $this->users->persist($user);
        $this->sendSuccessResponse($this->userViewFactory->getUser($user));
    }

    public function checkUpdateUiData(string $id)
    {
        $user = $this->users->findOrThrow($id);

        if (!$this->userAcl->canUpdateProfile($user)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Update the user-specific structured UI data
     * @POST
     * @param string $id Identifier of the user
     * @Param(type="post", name="uiData", validation="array|null", description="Structured user-specific UI data")
     * @throws NotFoundException
     */
    public function actionUpdateUiData(string $id)
    {
        $req = $this->getRequest();
        $user = $this->users->findOrThrow($id);

        $newUiData = $req->getPost("uiData");
        if ($newUiData) {
            $uiData = $user->getUiData();
            if (!$uiData) {
                $uiData = new UserUiData($newUiData);
                $user->setUiData($uiData);
            } else {
                $uiData->setData($newUiData);
            }
        } else {
            $user->setUiData(null);
        }

        $this->users->persist($user);
        $this->sendSuccessResponse($this->userViewFactory->getUser($user));
    }

    public function checkCreateLocalAccount(string $id)
    {
        $user = $this->users->findOrThrow($id);
        if (!$this->userAcl->canCreateLocalAccount($user)) {
            throw new ForbiddenRequestException();
        }

        if ($user->hasLocalAccount()) {
            throw new BadRequestException("User is already registered locally");
        }

        if (!$user->isVerified()) {
            throw new BadRequestException("Email address has to be verified before creating local account");
        }
    }

    /**
     * If user is registered externally, add local account as another login method.
     * Created password is empty and has to be changed in order to use it.
     * @POST
     * @param string $id
     * @throws InvalidArgumentException
     */
    public function actionCreateLocalAccount(string $id)
    {
        $user = $this->users->findOrThrow($id);

        Login::createLogin($user, $user->getEmail(), "", $this->passwordsService);
        $this->users->flush();
        $this->sendSuccessResponse($this->userViewFactory->getUser($user));
    }

    public function checkGroups(string $id)
    {
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
    public function actionGroups(string $id)
    {
        $user = $this->users->findOrThrow($id);

        $asStudent = $user->getGroupsAsStudent()->filter(
            function (Group $group) {
                return !$group->isArchived();
            }
        );

        $asSupervisor = $user->getGroupsAsSupervisor()->filter(
            function (Group $group) {
                return !$group->isArchived();
            }
        );

        $this->sendSuccessResponse(
            [
                "supervisor" => $this->groupViewFactory->getGroups($asSupervisor->getValues()),
                "student" => $this->groupViewFactory->getGroups($asStudent->getValues()),
                "stats" => $user->getGroupsAsStudent()->map(
                    function (Group $group) use ($user) {
                        return $this->groupViewFactory->getStudentsStats($group, $user);
                    }
                )->getValues(),
            ]
        );
    }

    public function checkAllGroups(string $id)
    {
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
    public function actionAllGroups(string $id)
    {
        $user = $this->users->findOrThrow($id);
        $this->sendSuccessResponse(
            [
                "supervisor" => $this->groupViewFactory->getGroups($user->getGroupsAsSupervisor()->getValues(), false),
                "student" => $this->groupViewFactory->getGroups($user->getGroupsAsStudent()->getValues(), false)
            ]
        );
    }

    public function checkInstances(string $id)
    {
        $user = $this->users->findOrThrow($id);

        if (!$this->userAcl->canViewInstances($user)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of instances where a user is registered
     * @GET
     * @param string $id Identifier of the user
     * @throws NotFoundException
     */
    public function actionInstances(string $id)
    {
        $user = $this->users->findOrThrow($id);

        $this->sendSuccessResponse($this->instanceViewFactory->getInstances($user->getInstances()->toArray()));
    }

    public function checkExercises(string $id)
    {
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
    public function actionExercises(string $id)
    {
        $user = $this->users->findOrThrow($id);
        $this->sendSuccessResponse(
            array_map([$this->exerciseViewFactory, "getExercise"], $user->getExercises()->getValues())
        );
    }

    public function checkSetRole(string $id)
    {
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
     * @Param(type="post", name="role", validation="string:1..", description="Role which should be assigned to the user")
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function actionSetRole(string $id)
    {
        $user = $this->users->findOrThrow($id);
        $role = $this->getRequest()->getPost("role");
        // validate role
        if (!$this->roles->validateRole($role)) {
            throw new InvalidArgumentException("role", "Unknown user role '$role'");
        }

        $user->setRole($role);
        $this->users->flush();
        $this->sendSuccessResponse($this->userViewFactory->getUser($user));
    }

    public function checkInvalidateTokens(string $id)
    {
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
    public function actionInvalidateTokens(string $id)
    {
        $user = $this->users->findOrThrow($id);
        $user->setTokenValidityThreshold(new DateTime());
        $this->users->flush();
        $token = $this->getAccessToken();

        $this->sendSuccessResponse(
            [
                "accessToken" => $user === $this->getCurrentUser() ? $this->accessManager->issueRefreshedToken(
                    $token
                ) : null
            ]
        );
    }

    public function checkSetAllowed(string $id)
    {
        $user = $this->users->findOrThrow($id);
        if (!$this->userAcl->canSetIsAllowed($user)) {
            throw new ForbiddenRequestException();
        }

        if ($this->getCurrentUser() === $user) {
            throw new ForbiddenRequestException("You cannot change the allow flag of your self");
        }
    }

    /**
     * Set "isAllowed" flag of the given user. The flag determines whether a user may perform any operation of the API.
     * @POST
     * @param string $id Identifier of the user
     * @Param(type="post", name="isAllowed", validation="bool", description="Whether the user is allowed (active) or not.")
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function actionSetAllowed(string $id)
    {
        $user = $this->users->findOrThrow($id);
        $isAllowed = filter_var($this->getRequest()->getPost("isAllowed"), FILTER_VALIDATE_BOOLEAN);
        $user->setIsAllowed($isAllowed);
        $this->users->flush();
        $this->sendSuccessResponse($this->userViewFactory->getUser($user));
    }
}
