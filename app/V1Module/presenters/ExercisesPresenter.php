<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\ApiException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidApiArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ParseException;
use App\Exceptions\FrontendErrorMappings;
use App\Helpers\ExercisesConfig;
use App\Helpers\ExerciseConfig\Compiler;
use App\Helpers\ExerciseConfig\ExerciseConfigChecker;
use App\Helpers\ExerciseConfig\Updater;
use App\Helpers\Localizations;
use App\Helpers\Evaluation\ScoreCalculatorAccessor;
use App\Helpers\Validators;
use App\Helpers\Notifications\ExerciseNotificationSender;
use App\Model\Entity\Assignment;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseScoreConfig;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\ExerciseTag;
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

    // public function checkDefault()
    // {
    //     if (!$this->exerciseAcl->canViewAll()) {
    //         throw new ForbiddenRequestException();
    //     }
    // }

    /**
     * Get a list of all exercises matching given filters in given pagination rage.
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

    // public function checkAuthors()
    // {
    //     if (!$this->exerciseAcl->canViewAllAuthors()) {
    //         throw new ForbiddenRequestException();
    //     }
    // }

    /**
     * List authors of all exercises, possibly filtered by a group in which the exercises appear.
     * @GET
     */
    #[Query("instanceId", new VString(), "Id of an instance from which the authors are listed.", required: false)]
    #[Query(
        "groupId",
        new VString(),
        "A group where the relevant exercises can be seen (assigned).",
        required: false,
        nullable: true,
    )]
    public function actionAuthors(?string $instanceId = null, ?string $groupId = null)
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkListByIds()
    // {
    //     if (!$this->exerciseAcl->canViewList()) {
    //         throw new ForbiddenRequestException();
    //     }
    // }

    /**
     * Get a list of exercises based on given ids.
     * @POST
     */
    #[Post("ids", new VArray(), "Identifications of exercises")]
    public function actionListByIds()
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkDetail(string $id)
    // {
    //     /** @var Exercise $exercise */
    //     $exercise = $this->exercises->findOrThrow($id);
    //     if (!$this->exerciseAcl->canViewDetail($exercise)) {
    //         throw new ForbiddenRequestException();
    //     }
    // }

    /**
     * Get details of an exercise
     * @GET
     */
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    public function actionDetail(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkUpdateDetail(string $id)
    // {
    //     /** @var Exercise $exercise */
    //     $exercise = $this->exercises->findOrThrow($id);
    //     if (!$this->exerciseAcl->canUpdate($exercise)) {
    //         throw new ForbiddenRequestException("You cannot update this exercise.");
    //     }
    // }

    /**
     * Update detail of an exercise
     * @POST
     * @throws BadRequestException
     * @throws ForbiddenRequestException
     * @throws InvalidApiArgumentException
     * @throws BadRequestException
     * @throws InvalidApiArgumentException
     */
    #[Post("version", new VInt(), "Version of the edited exercise")]
    #[Post(
        "difficulty",
        new VMixed(),
        "Difficulty of an exercise, should be one of 'easy', 'medium' or 'hard'",
        nullable: true,
    )]
    #[Post("localizedTexts", new VArray(), "A description of the exercise")]
    #[Post("isPublic", new VBool(), "Exercise can be public or private", required: false)]
    #[Post("isLocked", new VBool(), "If true, the exercise cannot be assigned", required: false)]
    #[Post(
        "configurationType",
        new VString(),
        "Identifies the way the evaluation of the exercise is configured",
        required: false,
    )]
    #[Post(
        "solutionFilesLimit",
        new VInt(),
        "Maximal number of files in a solution being submitted (default for assignments)",
        nullable: true,
    )]
    #[Post(
        "solutionSizeLimit",
        new VInt(),
        "Maximal size (bytes) of all files in a solution being submitted (default for assignments)",
        nullable: true,
    )]
    #[Post("mergeJudgeLogs", new VBool(), "If true, judge stderr will be merged into stdout (default for assignments)")]
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    public function actionUpdateDetail(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkValidate($id)
    // {
    //     $exercise = $this->exercises->findOrThrow($id);
    //     if (!$this->exerciseAcl->canUpdate($exercise)) {
    //         throw new ForbiddenRequestException("You cannot modify this exercise.");
    //     }
    // }

    /**
     * Check if the version of the exercise is up-to-date.
     * @POST
     */
    #[Post("version", new VInt(), "Version of the exercise.")]
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionValidate($id)
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkAssignments(string $id)
    // {
    //     $exercise = $this->exercises->findOrThrow($id);

    //     if (!$this->exerciseAcl->canViewAssignments($exercise)) {
    //         throw new ForbiddenRequestException();
    //     }
    // }

    /**
     * Get all non-archived assignments created from this exercise.
     * @GET
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    #[Query("archived", new VBool(), "Include also archived groups in the result", required: false)]
    public function actionAssignments(string $id, bool $archived = false)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Create exercise with all default values.
     * Exercise detail can be then changed in appropriate endpoint.
     * @POST
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws ApiException
     * @throws ParseException
     */
    #[Post("groupId", new VMixed(), "Identifier of the group to which exercise belongs to", nullable: true)]
    public function actionCreate()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Set hardware groups which are associated with exercise.
     * @POST
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Post("hwGroups", new VArray(), "List of hardware groups identifications to which exercise belongs to")]
    #[Path("id", new VUuid(), "identifier of exercise", required: true)]
    public function actionHardwareGroups(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Delete an exercise
     * @DELETE
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionRemove(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Fork exercise from given one into the completely new one.
     * @POST
     * @throws ApiException
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws ParseException
     */
    #[Post("groupId", new VMixed(), "Identifier of the group to which exercise will be forked", nullable: true)]
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionForkFrom(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    // ************************************************
    // ******************** GROUPS ********************
    // ************************************************


    /**
     * Attach exercise to group with given identification.
     * @POST
     * @throws InvalidApiArgumentException
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    #[Path("groupId", new VString(), "Identifier of the group to which exercise should be attached", required: true)]
    public function actionAttachGroup(string $id, string $groupId)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Detach exercise from given group.
     * @DELETE
     * @throws InvalidApiArgumentException
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    #[Path("groupId", new VString(), "Identifier of the group which should be detached from exercise", required: true)]
    public function actionDetachGroup(string $id, string $groupId)
    {
        $this->sendSuccessResponse("OK");
    }

    // **********************************************
    // ******************** TAGS ********************
    // **********************************************

    // public function checkAllTags()
    // {
    //     if (!$this->exerciseAcl->canViewAllTags()) {
    //         throw new ForbiddenRequestException("You are not allowed to view all tags");
    //     }
    // }

    /**
     * Get list of global exercise tag names which are currently registered.
     * @GET
     */
    public function actionAllTags()
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkTagsStats()
    // {
    //     if (!$this->exerciseAcl->canViewTagsStats()) {
    //         throw new ForbiddenRequestException("You are not allowed to view tags statistics");
    //     }
    // }

    /**
     * Get list of global exercise tag names along with how many times they have been used.
     * @GET
     */
    public function actionTagsStats()
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkTagsUpdateGlobal(string $tag)
    // {
    //     if (!$this->exerciseAcl->canUpdateTagsGlobal()) {
    //         throw new ForbiddenRequestException("You are not allowed to update tags globally");
    //     }
    // }

    /**
     * Update the tag globally. At the moment, the only 'update' function is 'rename'.
     * Other types of updates may be implemented in the future.
     * @POST
     */
    #[Query("renameTo", new VString(1, 32), "New name of the tag", required: false)]
    #[Query(
        "force",
        new VBool(),
        "If true, the rename will be allowed even if the new tag name exists (tags will be merged). "
            . "Otherwise, name collisions will result in error.",
        required: false,
    )]
    #[Path("tag", new VString(), "Tag to be updated", required: true)]
    public function actionTagsUpdateGlobal(string $tag, ?string $renameTo = null, bool $force = false)
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkTagsRemoveGlobal(string $tag)
    // {
    //     if (!$this->exerciseAcl->canRemoveTagsGlobal()) {
    //         throw new ForbiddenRequestException("You are not allowed to remove tags globally");
    //     }
    // }

    /**
     * Remove a tag from all exercises.
     * @POST
     */
    #[Path("tag", new VString(), "Tag to be removed", required: true)]
    public function actionTagsRemoveGlobal(string $tag)
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkAddTag(string $id)
    // {
    //     $exercise = $this->exercises->findOrThrow($id);
    //     if (!$this->exerciseAcl->canAddTag($exercise)) {
    //         throw new ForbiddenRequestException("You are not allowed to add tag to the exercise");
    //     }
    // }

    /**
     * Add tag with given name to the exercise.
     * @POST
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws ForbiddenRequestException
     * @throws InvalidApiArgumentException
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    #[Path("name", new VString(1, 32), "Name of the newly added tag to given exercise", required: true)]
    public function actionAddTag(string $id, string $name)
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkRemoveTag(string $id)
    // {
    //     $exercise = $this->exercises->findOrThrow($id);
    //     if (!$this->exerciseAcl->canRemoveTag($exercise)) {
    //         throw new ForbiddenRequestException("You are not allowed to add tag to the exercise");
    //     }
    // }

    /**
     * Remove tag with given name from exercise.
     * @DELETE
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    #[Path("name", new VString(), required: true)]
    public function actionRemoveTag(string $id, string $name)
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkSetArchived(string $id)
    // {
    //     $exercise = $this->exercises->findOrThrow($id);
    //     if (!$this->exerciseAcl->canArchive($exercise)) {
    //         throw new ForbiddenRequestException("You are not allowed to change the archived state of the exercise");
    //     }
    // }

    /**
     * (Un)mark the exercise as archived. Nothing happens if the exercise is already in the requested state.
     * @POST
     * @throws NotFoundException
     */
    #[Post("archived", new VBool(), "Whether the exercise should be marked or unmarked", required: true)]
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionSetArchived(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkSetAuthor(string $id)
    // {
    //     $exercise = $this->exercises->findOrThrow($id);
    //     if (!$this->exerciseAcl->canChangeAuthor($exercise)) {
    //         throw new ForbiddenRequestException("You are not allowed to change the author of the exercise");
    //     }
    // }

    /**
     * Change the author of the exercise. This is a very special operation reserved for powerful users.
     * @POST
     * @throws NotFoundException
     * @throws ForbiddenRequestException
     */
    #[Post("author", new VUuid(), "Id of the new author of the exercise.", required: true)]
    #[Path("id", new VUuid(), "identifier of the exercise", required: true)]
    public function actionSetAuthor(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Change who the admins of an exercise are (all users on the list are added,
     * prior admins not on the list are removed).
     * @POST
     * @throws NotFoundException
     */
    #[Post("admins", new VArray(), "List of user IDs.", required: true)]
    #[Path("id", new VUuid(), "identifier of the exercise", required: true)]
    public function actionSetAdmins(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Sends an email to all group admins and supervisors, where the exercise is assigned.
     * The purpose of this is to quickly notify all relevant teachers when a bug is found
     * or the exercise is modified significantly.
     * The response is number of emails sent (number of notified users).
     * @POST
     */
    #[Post("message", new VString(), "Message sent to notified users.")]
    #[Path("id", new VUuid(), "identifier of the exercise", required: true)]
    public function actionSendNotification(string $id)
    {
        $this->sendSuccessResponse("OK");
    }
}
