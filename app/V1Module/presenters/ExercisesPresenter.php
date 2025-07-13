<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ApiException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ParseException;
use App\Exceptions\FrontendErrorMappings;
use App\Helpers\ExercisesConfig;
use App\Helpers\ExerciseConfig\Compiler;
use App\Helpers\ExerciseConfig\ExerciseConfigChecker;
use App\Helpers\ExerciseConfig\Updater;
use App\Helpers\Localizations;
use App\Helpers\Pagination;
use App\Helpers\Evaluation\ScoreCalculatorAccessor;
use App\Helpers\Validators;
use App\Helpers\Notifications\ExerciseNotificationSender;
use App\Model\Entity\Assignment;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseScoreConfig;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\ExerciseTag;
use App\Model\Entity\Pipeline;
use App\Model\Entity\LocalizedExercise;
use App\Model\Entity\User;
use App\Model\Repository\Exercises;
use App\Model\Repository\ExerciseTags;
use App\Model\Repository\Pipelines;
use App\Model\Repository\HardwareGroups;
use App\Model\Repository\Groups;
use App\Model\View\AssignmentViewFactory;
use App\Model\View\ExerciseViewFactory;
use App\Model\View\PipelineViewFactory;
use App\Model\View\UserViewFactory;
use App\Security\ACL\IAssignmentPermissions;
use App\Security\ACL\IExercisePermissions;
use App\Security\ACL\IGroupPermissions;
use App\Security\ACL\IPipelinePermissions;
use App\Security\Identity;
use App\Security\Loader;
use Nette\Utils\Arrays;
use DateTime;

/**
 * Endpoints for exercise manipulation
 * @LoggedIn
 */
class ExercisesPresenter extends BasePresenter
{
    /**
     * @var Exercises
     * @inject
     */
    public $exercises;

    /**
     * @var Pipelines
     * @inject
     */
    public $pipelines;

    /**
     * @var Groups
     * @inject
     */
    public $groups;

    /**
     * @var HardwareGroups
     * @inject
     */
    public $hardwareGroups;

    /**
     * @var IExercisePermissions
     * @inject
     */
    public $exerciseAcl;

    /**
     * @var IGroupPermissions
     * @inject
     */
    public $groupAcl;

    /**
     * @var IPipelinePermissions
     * @inject
     */
    public $pipelineAcl;

    /**
     * @var IAssignmentPermissions
     * @inject
     */
    public $assignmentAcl;

    /**
     * @var Loader
     * @inject
     */
    public $aclLoader;

    /**
     * @var ScoreCalculatorAccessor
     * @inject
     */
    public $calculators;

    /**
     * @var Updater
     * @inject
     */
    public $exerciseConfigUpdater;

    /**
     * @var ExercisesConfig
     * @inject
     */
    public $exercisesConfigParams;

    /**
     * @var ExerciseConfigChecker
     * @inject
     */
    public $configChecker;

    /**
     * @var ExerciseViewFactory
     * @inject
     */
    public $exerciseViewFactory;

    /**
     * @var UserViewFactory
     * @inject
     */
    public $userViewFactory;

    /**
     * @var PipelineViewFactory
     * @inject
     */
    public $pipelineViewFactory;

    /**
     * @var AssignmentViewFactory
     * @inject
     */
    public $assignmentViewFactory;

    /**
     * @var ExerciseTags
     * @inject
     */
    public $exerciseTags;

    /**
     * @var ExerciseNotificationSender
     * @inject
     */
    public $notificationSender;

    private function getExercisePermissionsOfUser(User $user): IExercisePermissions
    {
        return $this->aclLoader->loadACLModule(
            IExercisePermissions::class,
            $this->authorizator,
            new Identity($user, null)
        );
    }

    public function noncheckDefault()
    {
        if (!$this->exerciseAcl->canViewAll()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of all exercises matching given filters in given pagination rage.
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

    public function noncheckAuthors()
    {
        if (!$this->exerciseAcl->canViewAllAuthors()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * List authors of all exercises, possibly filtered by a group in which the exercises appear.
     * @GET
     * @param string $instanceId Id of an instance from which the authors are listed.
     * @param string|null $groupId A group where the relevant exercises can be seen (assigned).
     */
    public function actionAuthors(string $instanceId = null, string $groupId = null)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckListByIds()
    {
        if (!$this->exerciseAcl->canViewList()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of exercises based on given ids.
     * @POST
     * @Param(type="post", name="ids", validation="array", description="Identifications of exercises")
     */
    public function actionListByIds()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDetail(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canViewDetail($exercise)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get details of an exercise
     * @GET
     * @param string $id identification of exercise
     */
    public function actionDetail(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdateDetail(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You cannot update this exercise.");
        }
    }

    /**
     * Update detail of an exercise
     * @POST
     * @param string $id identification of exercise
     * @throws BadRequestException
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     * @Param(type="post", name="version", validation="numericint", description="Version of the edited exercise")
     * @Param(type="post", name="difficulty",
     *   description="Difficulty of an exercise, should be one of 'easy', 'medium' or 'hard'")
     * @Param(type="post", name="localizedTexts", validation="array", description="A description of the exercise")
     * @Param(type="post", name="isPublic", validation="bool", required=false,
     *   description="Exercise can be public or private")
     * @Param(type="post", name="isLocked", validation="bool", required=false,
     *   description="If true, the exercise cannot be assigned")
     * @Param(type="post", name="configurationType", validation="string", required=false,
     *   description="Identifies the way the evaluation of the exercise is configured")
     * @Param(type="post", name="solutionFilesLimit", validation="numericint|null",
     *   description="Maximal number of files in a solution being submitted (default for assignments)")
     * @Param(type="post", name="solutionSizeLimit", validation="numericint|null",
     *   description="Maximal size (bytes) of all files in a solution being submitted (default for assignments)")
     * @Param(type="post", name="mergeJudgeLogs", validation="bool",
     *   description="If true, judge stderr will be merged into stdout (default for assignments)")
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    public function actionUpdateDetail(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckValidate($id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You cannot modify this exercise.");
        }
    }

    /**
     * Check if the version of the exercise is up-to-date.
     * @POST
     * @Param(type="post", name="version", validation="numericint", description="Version of the exercise.")
     * @param string $id Identifier of the exercise
     */
    public function actionValidate($id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckAssignments(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);

        if (!$this->exerciseAcl->canViewAssignments($exercise)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get all non-archived assignments created from this exercise.
     * @GET
     * @param string $id Identifier of the exercise
     * @param bool $archived Include also archived groups in the result
     * @throws NotFoundException
     */
    public function actionAssignments(string $id, bool $archived = false)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Create exercise with all default values.
     * Exercise detail can be then changed in appropriate endpoint.
     * @POST
     * @Param(type="post", name="groupId", description="Identifier of the group to which exercise belongs to")
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws ApiException
     * @throws ParseException
     */
    public function actionCreate()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckHardwareGroups(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You cannot modify this exercise.");
        }
    }

    /**
     * Set hardware groups which are associated with exercise.
     * @POST
     * @param string $id identifier of exercise
     * @Param(type="post", name="hwGroups", validation="array",
     *        description="List of hardware groups identifications to which exercise belongs to")
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    public function actionHardwareGroups(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRemove(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canRemove($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to remove this exercise.");
        }
    }

    /**
     * Delete an exercise
     * @DELETE
     * @param string $id
     */
    public function actionRemove(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Fork exercise from given one into the completely new one.
     * @POST
     * @param string $id Identifier of the exercise
     * @Param(type="post", name="groupId", description="Identifier of the group to which exercise will be forked")
     * @throws ApiException
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws ParseException
     */
    public function actionForkFrom(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    // ************************************************
    // ******************** GROUPS ********************
    // ************************************************

    public function noncheckAttachGroup(string $id, string $groupId)
    {
        $exercise = $this->exercises->findOrThrow($id);
        $group = $this->groups->findOrThrow($groupId);
        if (!$this->exerciseAcl->canAttachGroup($exercise, $group)) {
            throw new ForbiddenRequestException("You are not allowed to attach the group to the exercise");
        }
    }

    /**
     * Attach exercise to group with given identification.
     * @POST
     * @param string $id Identifier of the exercise
     * @param string $groupId Identifier of the group to which exercise should be attached
     * @throws InvalidArgumentException
     */
    public function actionAttachGroup(string $id, string $groupId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDetachGroup(string $id, string $groupId)
    {
        $exercise = $this->exercises->findOrThrow($id);
        $group = $this->groups->findOrThrow($groupId);
        if (!$this->exerciseAcl->canDetachGroup($exercise, $group)) {
            throw new ForbiddenRequestException("You are not allowed to detach the group from the exercise");
        }

        if ($exercise->getGroups()->count() < 2) {
            throw new BadRequestException("You cannot detach last group from exercise");
        }
    }

    /**
     * Detach exercise from given group.
     * @DELETE
     * @param string $id Identifier of the exercise
     * @param string $groupId Identifier of the group which should be detached from exercise
     * @throws InvalidArgumentException
     */
    public function actionDetachGroup(string $id, string $groupId)
    {
        $this->sendSuccessResponse("OK");
    }

    // **********************************************
    // ******************** TAGS ********************
    // **********************************************

    public function noncheckAllTags()
    {
        if (!$this->exerciseAcl->canViewAllTags()) {
            throw new ForbiddenRequestException("You are not allowed to view all tags");
        }
    }

    /**
     * Get list of global exercise tag names which are currently registered.
     * @GET
     */
    public function actionAllTags()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckTagsStats()
    {
        if (!$this->exerciseAcl->canViewTagsStats()) {
            throw new ForbiddenRequestException("You are not allowed to view tags statistics");
        }
    }

    /**
     * Get list of global exercise tag names along with how many times they have been used.
     * @GET
     */
    public function actionTagsStats()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckTagsUpdateGlobal(string $tag)
    {
        if (!$this->exerciseAcl->canUpdateTagsGlobal()) {
            throw new ForbiddenRequestException("You are not allowed to update tags globally");
        }
    }

    /**
     * Update the tag globally. At the moment, the only 'update' function is 'rename'.
     * Other types of updates may be implemented in the future.
     * @POST
     * @param string $tag Tag to be updated
     * @Param(type="query", name="renameTo", validation="string:1..32", required=false,
     *        description="New name of the tag")
     * @Param(type="query", name="force", validation="bool", required=false,
     *        description="If true, the rename will be allowed even if the new tag name exists (tags will be merged).
     *                     Otherwise, name collisions will result in error.")
     */
    public function actionTagsUpdateGlobal(string $tag, string $renameTo = null, bool $force = false)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckTagsRemoveGlobal(string $tag)
    {
        if (!$this->exerciseAcl->canRemoveTagsGlobal()) {
            throw new ForbiddenRequestException("You are not allowed to remove tags globally");
        }
    }

    /**
     * Remove a tag from all exercises.
     * @POST
     * @param string $tag Tag to be removed
     */
    public function actionTagsRemoveGlobal(string $tag)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckAddTag(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canAddTag($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to add tag to the exercise");
        }
    }

    /**
     * Add tag with given name to the exercise.
     * @POST
     * @param string $id
     * @param string $name
     * @Param(type="query", name="name", validation="string:1..32",
     *        description="Name of the newly added tag to given exercise")
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     */
    public function actionAddTag(string $id, string $name)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRemoveTag(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canRemoveTag($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to add tag to the exercise");
        }
    }

    /**
     * Remove tag with given name from exercise.
     * @DELETE
     * @param string $id
     * @param string $name
     * @throws NotFoundException
     */
    public function actionRemoveTag(string $id, string $name)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetArchived(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canArchive($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to change the archived state of the exercise");
        }
    }

    /**
     * (Un)mark the exercise as archived. Nothing happens if the exercise is already in the requested state.
     * @POST
     * @param string $id identifier of the exercise
     * @Param(type="post", name="archived", required=true, validation=boolean,
     *        description="Whether the exercise should be marked or unmarked")
     * @throws NotFoundException
     */
    public function actionSetArchived(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetAuthor(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canChangeAuthor($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to change the author of the exercise");
        }
    }

    /**
     * Change the author of the exercise. This is a very special operation reserved for powerful users.
     * @POST
     * @param string $id identifier of the exercise
     * @Param(type="post", name="author", required=true, validation="string:36",
     *        description="Id of the new author of the exercise.")
     * @throws NotFoundException
     * @throws ForbiddenRequestException
     */
    public function actionSetAuthor(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetAdmins(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdateAdmins($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to modify admins of the exercise");
        }
    }

    /**
     * Change who the admins of an exercise are (all users on the list are added,
     * prior admins not on the list are removed).
     * @POST
     * @param string $id identifier of the exercise
     * @Param(type="post", name="admins", required=true, validation=array, description="List of user IDs.")
     * @throws NotFoundException
     */
    public function actionSetAdmins(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSendNotification(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            // who can update may also need to notify others about the change
            throw new ForbiddenRequestException("You are not allowed to notify users who assigned this exercise");
        }
    }

    /**
     * Sends an email to all group admins and supervisors, where the exercise is assigned.
     * The purpose of this is to quickly notify all relevant teachers when a bug is found
     * or the exercise is modified significantly.
     * The response is number of emails sent (number of notified users).
     * @POST
     * @param string $id identifier of the exercise
     * @Param(type="post", name="message", validation=string, description="Message sent to notified users.")
     */
    public function actionSendNotification(string $id)
    {
        $this->sendSuccessResponse("OK");
    }
}
