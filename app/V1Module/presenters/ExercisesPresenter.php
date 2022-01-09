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
use App\Model\Entity\Assignment;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseScoreConfig;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\ExerciseTag;
use App\Model\Entity\Pipeline;
use App\Model\Entity\LocalizedExercise;
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
use Nette\Utils\Arrays;

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
            ['search', 'instanceId', 'groupsIds', 'authorsIds', 'tags', 'runtimeEnvironments']
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
     * @param string $instanceId Id of an instance from which the authors are listed.
     * @param string|null $groupId A group where the relevant exercises can be seen (assigned).
     */
    public function actionAuthors(string $instanceId = null, string $groupId = null)
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
     * @Param(type="post", name="ids", validation="array", description="Identifications of exercises")
     */
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
     * @param string $id identification of exercise
     */
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
     *   description="Initial value for assignments. If true, judge stderr will be merged into stdout.")
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
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
                throw new InvalidArgumentException("Invalid configuration type '{$configurationType}'");
            }
            $exercise->setConfigurationType($configurationType);
        }

        // retrieve localizations and prepare some temp variables
        $localizedTexts = $req->getPost("localizedTexts");
        $localizations = [];

        // localized texts cannot be empty
        if (count($localizedTexts) == 0) {
            throw new InvalidArgumentException("No entry for localized texts given.");
        }

        // go through given localizations and construct database entities
        foreach ($localizedTexts as $localization) {
            if (
                !array_key_exists("locale", $localization) || !array_key_exists(
                    "name",
                    $localization
                ) || !array_key_exists("text", $localization)
            ) {
                throw new InvalidArgumentException("Malformed localized text entry");
            }

            $lang = $localization["locale"];

            if (array_key_exists($lang, $localizations)) {
                throw new InvalidArgumentException("Duplicate entry for language $lang");
            }

            // create all new localized texts
            $externalAssignmentLink = trim(Arrays::get($localization, "link", ""));
            if ($externalAssignmentLink !== "" && !Validators::isUrl($externalAssignmentLink)) {
                throw new InvalidArgumentException("External assignment link is not a valid URL");
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
     * @Param(type="post", name="version", validation="numericint", description="Version of the exercise.")
     * @param string $id Identifier of the exercise
     */
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
     * @param string $id Identifier of the exercise
     * @param bool $archived Include also archived groups in the result
     * @throws NotFoundException
     */
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
     * @Param(type="post", name="groupId", description="Identifier of the group to which exercise belongs to")
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws ApiException
     * @throws ParseException
     */
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
     * @param string $id identifier of exercise
     * @Param(type="post", name="hwGroups", validation="array",
     *        description="List of hardware groups identifications to which exercise belongs to")
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
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
     * @param string $id
     */
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
     * @param string $id Identifier of the exercise
     * @Param(type="post", name="groupId", description="Identifier of the group to which exercise will be forked")
     * @throws ApiException
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws ParseException
     */
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
     * @param string $id Identifier of the exercise
     * @param string $groupId Identifier of the group to which exercise should be attached
     * @throws InvalidArgumentException
     */
    public function actionAttachGroup(string $id, string $groupId)
    {
        $exercise = $this->exercises->findOrThrow($id);
        $group = $this->groups->findOrThrow($groupId);

        if ($exercise->getGroups()->contains($group)) {
            throw new InvalidArgumentException("groupId", "group is already attached to the exercise");
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
     * @param string $id Identifier of the exercise
     * @param string $groupId Identifier of the group which should be detached from exercise
     * @throws InvalidArgumentException
     */
    public function actionDetachGroup(string $id, string $groupId)
    {
        $exercise = $this->exercises->findOrThrow($id);
        $group = $this->groups->findOrThrow($groupId);

        if (!$exercise->getGroups()->contains($group)) {
            throw new InvalidArgumentException("groupId", "given group is not attached to the exercise");
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
     * @param string $tag Tag to be updated
     * @Param(type="query", name="renameTo", validation="string:1..32", required=false,
     *        description="New name of the tag")
     * @Param(type="query", name="force", validation="bool", required=false,
     *        description="If true, the rename will be allowed even if the new tag name exists (tags will be merged).
     *                     Otherwise, name collisions will result in error.")
     */
    public function actionTagsUpdateGlobal(string $tag, string $renameTo = null, bool $force = false)
    {
        // Check whether at least one modification action is present (so far, we have only renameTo)
        if ($renameTo === null) {
            throw new BadRequestException("Nothing to update.");
        }

        if (!$this->exerciseTags->verifyTagName($renameTo)) {
            throw new InvalidArgumentException("renameTo", "tag name contains illicit characters");
        }

        if (!$force && $this->exerciseTags->tagExists($renameTo)) {
            throw new InvalidArgumentException(
                "renameTo",
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
     * @param string $tag Tag to be removed
     */
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
        if (!$this->exerciseTags->verifyTagName($name)) {
            throw new InvalidArgumentException("name", "tag name contains illicit characters");
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
     * @param string $id
     * @param string $name
     * @throws NotFoundException
     */
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
}
