<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\InvalidStateException;
use App\Exceptions\NotFoundException;
use App\Exceptions\FrontendErrorMappings;
use App\Helpers\EvaluationPointsLoader;
use App\Helpers\Localizations;
use App\Helpers\Notifications\AssignmentEmailsSender;
use App\Helpers\Validators;
use App\Helpers\AssignmentRestrictionsConfig;
use App\Helpers\ExerciseConfig\Loader as ExerciseConfigLoader;
use App\Helpers\Evaluation\ScoreCalculatorAccessor;
use App\Helpers\FileStorageManager;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Assignment;
use App\Model\Entity\LocalizedAssignment;
use App\Model\Entity\LocalizedExercise;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Repository\Assignments;
use App\Model\Repository\AsyncJobs;
use App\Model\Repository\Exercises;
use App\Model\Repository\Groups;
use App\Model\Repository\RuntimeEnvironments;
use App\Model\Repository\SolutionEvaluations;
use App\Model\Repository\AssignmentSolutions;
use App\Model\View\AssignmentSolutionViewFactory;
use App\Model\View\AssignmentViewFactory;
use App\Security\ACL\IAssignmentPermissions;
use App\Security\ACL\IGroupPermissions;
use App\Security\ACL\IAssignmentSolutionPermissions;
use App\Security\ACL\IExercisePermissions;
use App\Async\Dispatcher;
use App\Async\Handler\AssignmentNotificationJobHandler;
use DateTime;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;

/**
 * Endpoints for exercise assignment manipulation
 * @LoggedIn
 */
class AssignmentsPresenter extends BasePresenter
{
    /**
     * @var AsyncJobs
     * @inject
     */
    public $asyncJobs;

    /**
     * @var Dispatcher
     * @inject
     */
    public $dispatcher;

    /**
     * @var FileStorageManager
     * @inject
     */
    public $fileStorage;

    /**
     * @var Exercises
     * @inject
     */
    public $exercises;

    /**
     * @var Groups
     * @inject
     */
    public $groups;

    /**
     * @var Assignments
     * @inject
     */
    public $assignments;

    /**
     * @var AssignmentSolutions
     * @inject
     */
    public $assignmentSolutions;

    /**
     * @var AssignmentViewFactory
     * @inject
     */
    public $assignmentViewFactory;

    /**
     * @var AssignmentSolutionViewFactory
     * @inject
     */
    public $assignmentSolutionViewFactory;

    /**
     * @var SolutionEvaluations
     * @inject
     */
    public $solutionEvaluations;

    /**
     * @var IAssignmentPermissions
     * @inject
     */
    public $assignmentAcl;

    /**
     * @var IGroupPermissions
     * @inject
     */
    public $groupAcl;

    /**
     * @var IExercisePermissions
     * @inject
     */
    public $exerciseAcl;

    /**
     * @var IAssignmentSolutionPermissions
     * @inject
     */
    public $assignmentSolutionAcl;

    /**
     * @var ExerciseConfigLoader
     * @inject
     */
    public $exerciseConfigLoader;

    /**
     * @var AssignmentEmailsSender
     * @inject
     */
    public $assignmentEmailsSender;

    /**
     * @var EvaluationPointsLoader
     * @inject
     */
    public $evaluationPointsLoader;

    /**
     * @var ScoreCalculatorAccessor
     * @inject
     */
    public $calculators;

    /**
     * @var RuntimeEnvironments
     * @inject
     */
    public $runtimeEnvironments;

    /**
     * @var AssignmentRestrictionsConfig
     * @inject
     */
    public $restrictionsConfig;

    public function checkDetail(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        if (!$this->assignmentAcl->canViewDetail($assignment)) {
            throw new ForbiddenRequestException("You cannot view this assignment.");
        }
    }

    /**
     * Get details of an assignment
     * @GET
     * @param string $id Identifier of the assignment
     */
    public function actionDetail(string $id)
    {
        $this->sendSuccessResponse($this->assignmentViewFactory->getAssignment($this->assignments->findOrThrow($id)));
    }

    public function checkUpdateDetail(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        if (!$this->assignmentAcl->canUpdate($assignment)) {
            throw new ForbiddenRequestException("You cannot update this assignment.");
        }
    }

    /**
     * Update details of an assignment
     * @POST
     * @Param(type="post", name="version", validation="numericint", description="Version of the edited assignment")
     * @Param(type="post", name="isPublic", validation="bool",
     *        description="Is the assignment ready to be displayed to students?")
     * @Param(type="post", name="localizedTexts", validation="array", description="A description of the assignment")
     * @Param(type="post", name="firstDeadline", validation="timestamp",
     *        description="First deadline for submission of the assignment")
     * @Param(type="post", name="maxPointsBeforeFirstDeadline", validation="numericint",
     *        description="A maximum of points that can be awarded for a submission before first deadline")
     * @Param(type="post", name="submissionsCountLimit", validation="numericint",
     *        description="A maximum amount of submissions by a student for the assignment")
     * @Param(type="post", name="solutionFilesLimit", validation="numericint|null",
     *        description="Maximal number of files in a solution being submitted")
     * @Param(type="post", name="solutionSizeLimit", validation="numericint|null",
     *        description="Maximal size (bytes) of all files in a solution being submitted")
     * @Param(type="post", name="allowSecondDeadline", validation="bool",
     *        description="Should there be a second deadline for students who didn't make the first one?")
     * @Param(type="post", name="visibleFrom", validation="timestamp", required=false,
     *        description="Date from which this assignment will be visible to students")
     * @Param(type="post", name="canViewLimitRatios", validation="bool",
     *        description="Can all users view ratio of theirs solution memory and time usages and assignment limits?")
     * @Param(type="post", name="canViewMeasuredValues", validation="bool",
     *        description="Can all users view absolute memory and time values?")
     * @Param(type="post", name="canViewJudgeStdout", validation="bool",
     *        description="Can all users view judge primary log (stdout) of theirs solution?")
     * @Param(type="post", name="canViewJudgeStderr", validation="bool",
     *        description="Can all users view judge secondary log (stderr) of theirs solution?")
     * @Param(type="post", name="secondDeadline", validation="timestamp", required=false,
     *        description="A second deadline for submission of the assignment (with different point award)")
     * @Param(type="post", name="maxPointsBeforeSecondDeadline", validation="numericint", required=false,
     *        description="A maximum of points that can be awarded for a late submission")
     * @Param(type="post", name="maxPointsDeadlineInterpolation", validation="bool",
     *        description="Use linear interpolation for max. points between 1st and 2nd deadline")
     * @Param(type="post", name="isBonus", validation="bool",
     *        description="If true, points from this exercise will not be included in overall score of group")
     * @Param(type="post", name="pointsPercentualThreshold", validation="numeric", required=false,
     *        description="A minimum percentage of points needed to gain point from assignment")
     * @Param(type="post", name="disabledRuntimeEnvironmentIds", validation="list", required=false,
     *        description="Identifiers of runtime environments that should not be used for student submissions")
     * @Param(type="post", name="sendNotification", required=false, validation="bool",
     *        description="If email notification (when assignment becomes public) should be sent")
     * @Param(type="post", name="isExam", required=false, validation="bool",
     *        description="This assignemnt is dedicated for an exam (should be solved in exam mode)")
     * @param string $id Identifier of the updated assignment
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function actionUpdateDetail(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);

        $req = $this->getRequest();
        $version = (int)$req->getPost("version");
        if ($version !== $assignment->getVersion()) {
            $newVer = $assignment->getVersion();
            throw new BadRequestException(
                "The assignment was edited in the meantime and the version has changed. Current version is $newVer.",
                FrontendErrorMappings::E400_010__ENTITY_VERSION_TOO_OLD,
                [
                    'entity' => 'assignment',
                    'id' => $id,
                    'version' => $newVer
                ]
            );
        }

        // localized texts cannot be empty
        if (count($req->getPost("localizedTexts")) == 0) {
            throw new InvalidArgumentException("No entry for localized texts given.");
        }

        if ($this->isRequestJson()) {
            $disabledRuntimeIds = $req->getPost("disabledRuntimeEnvironmentIds");
            if ($disabledRuntimeIds !== null) {
                $disabledRuntimes = array_map([$this->runtimeEnvironments, "findOrThrow"], $disabledRuntimeIds);
                $assignment->setDisabledRuntimeEnvironments($disabledRuntimes);
            }
        }

        // Validate numeric inputs for reasonable ranges (and create variables with their values)
        $validRanges = [
            'submissionsCountLimit' => [1, $this->restrictionsConfig->getSubmissionsCountMetaLimit()],
            'pointsPercentualThreshold' => [0, 100],  // percent limits need not be configured
            'maxPointsBeforeFirstDeadline' => [0, $this->restrictionsConfig->getMaxPointsLimit()],
            'maxPointsBeforeSecondDeadline' => [0, $this->restrictionsConfig->getMaxPointsLimit()],
        ];
        foreach ($validRanges as $name => list($min, $max)) {
            if ($req->getPost($name) !== null) {
                $value = (int)$req->getPost($name);
                if ($value < $min || $value > $max) {
                    throw new InvalidArgumentException("Attribute '$name' value $value is out of range [$min,$max].");
                }
            }
        }

        // old values of some attributes
        $wasPublic = $assignment->isPublic();
        $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);
        $oldFirstDeadlinePoints = $assignment->getMaxPointsBeforeFirstDeadline();
        $firstDeadlinePoints = $req->getPost("maxPointsBeforeFirstDeadline");
        $oldSecondDeadlinePoints = $assignment->getMaxPointsBeforeSecondDeadline();
        $secondDeadlinePoints = $req->getPost("maxPointsBeforeSecondDeadline") ?: 0;
        $oldThreshold = $assignment->getPointsPercentualThreshold();
        $threshold = $req->getPost("pointsPercentualThreshold") !== null
            ? round((float)$req->getPost("pointsPercentualThreshold"), 2) / 100.0
            : $assignment->getPointsPercentualThreshold();
        $oldFirstDeadlineTimestamp = $assignment->getFirstDeadline()->getTimestamp();
        $firstDeadlineTimestamp = (int)$req->getPost("firstDeadline");
        $oldSecondDeadlineTimestamp = $assignment->getSecondDeadline()->getTimestamp();
        $allowSecondDeadline = filter_var($req->getPost("allowSecondDeadline"), FILTER_VALIDATE_BOOLEAN);
        $secondDeadlineTimestamp = (int)$req->getPost("secondDeadline") ?: 0;
        $oldVisibleFrom = $assignment->getVisibleFrom();
        $oldVisibleFromTimestamp = $oldVisibleFrom ? $oldVisibleFrom->getTimestamp() : null;
        $visibleFromTimestamp = (int)$req->getPost("visibleFrom");
        $visibleFrom = $visibleFromTimestamp ? DateTime::createFromFormat('U', $visibleFromTimestamp) : null;
        $maxPointsDeadlineInterpolation = filter_var(
            $req->getPost("maxPointsDeadlineInterpolation"),
            FILTER_VALIDATE_BOOLEAN
        );
        $oldMaxPointsDeadlineInterpolation = $assignment->getMaxPointsDeadlineInterpolation();
        $isExam = filter_var($req->getPost("isExam"), FILTER_VALIDATE_BOOLEAN);

        $sendNotification = $req->getPost("sendNotification");
        $sendNotification = $sendNotification !== null ? filter_var($sendNotification, FILTER_VALIDATE_BOOLEAN) : true;
        $sendNotification = $sendNotification && !$isExam; // exam assignments don't send notifications

        // basic constrain checks and sanitizations
        if (!$allowSecondDeadline) {
            $secondDeadlineTimestamp = 0;
        }
        if ($secondDeadlineTimestamp === 0) {
            $allowSecondDeadline = false;
        }
        if (!$allowSecondDeadline) {
            $maxPointsDeadlineInterpolation = false;
        }
        if ($allowSecondDeadline && $firstDeadlineTimestamp >= $secondDeadlineTimestamp) {
            throw new InvalidArgumentException(
                "When the second deadline is allowed, it must be after the first deadline."
            );
        }

        $assignment->incrementVersion();
        $assignment->updatedNow();
        $assignment->setIsPublic($isPublic);
        $assignment->setFirstDeadline(DateTime::createFromFormat('U', $firstDeadlineTimestamp));
        $assignment->setSecondDeadline(DateTime::createFromFormat('U', $secondDeadlineTimestamp));
        $assignment->setMaxPointsBeforeFirstDeadline($firstDeadlinePoints);
        $assignment->setMaxPointsBeforeSecondDeadline($secondDeadlinePoints);
        $assignment->setVisibleFrom($visibleFrom);
        $assignment->setSubmissionsCountLimit($req->getPost("submissionsCountLimit"));
        $assignment->setAllowSecondDeadline($allowSecondDeadline);
        $assignment->setMaxPointsDeadlineInterpolation($maxPointsDeadlineInterpolation);
        $assignment->setCanViewLimitRatios(filter_var($req->getPost("canViewLimitRatios"), FILTER_VALIDATE_BOOLEAN));
        $assignment->setCanViewMeasuredValues(filter_var(
            $req->getPost("canViewMeasuredValues"),
            FILTER_VALIDATE_BOOLEAN
        ));
        $assignment->setCanViewJudgeStdout(filter_var($req->getPost("canViewJudgeStdout"), FILTER_VALIDATE_BOOLEAN));
        $assignment->setCanViewJudgeStderr(filter_var($req->getPost("canViewJudgeStderr"), FILTER_VALIDATE_BOOLEAN));
        $assignment->setIsBonus(filter_var($req->getPost("isBonus"), FILTER_VALIDATE_BOOLEAN));
        $assignment->setPointsPercentualThreshold($threshold);
        $assignment->setSolutionFilesLimit($req->getPost("solutionFilesLimit"));
        $assignment->setSolutionSizeLimit($req->getPost("solutionSizeLimit"));
        $assignment->setExam($isExam);

        // if points, deadline or threshold were changed
        // go through all submissions and recalculate points
        if (
            $oldFirstDeadlinePoints != $firstDeadlinePoints ||
            $oldSecondDeadlinePoints != $secondDeadlinePoints ||
            $oldThreshold != $threshold ||
            $oldFirstDeadlineTimestamp !== $firstDeadlineTimestamp ||
            $oldSecondDeadlineTimestamp !== $secondDeadlineTimestamp ||
            $oldMaxPointsDeadlineInterpolation !== $maxPointsDeadlineInterpolation
        ) {
            foreach ($assignment->getAssignmentSolutions() as $solution) {
                foreach ($solution->getSubmissions() as $submission) {
                    $this->evaluationPointsLoader->setStudentPoints($submission);
                }
            }
            $this->solutionEvaluations->flush();
        }

        // go through localizedTexts and construct database entities
        $localizedTexts = [];
        $localizedAssignments = [];
        foreach ($req->getPost("localizedTexts") as $localization) {
            $lang = $localization["locale"];

            if (array_key_exists($lang, $localizedTexts)) {
                throw new InvalidArgumentException("Duplicate entry for language $lang in localizedTexts");
            }

            // create all new localized texts
            $assignmentExercise = $assignment->getExercise();
            $localizedExercise = $assignmentExercise ? $assignmentExercise->getLocalizedTextByLocale($lang) : null;
            $externalAssignmentLink = trim(Arrays::get($localization, "link", ""));
            if ($externalAssignmentLink !== "" && !Validators::isUrl($externalAssignmentLink)) {
                throw new InvalidArgumentException("External assignment link is not a valid URL");
            }

            $localizedTexts[$lang] = new LocalizedExercise(
                $lang,
                trim(Arrays::get($localization, "name", "")),
                trim(Arrays::get($localization, "text", "")),
                $localizedExercise ? $localizedExercise->getDescription() : "",
                $externalAssignmentLink ?: null
            );

            if (array_key_exists("studentHint", $localization)) {
                $localizedAssignments[$lang] = new LocalizedAssignment(
                    $lang,
                    trim(Arrays::get($localization, "studentHint", ""))
                );
            }
        }

        // make changes to database
        Localizations::updateCollection($assignment->getLocalizedTexts(), $localizedTexts);

        foreach ($assignment->getLocalizedTexts() as $localizedText) {
            $this->assignments->persist($localizedText, false);
        }

        Localizations::updateCollection($assignment->getLocalizedAssignments(), $localizedAssignments);

        foreach ($assignment->getLocalizedAssignments() as $localizedAssignment) {
            $this->assignments->persist($localizedAssignment, false);
        }

        // stop scheduled notification from happening
        $asyncJobs = $this->asyncJobs->findPendingJobs(AssignmentNotificationJobHandler::ID, true, null, $assignment);
        $notificationAlreadySent = false; // true if we were unable to prevent notification from sending
        if ($asyncJobs) {
            if (count($asyncJobs) > 1) {
                throw new InvalidStateException("Too many scheduled notification jobs.");
            }
            $notificationAlreadySent = !$this->dispatcher->unschedule($asyncJobs[0]); // true = unscheduled
        }

        // sending notification has to be after setting new localized texts
        if ($isPublic && $sendNotification && !$notificationAlreadySent) {
            // mail notification sent to students was requested
            $now = new DateTime();
            $wasPublic = $wasPublic && ($oldVisibleFrom === null || $oldVisibleFrom <= $now);

            if ($wasPublic === false && ($visibleFrom === null || $visibleFrom <= $now)) {
                // assignment is visible now, send notification to students immediately
                $this->assignmentEmailsSender->assignmentCreated($assignment);
            } elseif ($visibleFrom !== null && $visibleFrom > $now) {
                // assignment is public, but will be visible in the future, schedule a notification for later
                AssignmentNotificationJobHandler::scheduleAsyncJob(
                    $this->dispatcher,
                    $this->getCurrentUser(),
                    $assignment
                );
            }
        }

        $this->assignments->flush();
        $this->sendSuccessResponse($this->assignmentViewFactory->getAssignment($assignment));
    }

    public function checkValidate(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);

        if (!$this->assignmentAcl->canUpdate($assignment)) {
            throw new ForbiddenRequestException("You cannot access this assignment.");
        }
    }

    /**
     * Check if the version of the assignment is up-to-date.
     * @POST
     * @Param(type="post", name="version", validation="numericint", description="Version of the assignment.")
     * @param string $id Identifier of the assignment
     * @throws ForbiddenRequestException
     */
    public function actionValidate($id)
    {
        $assignment = $this->assignments->findOrThrow($id);

        $req = $this->getHttpRequest();
        $version = intval($req->getPost("version"));

        $this->sendSuccessResponse(
            [
                "versionIsUpToDate" => $assignment->getVersion() === $version
            ]
        );
    }

    /**
     * Assign an exercise to a group
     * @POST
     * @Param(type="post", name="exerciseId", description="Identifier of the exercise")
     * @Param(type="post", name="groupId", description="Identifier of the group")
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     * @throws InvalidStateException
     * @throws NotFoundException
     */
    public function actionCreate()
    {
        $req = $this->getRequest();
        $exerciseId = $req->getPost("exerciseId");
        $groupId = $req->getPost("groupId");

        $group = $this->groups->findOrThrow($groupId);
        $exercise = $this->exercises->findOrThrow($exerciseId);

        if (!$this->exerciseAcl->canAssign($exercise)) {
            throw new ForbiddenRequestException("You are not allowed to assign exercise '$exerciseId'.");
        }

        if (!$this->groupAcl->canAssignExercise($group)) {
            throw new ForbiddenRequestException("You are not allowed to assign exercises to group '$groupId'.");
        }

        if ($group->isOrganizational()) {
            throw new BadRequestException("You cannot assign exercises in organizational groups");
        }

        if ($exercise->isLocked()) {
            throw new BadRequestException("Exercise '$exerciseId' is locked");
        }

        if ($exercise->isBroken()) {
            throw new BadRequestException(
                "Exercise '$exerciseId' is broken. If you are the author, check its configuration."
            );
        }

        if ($exercise->getReferenceSolutions(ReferenceExerciseSolution::VISIBILITY_PRIVATE)->isEmpty()) {
            throw new BadRequestException("Exercise '$exerciseId' does not have any reference solutions");
        }

        // validate score configuration
        $calculator = $this->calculators->getCalculator($exercise->getScoreConfig()->getCalculator());
        if (
            !$calculator->isScoreConfigValid(
                $exercise->getScoreConfig()->getConfigParsed(),
                $exercise->getExerciseTestsNames()
            )
        ) {
            throw new BadRequestException("Exercise '$exerciseId' does not have valid score configuration");
        }

        // create an assignment for the group based on the given exercise but without any params
        // and make sure the assignment is not public yet - the supervisor must edit it first
        if ($group->hasExamPeriodSet()) {
            $deadline = $group->getExamEnd(); // when exam is set, sync the deadlines
        } else {
            $deadline = new DateTime();
            $deadline->modify("+2 weeks")->modify('tomorrow')->modify("-1 minute");
        }

        $assignment = Assignment::assignToGroup($exercise, $group, false, $deadline);
        if ($group->isExam() || ($group->hasExamPeriodSet() && $group->getExamBegin() <= (new DateTime()))) {
            // assigned to exam group, or group with pending exam term
            $assignment->setExam();
        }

        $this->assignments->persist($assignment);
        $this->sendSuccessResponse($this->assignmentViewFactory->getAssignment($assignment));
    }

    public function checkRemove(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);

        if (!$this->assignmentAcl->canRemove($assignment)) {
            throw new ForbiddenRequestException("You cannot remove this assignment.");
        }
    }

    /**
     * Delete an assignment
     * @DELETE
     * @param string $id Identifier of the assignment to be removed
     */
    public function actionRemove(string $id)
    {
        $this->assignments->remove($this->assignments->findOrThrow($id));
        $this->sendSuccessResponse("OK");
    }

    public function checkSyncWithExercise(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        if (!$this->assignmentAcl->canUpdate($assignment)) {
            throw new ForbiddenRequestException("You cannot sync this assignment.");
        }
    }

    /**
     * Update the assignment so that it matches with the current version of the exercise (limits, texts, etc.)
     * @param string $id Identifier of the assignment that should be synchronized
     * @POST
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function actionSyncWithExercise($id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        $exercise = $assignment->getExercise();

        if ($exercise === null) {
            throw new NotFoundException("Exercise for assignment '{$id}' was deleted");
        }

        if ($exercise->isBroken()) {
            throw new BadRequestException(
                "Exercise '{$exercise->getId()}' is broken. If you are the author, check its configuration."
            );
        }

        $assignment->updatedNow();
        $assignment->syncWithExercise();
        $this->assignments->flush();
        $this->sendSuccessResponse($this->assignmentViewFactory->getAssignment($assignment));
    }

    public function checkSolutions(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        if (!$this->assignmentAcl->canViewAssignmentSolutions($assignment)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of solutions of all users for the assignment
     * @GET
     * @param string $id Identifier of the assignment
     * @throws NotFoundException
     */
    public function actionSolutions(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);

        $allSolutions = $assignment->getAssignmentSolutions()->getValues();
        $bestSolutions = $this->assignmentSolutions->filterBestSolutions($allSolutions);
        $solutions = array_filter(
            $allSolutions,
            function (AssignmentSolution $solution) {
                return $this->assignmentSolutionAcl->canViewDetail($solution);
            }
        );

        $this->sendSuccessResponse(
            $this->assignmentSolutionViewFactory->getSolutionsData($solutions, $bestSolutions)
        );
    }

    public function checkUserSolutions(string $id, string $userId)
    {
        $assignment = $this->assignments->findOrThrow($id);
        $user = $this->users->findOrThrow($userId);

        if (!$this->assignmentAcl->canViewSubmissions($assignment, $user)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of solutions created by a user of an assignment
     * @GET
     * @param string $id Identifier of the assignment
     * @param string $userId Identifier of the user
     */
    public function actionUserSolutions(string $id, string $userId)
    {
        $assignment = $this->assignments->findOrThrow($id);
        $user = $this->users->findOrThrow($userId);

        $allSolutions = $this->assignmentSolutions->findSolutions($assignment, $user);
        $bestSolutions = $this->assignmentSolutions->filterBestSolutions($allSolutions);
        $solutions = array_filter(
            $allSolutions,
            function (AssignmentSolution $solution) {
                return $this->assignmentSolutionAcl->canViewDetail($solution);
            }
        );

        $this->sendSuccessResponse(
            $this->assignmentSolutionViewFactory->getSolutionsData($solutions, $bestSolutions)
        );
    }

    public function checkBestSolution(string $id, string $userId)
    {
        $assignment = $this->assignments->findOrThrow($id);
        $user = $this->users->findOrThrow($userId);
        $solution = $this->assignmentSolutions->findBestSolution($assignment, $user);

        if ($solution === null) {
            return;
        }

        if (
            !$this->assignmentAcl->canViewSubmissions($assignment, $user) ||
            !$this->assignmentSolutionAcl->canViewDetail($solution)
        ) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get the best solution by a user to an assignment
     * @GET
     * @param string $id Identifier of the assignment
     * @param string $userId Identifier of the user
     * @throws ForbiddenRequestException
     */
    public function actionBestSolution(string $id, string $userId)
    {
        $assignment = $this->assignments->findOrThrow($id);
        $user = $this->users->findOrThrow($userId);
        $solution = $this->assignmentSolutions->findBestSolution($assignment, $user);

        if ($solution == null) {
            $this->sendSuccessResponse(null);
        }

        $this->sendSuccessResponse(
            $this->assignmentSolutionViewFactory->getSolutionData($solution, true)  // true = isBestSolution hint
        );
    }

    public function checkBestSolutions(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        if (!$this->assignmentAcl->canViewDetail($assignment)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get the best solutions to an assignment for all students in group.
     * @GET
     * @param string $id Identifier of the assignment
     * @throws NotFoundException
     */
    public function actionBestSolutions(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        $bestSubmissions = [];

        if ($assignment->getGroup() === null) {
            throw new NotFoundException("Group for assignment '$id' was deleted");
        }

        foreach ($assignment->getGroup()->getStudents() as $student) {
            $solution = $this->assignmentSolutions->findBestSolution($assignment, $student);
            if ($solution === null) {
                $bestSubmissions[$student->getId()] = null;
                continue;
            }

            if (
                !$this->assignmentAcl->canViewSubmissions($assignment, $student) ||
                !$this->assignmentSolutionAcl->canViewDetail($solution)
            ) {
                continue;
            }

            $bestSubmissions[$student->getId()] =
                $this->assignmentSolutionViewFactory->getSolutionData($solution, true);  // true = isBestSolution hint
        }

        $this->sendSuccessResponse($bestSubmissions);
    }

    public function checkDownloadBestSolutionsArchive(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        if (!$this->assignmentAcl->canViewDetail($assignment)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Download the best solutions of an assignment for all students in group.
     * @GET
     * @param string $id Identifier of the assignment
     * @throws NotFoundException
     * @throws \Nette\Application\AbortException
     * @throws \Nette\Application\BadRequestException
     */
    public function actionDownloadBestSolutionsArchive(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        $files = [];

        if ($assignment->getGroup() === null) {
            throw new NotFoundException("Group for assignment '$id' was deleted");
        }

        foreach ($assignment->getGroup()->getStudents() as $student) {
            $solution = $this->assignmentSolutions->findBestSolution($assignment, $student);
            if ($solution === null) {
                continue;
            }

            if (
                !$this->assignmentAcl->canViewSubmissions($assignment, $student) ||
                !$this->assignmentSolutionAcl->canViewDetail($solution)
            ) {
                continue;
            }

            $studentDir = Strings::webalize("{$student->getLastName()}_{$student->getFirstName()}_{$student->getId()}");
            foreach ($solution->getSolution()->getFiles() as $file) {
                $files[$studentDir . "/" . $file->getName()] = $file->getFile($this->fileStorage);
            }
        }

        $this->sendZipFilesResponse($files, "assignment-{$id}.zip");
    }
}
