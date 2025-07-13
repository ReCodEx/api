<?php

namespace App\V1Module\Presenters;

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
     * @Param(type="post", name="ids", validation="array", description="Identifications of users")
     */
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
     * @param string $id Identifier of the user
     */
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
     * @param string $id Identifier of the user
     * @throws ForbiddenRequestException
     */
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
     * @param string $id Identifier of the user
     * @throws BadRequestException
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     * @Param(type="post", name="firstName", required=false, validation="string:2..", description="First name")
     * @Param(type="post", name="lastName", required=false, validation="string:2..", description="Last name")
     * @Param(type="post", name="titlesBeforeName", required=false, description="Titles before name")
     * @Param(type="post", name="titlesAfterName", required=false, description="Titles after name")
     * @Param(type="post", name="email", validation="email", description="New email address", required=false)
     * @Param(type="post", name="oldPassword", required=false, validation="string:1..",
     *        description="Old password of current user")
     * @Param(type="post", name="password", required=false, validation="string:1..",
     *        description="New password of current user")
     * @Param(type="post", name="passwordConfirm", required=false, validation="string:1..",
     *        description="Confirmation of new password of current user")
     * @Param(type="post", name="gravatarUrlEnabled", validation="bool", required=false,
     *        description="Enable or disable gravatar profile image")
     * @throws WrongCredentialsException
     * @throws NotFoundException
     */
    public function actionUpdateProfile(string $id)
    {
        $this->sendSuccessResponse("OK");
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

        // noncheck if there is not another user using provided email
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
     * Change firstname and second name and noncheck if user can change them.
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
            // noncheck if new passwords match each other
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
     * @param string $id Identifier of the user
     * @Param(type="post", name="defaultLanguage", validation="string", required=false,
     *        description="Default language of UI")
     * @Param(type="post", name="newAssignmentEmails", validation="bool", required=false,
     *        description="Flag if email should be sent to user when new assignment was created")
     * @Param(type="post", name="assignmentDeadlineEmails", validation="bool", required=false,
     *        description="Flag if email should be sent to user if assignment deadline is nearby")
     * @Param(type="post", name="submissionEvaluatedEmails", validation="bool", required=false,
     *        description="Flag if email should be sent to user when resubmission was evaluated")
     * @Param(type="post", name="solutionCommentsEmails", validation="bool", required=false,
     *        description="Flag if email should be sent to user when new submission comment is added")
     * @Param(type="post", name="solutionReviewsEmails", validation="bool", required=false,
     *        description="Flag enabling review-related email notifications sent to the author of the solution")
     * @Param(type="post", name="pointsChangedEmails", validation="bool", required=false,
     *        description="Flag if email should be sent to user when the points were awarded for assignment")
     * @Param(type="post", name="assignmentSubmitAfterAcceptedEmails", validation="bool", required=false,
     *        description="Flag if email should be sent to group supervisor if a student submits new solution
     *                     for already accepted assignment")
     * @Param(type="post", name="assignmentSubmitAfterReviewedEmails", validation="bool", required=false,
     *        description="Flag if email should be sent to group supervisor if a student submits new solution
     *                     for already reviewed and not accepted assignment")
     * @Param(type="post", name="exerciseNotificationEmails", validation="bool", required=false,
     *        description="Flag if notifications sent by authors of exercises should be sent via email.")
     * @Param(type="post", name="solutionAcceptedEmails", validation="bool", required=false,
     *        description="Flag if notification should be sent to a student when solution accepted flag is changed.")
     * @Param(type="post", name="solutionReviewRequestedEmails", validation="bool", required=false,
     *        description="Flag if notification should be send to a teacher when a solution reviewRequested flag
     *                      is chagned in a supervised/admined group.")
     * @throws NotFoundException
     */
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
     * @param string $id Identifier of the user
     * @Param(type="post", name="uiData", validation="array|null", description="Structured user-specific UI data")
     * @Param(type="post", name="overwrite", validation="bool", required=false,
     *        description="Flag indicating that uiData should be overwritten completelly (instead of regular merge)")
     * @throws NotFoundException
     */
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
     * @param string $id
     * @throws InvalidArgumentException
     */
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
     * @param string $id Identifier of the user
     */
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
     * @param string $id Identifier of the user
     */
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
     * @param string $id Identifier of the user
     * @throws NotFoundException
     */
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
     * @param string $id Identifier of the user
     * @Param(type="post", name="role", validation="string:1..",
     *        description="Role which should be assigned to the user")
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
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
     * @param string $id Identifier of the user
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
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
     * @param string $id Identifier of the user
     * @Param(type="post", name="isAllowed", validation="bool",
     *        description="Whether the user is allowed (active) or not.")
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
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
     * @param string $id identifier of the user
     * @param string $service identifier of the authentication service (login type)
     * @Param(type="post", name="externalId", validation="string:1..128")
     * @throws InvalidArgumentException
     */
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
     * @param string $id identifier of the user
     * @param string $service identifier of the authentication service (login type)
     */
    public function actionRemoveExternalLogin(string $id, string $service)
    {
        $this->sendSuccessResponse("OK");
    }
}
