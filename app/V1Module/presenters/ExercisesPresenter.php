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

    public function checkDefault()
    {
        if (!$this->exerciseAcl->canViewAll()) {
            throw new ForbiddenRequestException();
        }
    }

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
        $pagination = $this->getPagination(
            $offset,
            $limit,
            $locale,
            $orderBy,
            ($filters === null) ? [] : $filters,
            ['search', 'instanceId', 'groupsIds', 'authorsIds', 'tags', 'runtimeEnvironments', 'archived']
        );

        // Get all matching exercises and filter them by ACLs...
        $exercises = $this->exercises->getPreparedForPagination($pagination, $this->groups, $this->getCurrentUser());
        $exercises = array_filter(
            $exercises,
            function (Exercise $exercise) {
                return $this->exerciseAcl->canViewDetail($exercise);
            }
        );

        // Pre-slice the exercises, so only relevant part is sent to the view factory.
        $totalCount = count($exercises);
        $exercises = array_slice(
            array_values($exercises),
            $pagination->getOffset(),
            $pagination->getLimit() ? $pagination->getLimit() : null
        );

        // Format and post paginated output ...
        $exercises = array_map([$this->exerciseViewFactory, "getExercise"], array_values($exercises));
        $this->sendPaginationSuccessResponse($exercises, $pagination, false, $totalCount);
    }

    public function checkAuthors()
    {
        if (!$this->exerciseAcl->canViewAllAuthors()) {
            throw new ForbiddenRequestException();
        }
    }

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
        $authors = $this->exercises->getAuthors($instanceId, $groupId, $this->groups);
        $this->sendSuccessResponse($this->userViewFactory->getUsers($authors));
    }

    public function checkListByIds()
    {
        if (!$this->exerciseAcl->canViewList()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of exercises based on given ids.
     * @POST
     */
    #[Post("ids", new VArray(), "Identifications of exercises")]
    public function actionListByIds()
    {
        $exercises = $this->exercises->findByIds($this->getRequest()->getPost("ids"));
        $exercises = array_filter(
            $exercises,
            function (Exercise $exercise) {
                return $this->exerciseAcl->canViewDetail($exercise);
            }
        );
        $this->sendSuccessResponse(array_map([$this->exerciseViewFactory, "getExercise"], array_values($exercises)));
    }

    public function checkDetail(string $id)
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
     */
    #[Path("id", new VUuid(), "identification of exercise", required: true)]
    public function actionDetail(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);
        $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
    }

    public function checkUpdateDetail(string $id)
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
        $req = $this->getRequest();
        $difficulty = $req->getPost("difficulty");
        $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);
        $isLocked = filter_var($req->getPost("isLocked"), FILTER_VALIDATE_BOOLEAN);
        $mergeJudgeLogs = filter_var($req->getPost("mergeJudgeLogs"), FILTER_VALIDATE_BOOLEAN);

        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);

        $version = intval($req->getPost("version"));
        if ($version !== $exercise->getVersion()) {
            $v = $exercise->getVersion();
            throw new BadRequestException(
                "The exercise was edited in the meantime and the version has changed. Current version is $v.",
                FrontendErrorMappings::E400_010__ENTITY_VERSION_TOO_OLD,
                [
                    'entity' => 'exercise',
                    'id' => $id,
                    'version' => $v
                ]
            );
        }

        // make changes to the exercise
        $exercise->setDifficulty($difficulty);
        $exercise->setIsPublic($isPublic);
        $exercise->updatedNow();
        $exercise->incrementVersion();
        $exercise->setLocked($isLocked);
        $exercise->setSolutionFilesLimit($req->getPost("solutionFilesLimit"));
        $exercise->setSolutionSizeLimit($req->getPost("solutionSizeLimit"));
        $exercise->setMergeJudgeLogs($mergeJudgeLogs);

        $configurationType = $req->getPost("configurationType");
        if ($configurationType) {
            if (!Compiler::checkConfigurationType($configurationType)) {
                throw new InvalidApiArgumentException(
                    'configurationType',
                    "Invalid configuration type '{$configurationType}'"
                );
            }
            $exercise->setConfigurationType($configurationType);
        }

        // retrieve localizations and prepare some temp variables
        $localizedTexts = $req->getPost("localizedTexts");
        $localizations = [];

        // localized texts cannot be empty
        if (count($localizedTexts) == 0) {
            throw new InvalidApiArgumentException('localizedTexts', "No entry for localized texts given.");
        }

        // go through given localizations and construct database entities
        foreach ($localizedTexts as $localization) {
            if (
                !array_key_exists("locale", $localization) || !array_key_exists(
                    "name",
                    $localization
                ) || !array_key_exists("text", $localization)
            ) {
                throw new InvalidApiArgumentException('localizedTexts', "Malformed localized text entry");
            }

            $lang = $localization["locale"];

            if (array_key_exists($lang, $localizations)) {
                throw new InvalidApiArgumentException("Duplicate entry for language $lang");
            }

            // create all new localized texts
            $externalAssignmentLink = trim(Arrays::get($localization, "link", ""));
            if ($externalAssignmentLink !== "" && !Validators::isUrl($externalAssignmentLink)) {
                throw new InvalidApiArgumentException('link', "External assignment link is not a valid URL");
            }

            $localization["description"] = $localization["description"] ?? "";

            $localized = new LocalizedExercise(
                $lang,
                trim(Arrays::get($localization, "name", "")),
                trim(Arrays::get($localization, "text", "")),
                trim(Arrays::get($localization, "description", "")),
                $externalAssignmentLink ?: null
            );

            $localizations[$lang] = $localized;
        }

        // make changes to database
        Localizations::updateCollection($exercise->getLocalizedTexts(), $localizations);

        foreach ($exercise->getLocalizedTexts() as $localizedText) {
            $this->exercises->persist($localizedText, false);
        }

        $this->exercises->flush();

        $this->configChecker->check($exercise);
        $this->exercises->flush();

        $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
    }

    public function checkValidate($id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You cannot modify this exercise.");
        }
    }

    /**
     * Check if the version of the exercise is up-to-date.
     * @POST
     */
    #[Post("version", new VInt(), "Version of the exercise.")]
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionValidate($id)
    {
        $exercise = $this->exercises->findOrThrow($id);

        $req = $this->getHttpRequest();
        $version = intval($req->getPost("version"));

        $this->sendSuccessResponse(
            [
                "versionIsUpToDate" => $exercise->getVersion() === $version
            ]
        );
    }

    public function checkAssignments(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);

        if (!$this->exerciseAcl->canViewAssignments($exercise)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get all non-archived assignments created from this exercise.
     * @GET
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    #[Query("archived", new VBool(), "Include also archived groups in the result", required: false)]
    public function actionAssignments(string $id, bool $archived = false)
    {
        $exercise = $this->exercises->findOrThrow($id);

        $assignments = $exercise->getAssignments()->filter(
            function (Assignment $assignment) use ($archived) {
                return $this->assignmentAcl->canViewDetail($assignment) && $assignment->getGroup()
                    && ($archived || !$assignment->getGroup()->isArchived());
            }
        )->getValues();
        $this->sendSuccessResponse($this->assignmentViewFactory->getAssignments($assignments));
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
        $user = $this->getCurrentUser();
        $group = $this->groups->findOrThrow($this->getRequest()->getPost("groupId"));
        if (!$this->groupAcl->canCreateExercise($group)) {
            throw new ForbiddenRequestException();
        }

        // create default score configuration without tests
        $calculator = $this->calculators->getDefaultCalculator();
        $scoreConfig = new ExerciseScoreConfig($calculator->getId(), $calculator->getDefaultConfig([]));

        // create exercise and fill some predefined details
        $exercise = Exercise::create($user, $group, $scoreConfig, $this->exercisesConfigParams);
        $localizedExercise = new LocalizedExercise(
            $user->getSettings()->getDefaultLanguage(),
            "Exercise by " . $user->getName(),
            "",
            "",
            null
        );
        $this->exercises->persist($localizedExercise, false);
        $exercise->addLocalizedText($localizedExercise);

        // create and store basic exercise configuration
        $exerciseConfig = new ExerciseConfig((string)new \App\Helpers\ExerciseConfig\ExerciseConfig(), $user);
        $exercise->setExerciseConfig($exerciseConfig);

        // and finally make changes to database
        $this->exercises->persist($exercise);

        $this->configChecker->check($exercise);
        $this->exercises->flush();

        $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
    }

    public function checkHardwareGroups(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canUpdate($exercise)) {
            throw new ForbiddenRequestException("You cannot modify this exercise.");
        }
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
        $exercise = $this->exercises->findOrThrow($id);

        // load all new hardware groups
        $req = $this->getRequest();
        $groups = [];
        foreach ($req->getPost("hwGroups") as $groupId) {
            $groups[] = $this->hardwareGroups->findOrThrow($groupId);
        }

        // ... and after clearing the old ones assign new ones
        $exercise->getHardwareGroups()->clear();
        foreach ($groups as $group) {
            $exercise->addHardwareGroup($group);
        }

        // update configurations
        $this->exerciseConfigUpdater->hwGroupsUpdated($exercise, $this->getCurrentUser(), false);

        // update and return
        $exercise->updatedNow();
        $this->exercises->flush();

        // check exercise configuration
        $this->configChecker->check($exercise);
        $this->exercises->flush();
        $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
    }

    public function checkRemove(string $id)
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
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionRemove(string $id)
    {
        /** @var Exercise $exercise */
        $exercise = $this->exercises->findOrThrow($id);

        $this->exercises->remove($exercise);
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
        $user = $this->getCurrentUser();
        $forkFrom = $this->exercises->findOrThrow($id);

        $group = $this->groups->findOrThrow($this->getRequest()->getPost("groupId"));
        if (
            !$this->exerciseAcl->canFork($forkFrom) ||
            !$this->groupAcl->canCreateExercise($group)
        ) {
            throw new ForbiddenRequestException("Exercise cannot be forked");
        }

        $exercise = Exercise::forkFrom($forkFrom, $user, $group);
        $this->exercises->persist($exercise);

        $this->configChecker->check($exercise);
        $this->exercises->flush();

        $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
    }

    // ************************************************
    // ******************** GROUPS ********************
    // ************************************************

    public function checkAttachGroup(string $id, string $groupId)
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
     * @throws InvalidApiArgumentException
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    #[Path("groupId", new VString(), "Identifier of the group to which exercise should be attached", required: true)]
    public function actionAttachGroup(string $id, string $groupId)
    {
        $exercise = $this->exercises->findOrThrow($id);
        $group = $this->groups->findOrThrow($groupId);

        if ($exercise->getGroups()->contains($group)) {
            throw new InvalidApiArgumentException('groupId', "group is already attached to the exercise");
        }

        $exercise->addGroup($group);
        $this->exercises->flush();
        $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
    }

    public function checkDetachGroup(string $id, string $groupId)
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
     * @throws InvalidApiArgumentException
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    #[Path("groupId", new VString(), "Identifier of the group which should be detached from exercise", required: true)]
    public function actionDetachGroup(string $id, string $groupId)
    {
        $exercise = $this->exercises->findOrThrow($id);
        $group = $this->groups->findOrThrow($groupId);

        if (!$exercise->getGroups()->contains($group)) {
            throw new InvalidApiArgumentException('groupId', "given group is not attached to the exercise");
        }

        $exercise->removeGroup($group);
        $this->exercises->flush();
        $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
    }

    // **********************************************
    // ******************** TAGS ********************
    // **********************************************

    public function checkAllTags()
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
        $tags = $this->exerciseTags->findAllDistinctNames();
        $this->sendSuccessResponse($tags);
    }

    public function checkTagsStats()
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
        $stats = $this->exerciseTags->getStatistics();
        $this->sendSuccessResponse($stats);
    }

    public function checkTagsUpdateGlobal(string $tag)
    {
        if (!$this->exerciseAcl->canUpdateTagsGlobal()) {
            throw new ForbiddenRequestException("You are not allowed to update tags globally");
        }
    }

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
        // Check whether at least one modification action is present (so far, we have only renameTo)
        if ($renameTo === null) {
            throw new BadRequestException("Nothing to update.");
        }

        if (!$this->exerciseTags->verifyTagName($renameTo)) {
            throw new InvalidApiArgumentException('renameTo', "tag name contains illicit characters");
        }

        if (!$force && $this->exerciseTags->tagExists($renameTo)) {
            throw new InvalidApiArgumentException(
                'renameTo',
                "new tag name collides with existing name (use force to override)"
            );
        }

        $renameCount = $this->exerciseTags->renameTag($tag, $renameTo);

        $this->sendSuccessResponse(
            [
                'tag' => $renameTo,
                'oldName' => $tag,
                'count' => $renameCount,
            ]
        );
    }

    public function checkTagsRemoveGlobal(string $tag)
    {
        if (!$this->exerciseAcl->canRemoveTagsGlobal()) {
            throw new ForbiddenRequestException("You are not allowed to remove tags globally");
        }
    }

    /**
     * Remove a tag from all exercises.
     * @POST
     */
    #[Path("tag", new VString(), "Tag to be removed", required: true)]
    public function actionTagsRemoveGlobal(string $tag)
    {
        $removeCount = $this->exerciseTags->removeTag($tag);
        $this->sendSuccessResponse(
            [
                'tag' => $tag,
                'count' => $removeCount,
            ]
        );
    }

    public function checkAddTag(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canAddTag($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to add tag to the exercise");
        }
    }

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
        if (!$this->exerciseTags->verifyTagName($name)) {
            throw new InvalidApiArgumentException('name', "tag name contains illicit characters");
        }

        $exercise = $this->exercises->findOrThrow($id);
        $tag = $this->exerciseTags->findByNameAndExercise($name, $exercise);
        if ($tag !== null) {
            throw new BadRequestException("Tag already exists on exercise");
        }

        $tag = new ExerciseTag($name, $this->getCurrentUser(), $exercise);
        $exercise->addTag($tag);
        $this->exercises->flush();
        $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
    }

    public function checkRemoveTag(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canRemoveTag($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to add tag to the exercise");
        }
    }

    /**
     * Remove tag with given name from exercise.
     * @DELETE
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    #[Path("name", new VString(), required: true)]
    public function actionRemoveTag(string $id, string $name)
    {
        $exercise = $this->exercises->findOrThrow($id);
        $tag = $this->exerciseTags->findByNameAndExercise($name, $exercise);
        if ($tag === null) {
            throw new NotFoundException("Tag '{$name}' was not found");
        }

        $exercise->removeTag($tag);
        $this->exercises->flush();
        $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
    }

    public function checkSetArchived(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canArchive($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to change the archived state of the exercise");
        }
    }

    /**
     * (Un)mark the exercise as archived. Nothing happens if the exercise is already in the requested state.
     * @POST
     * @throws NotFoundException
     */
    #[Post("archived", new VBool(), "Whether the exercise should be marked or unmarked", required: true)]
    #[Path("id", new VUuid(), "Identifier of the exercise", required: true)]
    public function actionSetArchived(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        $req = $this->getRequest();
        $archived = filter_var($req->getPost("archived"), FILTER_VALIDATE_BOOLEAN);

        if ($exercise->isArchived() !== $archived) {
            $exercise->setArchivedAt($archived ? new DateTime() : null);
            $this->exercises->persist($exercise);
        }

        $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
    }

    public function checkSetAuthor(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        if (!$this->exerciseAcl->canChangeAuthor($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to change the author of the exercise");
        }
    }

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
        $exercise = $this->exercises->findOrThrow($id);
        $newAuthorId = $this->getRequest()->getPost("author");

        if ($exercise->getAuthor()->getId() !== $newAuthorId) {
            $newAuthor = $this->users->findOrThrow($newAuthorId);
            if (!$this->getExercisePermissionsOfUser($newAuthor)->canCreate()) {
                throw new ForbiddenRequestException("Given user is not allowed to be an author of an exercise.");
            }

            $exercise->setAuthor($newAuthor);
            if ($exercise->getAdmins()->contains($newAuthor)) {
                $exercise->removeAdmin($newAuthor); // author is promoted from admins
            }
            $this->exercises->persist($exercise);
        }

        $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
    }

    public function checkSetAdmins(string $id)
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
     * @throws NotFoundException
     */
    #[Post("admins", new VArray(), "List of user IDs.", required: true)]
    #[Path("id", new VUuid(), "identifier of the exercise", required: true)]
    public function actionSetAdmins(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);

        // prepare a list of new admins
        $newAdmins = [];
        foreach ($this->getRequest()->getPost("admins") as $id) {
            $user = $this->users->findOrThrow($id);
            if (!$this->getExercisePermissionsOfUser($user)->canCreate()) {
                throw new ForbiddenRequestException("Given user is not allowed to be administrator of an exercise.");
            }
            if ($id === $exercise->getAuthor()->getId()) {
                throw new ForbiddenRequestException("Given user is already the author (cannot become also and admin).");
            }
            $newAdmins[$id] = $user;
        }

        // create a list of admins to be removed and prune current admins from newAdmins
        $toRemove = [];
        foreach ($exercise->getAdmins() as $admin) {
            if (!array_key_exists($admin->getId(), $newAdmins)) {
                $toRemove[] = $admin; // not on the new list, needs to be removed
            } else {
                unset($newAdmins[$admin->getId()]); // no need to add, already present
            }
        }

        // update the exercise
        foreach ($toRemove as $admin) {
            $exercise->removeAdmin($admin);
        }
        foreach ($newAdmins as $newAdmin) {
            $exercise->addAdmin($newAdmin);
        }
        if ($toRemove || $newAdmins) {
            $this->exercises->persist($exercise);
        }

        $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
    }

    public function checkSendNotification(string $id)
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
     */
    #[Post("message", new VString(), "Message sent to notified users.")]
    #[Path("id", new VUuid(), "identifier of the exercise", required: true)]
    public function actionSendNotification(string $id)
    {
        $exercise = $this->exercises->findOrThrow($id);
        $message = trim($this->getRequest()->getPost("message"));
        $notified = $this->notificationSender->sendNotification($exercise, $this->getCurrentUser(), $message);
        $this->sendSuccessResponse($notified);
    }
}
