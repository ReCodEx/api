<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VDouble;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\FrontendErrorMappings;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\WrongCredentialsException;
use App\Model\Entity\ExternalLogin;
use App\Model\Entity\Group;
use App\Model\Entity\Login;
use App\Model\Entity\SecurityEvent;
use App\Model\Entity\User;
use App\Model\Entity\UserUiData;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Logins;
use App\Model\Repository\SecurityEvents;
use App\Exceptions\BadRequestException;
use App\Helpers\EmailVerificationHelper;
use App\Helpers\AnonymizationHelper;
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
     * @var ExternalLogins
     * @inject
     */
    public $externalLogins;

    /**
     * @var SecurityEvents
     * @inject
     */
    public $securityEvents;

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
     */
    #[Query("offset", new VInt(), "Index of the first result.", required: false)]
    #[Query("limit", new VInt(), "Maximal number of results returned.", required: false)]
    #[Query(
        "orderBy",
        new VString(),
        "Name of the column (column concept). The '!' prefix indicate descending order.",
        required: false,
    )]
    #[Query("filters", new VArray(), "Named filters that prune the result.", required: false)]
    #[Query(
        "locale",
        new VString(),
        "Currently set locale (used to augment order by clause if necessary),",
        required: false,
    )]
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
     */
    #[Post("ids", new VArray(), "Identifications of users")]
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
     */
    #[Path("id", new VString(), "Identifier of the user", required: true)]
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
     * @throws ForbiddenRequestException
     */
    #[Path("id", new VString(), "Identifier of the user", required: true)]
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
     * @throws BadRequestException
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     * @throws WrongCredentialsException
     * @throws NotFoundException
     */
    #[Post("firstName", new VString(2), "First name", required: false)]
    #[Post("lastName", new VString(2), "Last name", required: false)]
    #[Post("titlesBeforeName", new VMixed(), "Titles before name", required: false, nullable: true)]
    #[Post("titlesAfterName", new VMixed(), "Titles after name", required: false, nullable: true)]
    #[Post("email", new VEmail(), "New email address", required: false)]
    #[Post("oldPassword", new VString(1), "Old password of current user", required: false)]
    #[Post("password", new VString(1), "New password of current user", required: false)]
    #[Post("passwordConfirm", new VString(1), "Confirmation of new password of current user", required: false)]
    #[Post("gravatarUrlEnabled", new VBool(), "Enable or disable gravatar profile image", required: false)]
    #[Path("id", new VString(), "Identifier of the user", required: true)]
    public function actionUpdateProfile(string $id)
    {
        $req = $this->getRequest();

        // fill user with provided data
        $user = $this->users->findOrThrow($id);
        $login = $user->getLogin();

        // change details in separate methods
        $this->changeUserEmail($user, $req->getPost("email"));
        $this->changePersonalData(
            $user,
            $req->getPost("titlesBeforeName"),
            $req->getPost("firstName"),
            $req->getPost("lastName"),
            $req->getPost("titlesAfterName")
        );
        $passwordChanged = $this->changeUserPassword(
            $login,
            $req->getPost("oldPassword"),
            $req->getPost("password"),
            $req->getPost("passwordConfirm")
        );

        $gravatarUrlEnabled = $req->getPost("gravatarUrlEnabled");
        if ($gravatarUrlEnabled !== null) {  // null or missing value -> no update
            $user->setGravatar(filter_var($gravatarUrlEnabled, FILTER_VALIDATE_BOOLEAN));
        }

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
        $email = trim($email ?? "");
        if (strlen($email) === 0) {
            return;
        }

        // check if there is not another user using provided email
        $userEmail = $this->users->getByEmail($email);
        if ($userEmail !== null && $userEmail->getId() !== $user->getId()) {
            throw new BadRequestException("This email address is already taken.");
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('email', "Provided email is not in correct format");
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
     * @param null|string $titlesBefore
     * @param null|string $firstname
     * @param null|string $lastname
     * @param null|string $titlesAfter
     * @throws ForbiddenRequestException
     */
    private function changePersonalData(
        User $user,
        ?string $titlesBefore,
        ?string $firstname,
        ?string $lastname,
        ?string $titlesAfter
    ) {
        if (
            ($titlesBefore !== null || $firstname !== null || $lastname !== null || $titlesAfter !== null) &&
            !$this->userAcl->canUpdatePersonalData($user)
        ) {
            throw new ForbiddenRequestException("You cannot update personal data");
        }

        if ($titlesBefore !== null) {
            $user->setTitlesBeforeName(trim($titlesBefore));
        }

        if ($firstname && trim($firstname)) {
            $user->setFirstName(trim($firstname));
        }

        if ($lastname && trim($lastname)) {
            $user->setLastName(trim($lastname));
        }

        if ($titlesAfter !== null) {
            $user->setTitlesAfterName(trim($titlesAfter));
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
            throw new InvalidArgumentException('password|passwordConfirm', "New password was not provided");
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

            $event = SecurityEvent::createChangePasswoedEvent(
                $this->getHttpRequest()->getRemoteAddress(),
                $login->getUser()
            );
            $this->securityEvents->persist($event);
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
     * @throws NotFoundException
     */
    #[Post("defaultLanguage", new VString(), "Default language of UI", required: false)]
    #[Post(
        "newAssignmentEmails",
        new VBool(),
        "Flag if email should be sent to user when new assignment was created",
        required: false,
    )]
    #[Post(
        "assignmentDeadlineEmails",
        new VBool(),
        "Flag if email should be sent to user if assignment deadline is nearby",
        required: false,
    )]
    #[Post(
        "submissionEvaluatedEmails",
        new VBool(),
        "Flag if email should be sent to user when resubmission was evaluated",
        required: false,
    )]
    #[Post(
        "solutionCommentsEmails",
        new VBool(),
        "Flag if email should be sent to user when new submission comment is added",
        required: false,
    )]
    #[Post(
        "solutionReviewsEmails",
        new VBool(),
        "Flag enabling review-related email notifications sent to the author of the solution",
        required: false,
    )]
    #[Post(
        "pointsChangedEmails",
        new VBool(),
        "Flag if email should be sent to user when the points were awarded for assignment",
        required: false,
    )]
    #[Post(
        "assignmentSubmitAfterAcceptedEmails",
        new VBool(),
        "Flag if email should be sent to group supervisor if a student submits new solution for already accepted assignment",
        required: false,
    )]
    #[Post(
        "assignmentSubmitAfterReviewedEmails",
        new VBool(),
        "Flag if email should be sent to group supervisor if a student submits new solution for already reviewed and not accepted assignment",
        required: false,
    )]
    #[Post(
        "exerciseNotificationEmails",
        new VBool(),
        "Flag if notifications sent by authors of exercises should be sent via email.",
        required: false,
    )]
    #[Post(
        "solutionAcceptedEmails",
        new VBool(),
        "Flag if notification should be sent to a student when solution accepted flag is changed.",
        required: false,
    )]
    #[Post(
        "solutionReviewRequestedEmails",
        new VBool(),
        "Flag if notification should be send to a teacher when a solution reviewRequested flag is chagned in a supervised/admined group.",
        required: false,
    )]
    #[Path("id", new VString(), "Identifier of the user", required: true)]
    public function actionUpdateSettings(string $id)
    {
        $req = $this->getRequest();
        $user = $this->users->findOrThrow($id);
        $settings = $user->getSettings();

        // handle boolean flags
        $knownBoolFlags = [
            "newAssignmentEmails",
            "assignmentDeadlineEmails",
            "submissionEvaluatedEmails",
            "solutionCommentsEmails",
            "solutionReviewsEmails",
            "assignmentCommentsEmails",
            "pointsChangedEmails",
            "assignmentSubmitAfterAcceptedEmails",
            "assignmentSubmitAfterReviewedEmails",
            "exerciseNotificationEmails",
            "solutionAcceptedEmails",
            "solutionReviewRequestedEmails",
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
     * @throws NotFoundException
     */
    #[Post("uiData", new VArray(), "Structured user-specific UI data", nullable: true)]
    #[Post(
        "overwrite",
        new VBool(),
        "Flag indicating that uiData should be overwritten completelly (instead of regular merge)",
        required: false,
    )]
    #[Path("id", new VString(), "Identifier of the user", required: true)]
    public function actionUpdateUiData(string $id)
    {
        $req = $this->getRequest();
        $user = $this->users->findOrThrow($id);
        $uiData = $user->getUiData();

        $overwrite = filter_var($req->getPost("overwrite"), FILTER_VALIDATE_BOOLEAN);
        $newUiData = $req->getPost("uiData");
        if (!$newUiData && !$overwrite) {
            // nothing will change
            $this->sendSuccessResponse($this->userViewFactory->getUser($user));
            return;
        }

        if (!$newUiData && ($overwrite || $uiData)) {
            $user->setUiData(null); // ui data are being erased
        } else {
            if (!$overwrite && $uiData) {
                $newUiData = array_merge($uiData->getData(), $newUiData);
            }

            if (!$uiData) {
                $uiData = new UserUiData($newUiData);
                $user->setUiData($uiData);
            } else {
                $uiData->setData($newUiData);
            }
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
     * @throws InvalidArgumentException
     */
    #[Path("id", new VString(), required: true)]
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
     */
    #[Path("id", new VString(), "Identifier of the user", required: true)]
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
     */
    #[Path("id", new VString(), "Identifier of the user", required: true)]
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
     * @throws NotFoundException
     */
    #[Path("id", new VString(), "Identifier of the user", required: true)]
    public function actionInstances(string $id)
    {
        $user = $this->users->findOrThrow($id);

        $this->sendSuccessResponse($this->instanceViewFactory->getInstances(
            $user->getInstances()->toArray(),
            $this->getCurrentUser()
        ));
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
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    #[Post("role", new VString(1), "Role which should be assigned to the user")]
    #[Path("id", new VString(), "Identifier of the user", required: true)]
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
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Path("id", new VString(), "Identifier of the user", required: true)]
    public function actionInvalidateTokens(string $id)
    {
        $user = $this->users->findOrThrow($id);
        $user->setTokenValidityThreshold(new DateTime());
        $this->users->flush();

        $event = SecurityEvent::createInvalidateTokensEvent($this->getHttpRequest()->getRemoteAddress(), $user);
        $this->securityEvents->persist($event);

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
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    #[Post("isAllowed", new VBool(), "Whether the user is allowed (active) or not.")]
    #[Path("id", new VString(), "Identifier of the user", required: true)]
    public function actionSetAllowed(string $id)
    {
        $user = $this->users->findOrThrow($id);
        $isAllowed = filter_var($this->getRequest()->getPost("isAllowed"), FILTER_VALIDATE_BOOLEAN);
        $user->setIsAllowed($isAllowed);
        $this->users->flush();
        $this->sendSuccessResponse($this->userViewFactory->getUser($user));
    }

    public function checkUpdateExternalLogin(string $id, string $service)
    {
        $user = $this->users->findOrThrow($id);
        if (!$this->userAcl->canSetExternalIds($user)) {
            throw new ForbiddenRequestException();
        }

        // in the future, we might consider cross-checking the service ID
    }

    /**
     * Add or update existing external ID of given authentication service.
     * @POST
     * @throws InvalidArgumentException
     */
    #[Post("externalId", new VString(1, 128))]
    #[Path("id", new VString(), "identifier of the user", required: true)]
    #[Path("service", new VString(), "identifier of the authentication service (login type)", required: true)]
    public function actionUpdateExternalLogin(string $id, string $service)
    {
        $user = $this->users->findOrThrow($id);

        // make sure the external ID is not used for another user
        $externalId = $this->getRequest()->getPost("externalId");
        $anotherUser = $this->externalLogins->getUser($service, $externalId);
        if ($anotherUser) {
            if ($anotherUser->getId() !== $id) {
                // oopsie, this external ID is alreay used for a different user
                throw new InvalidArgumentException('externalId', "This ID is already used by another user.");
            }
            // otherwise the external ID is already set to this user, so there is nothing to change...
        } else {
            // create/update external login entry
            $login = $this->externalLogins->findByUser($user, $service);
            if ($login) {
                $login->setExternalId($externalId);
            } else {
                $login = new ExternalLogin($user, $service, $externalId);
            }

            $this->externalLogins->persist($login);
            $this->users->refresh($user);
        }

        $this->sendSuccessResponse($this->userViewFactory->getUser($user));
    }

    public function checkRemoveExternalLogin(string $id, string $service)
    {
        $user = $this->users->findOrThrow($id);
        if (!$this->userAcl->canSetExternalIds($user)) {
            throw new ForbiddenRequestException();
        }

        // in the future, we might consider cross-checking the service ID
    }

    /**
     * Remove external ID of given authentication service.
     * @DELETE
     */
    #[Path("id", new VString(), "identifier of the user", required: true)]
    #[Path("service", new VString(), "identifier of the authentication service (login type)", required: true)]
    public function actionRemoveExternalLogin(string $id, string $service)
    {
        $user = $this->users->findOrThrow($id);
        $login = $this->externalLogins->findByUser($user, $service);
        if ($login) {
            $this->externalLogins->remove($login);
            $this->users->refresh($user);
        }
        $this->sendSuccessResponse($this->userViewFactory->getUser($user));
    }
}
