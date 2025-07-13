<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\FrontendErrorMappings;
use App\Exceptions\InvalidApiArgumentException;
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


    public function noncheckDefault()
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
    #[Query("limit", new VInt(), "Maximal number of results returned.", required: false, nullable: true)]
    #[Query(
        "orderBy",
        new VString(),
        "Name of the column (column concept). The '!' prefix indicate descending order.",
        required: false,
        nullable: true,
    )]
    #[Query("filters", new VArray(), "Named filters that prune the result.", required: false, nullable: true)]
    #[Query(
        "locale",
        new VString(),
        "Currently set locale (used to augment order by clause if necessary),",
        required: false,
        nullable: true,
    )]
    public function actionDefault(
        int $offset = 0,
        ?int $limit = null,
        ?string $orderBy = null,
        ?array $filters = null,
        ?string $locale = null
    ) {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckListByIds()
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDetail(string $id)
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
    #[Path("id", new VUuid(), "Identifier of the user", required: true)]
    public function actionDetail(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDelete(string $id)
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
    #[Path("id", new VUuid(), "Identifier of the user", required: true)]
    public function actionDelete(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdateProfile(string $id)
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
     * @throws InvalidApiArgumentException
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
    #[Path("id", new VUuid(), "Identifier of the user", required: true)]
    public function actionUpdateProfile(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Change email if any given of the provided user.
     * @param User $user
     * @param null|string $email
     * @throws BadRequestException
     * @throws InvalidApiArgumentException
     */
    private function changeUserEmail(User $user, ?string $email)
    {
        $email = trim($email ?? "");
        if (strlen($email) === 0) {
            return;
        }

        // noncheck if there is not another user using provided email
        $userEmail = $this->users->getByEmail($email);
        if ($userEmail !== null && $userEmail->getId() !== $user->getId()) {
            throw new BadRequestException("This email address is already taken.");
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidApiArgumentException('email', "Provided email is not in correct format");
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
     * Change first name and last name and noncheck if user can change them.
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
     * @throws InvalidApiArgumentException
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
            throw new InvalidApiArgumentException('password|passwordConfirm', "New password was not provided");
        }

        // passwords need to be handled differently
        if (
            $login->passwordsMatchOrEmpty($oldPassword, $this->passwordsService) ||
            (!$oldPassword && $this->userAcl->canForceChangePassword($login->getUser()))
        ) {
            // noncheck if new passwords match each other
            if ($password !== $passwordConfirm) {
                throw new WrongCredentialsException(
                    "Provided passwords do not match",
                    FrontendErrorMappings::E400_102__WRONG_CREDENTIALS_PASSWORDS_NOT_MATCH
                );
            }

            $login->changePassword($password, $this->passwordsService);
            $login->getUser()->setTokenValidityThreshold(new DateTime());

            $event = SecurityEvent::createChangePasswordEvent(
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

    public function noncheckUpdateSettings(string $id)
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
        "Flag if email should be sent to the group supervisor if a student submits new solution "
            . "for already accepted assignment",
        required: false,
    )]
    #[Post(
        "assignmentSubmitAfterReviewedEmails",
        new VBool(),
        "Flag if email should be sent to group supervisor if a student submits new solution "
            . "for already reviewed and not accepted assignment",
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
        "Flag if notification should be send to a teacher when a solution reviewRequested flag is changed "
            . "in a supervised/admin-ed group.",
        required: false,
    )]
    #[Path("id", new VUuid(), "Identifier of the user", required: true)]
    public function actionUpdateSettings(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdateUiData(string $id)
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
        "Flag indicating that uiData should be overwritten completely (instead of regular merge)",
        required: false,
    )]
    #[Path("id", new VUuid(), "Identifier of the user", required: true)]
    public function actionUpdateUiData(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckCreateLocalAccount(string $id)
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
     * @throws InvalidApiArgumentException
     */
    #[Path("id", new VUuid(), "Identifier of the user", required: true)]
    public function actionCreateLocalAccount(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckGroups(string $id)
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
    #[Path("id", new VUuid(), "Identifier of the user", required: true)]
    public function actionGroups(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckAllGroups(string $id)
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
    #[Path("id", new VUuid(), "Identifier of the user", required: true)]
    public function actionAllGroups(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckInstances(string $id)
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
    #[Path("id", new VUuid(), "Identifier of the user", required: true)]
    public function actionInstances(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetRole(string $id)
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
     * @throws InvalidApiArgumentException
     * @throws NotFoundException
     */
    #[Post("role", new VString(1), "Role which should be assigned to the user")]
    #[Path("id", new VUuid(), "Identifier of the user", required: true)]
    public function actionSetRole(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckInvalidateTokens(string $id)
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
    #[Path("id", new VUuid(), "Identifier of the user", required: true)]
    public function actionInvalidateTokens(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetAllowed(string $id)
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
     * @throws InvalidApiArgumentException
     * @throws NotFoundException
     */
    #[Post("isAllowed", new VBool(), "Whether the user is allowed (active) or not.")]
    #[Path("id", new VUuid(), "Identifier of the user", required: true)]
    public function actionSetAllowed(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdateExternalLogin(string $id, string $service)
    {
        $user = $this->users->findOrThrow($id);
        if (!$this->userAcl->canSetExternalIds($user)) {
            throw new ForbiddenRequestException();
        }

        // in the future, we might consider cross-nonchecking the service ID
    }

    /**
     * Add or update existing external ID of given authentication service.
     * @POST
     * @throws InvalidApiArgumentException
     */
    #[Post("externalId", new VString(1, 128))]
    #[Path("id", new VUuid(), "identifier of the user", required: true)]
    #[Path("service", new VString(), "identifier of the authentication service (login type)", required: true)]
    public function actionUpdateExternalLogin(string $id, string $service)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRemoveExternalLogin(string $id, string $service)
    {
        $user = $this->users->findOrThrow($id);
        if (!$this->userAcl->canSetExternalIds($user)) {
            throw new ForbiddenRequestException();
        }

        // in the future, we might consider cross-nonchecking the service ID
    }

    /**
     * Remove external ID of given authentication service.
     * @DELETE
     */
    #[Path("id", new VUuid(), "identifier of the user", required: true)]
    #[Path("service", new VString(), "identifier of the authentication service (login type)", required: true)]
    public function actionRemoveExternalLogin(string $id, string $service)
    {
        $this->sendSuccessResponse("OK");
    }
}
