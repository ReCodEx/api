<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VDouble;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidApiArgumentException;
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
     */
    #[Path("id", new VString(), "Identifier of the assignment", required: true)]
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
     * @throws BadRequestException
     * @throws InvalidApiArgumentException
     * @throws NotFoundException
     */
    #[Post("version", new VInt(), "Version of the edited assignment")]
    #[Post("isPublic", new VBool(), "Is the assignment ready to be displayed to students?")]
    #[Post("localizedTexts", new VArray(), "A description of the assignment")]
    #[Post("firstDeadline", new VTimestamp(), "First deadline for submission of the assignment")]
    #[Post(
        "maxPointsBeforeFirstDeadline",
        new VInt(),
        "A maximum of points that can be awarded for a submission before first deadline",
    )]
    #[Post("submissionsCountLimit", new VInt(), "A maximum amount of submissions by a student for the assignment")]
    #[Post("solutionFilesLimit", new VInt(), "Maximal number of files in a solution being submitted", nullable: true)]
    #[Post(
        "solutionSizeLimit",
        new VInt(),
        "Maximal size (bytes) of all files in a solution being submitted",
        nullable: true,
    )]
    #[Post(
        "allowSecondDeadline",
        new VBool(),
        "Should there be a second deadline for students who didn't make the first one?",
    )]
    #[Post(
        "visibleFrom",
        new VTimestamp(),
        "Date from which this assignment will be visible to students",
        required: false,
    )]
    #[Post(
        "canViewLimitRatios",
        new VBool(),
        "Can all users view ratio of theirs solution memory and time usages and assignment limits?",
    )]
    #[Post("canViewMeasuredValues", new VBool(), "Can all users view absolute memory and time values?")]
    #[Post("canViewJudgeStdout", new VBool(), "Can all users view judge primary log (stdout) of theirs solution?")]
    #[Post("canViewJudgeStderr", new VBool(), "Can all users view judge secondary log (stderr) of theirs solution?")]
    #[Post(
        "secondDeadline",
        new VTimestamp(),
        "A second deadline for submission of the assignment (with different point award)",
        required: false,
    )]
    #[Post(
        "maxPointsBeforeSecondDeadline",
        new VInt(),
        "A maximum of points that can be awarded for a late submission",
        required: false,
    )]
    #[Post(
        "maxPointsDeadlineInterpolation",
        new VBool(),
        "Use linear interpolation for max. points between 1st and 2nd deadline",
    )]
    #[Post("isBonus", new VBool(), "If true, points from this exercise will not be included in overall score of group")]
    #[Post(
        "pointsPercentualThreshold",
        new VDouble(),
        "A minimum percentage of points needed to gain point from assignment",
        required: false,
    )]
    #[Post(
        "disabledRuntimeEnvironmentIds",
        new VArray(),
        "Identifiers of runtime environments that should not be used for student submissions",
        required: false,
    )]
    #[Post(
        "sendNotification",
        new VBool(),
        "If email notification (when assignment becomes public) should be sent",
        required: false,
    )]
    #[Post(
        "isExam",
        new VBool(),
        "This assignemnt is dedicated for an exam (should be solved in exam mode)",
        required: false,
    )]
    #[Path("id", new VString(), "Identifier of the updated assignment", required: true)]
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
            throw new InvalidApiArgumentException('localizedTexts', "No entry for localized texts given.");
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
                    throw new InvalidApiArgumentException($name, "Value $value is out of range [$min,$max].");
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
            throw new InvalidApiArgumentException(
                'secondDeadline',
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
                throw new InvalidApiArgumentException('localizedTexts', "Duplicate entry for language '$lang'");
            }

            // create all new localized texts
            $assignmentExercise = $assignment->getExercise();
            $localizedExercise = $assignmentExercise ? $assignmentExercise->getLocalizedTextByLocale($lang) : null;
            $externalAssignmentLink = trim(Arrays::get($localization, "link", ""));
            if ($externalAssignmentLink !== "" && !Validators::isUrl($externalAssignmentLink)) {
                throw new InvalidApiArgumentException('link', "External assignment link is not a valid URL");
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
     * @throws ForbiddenRequestException
     */
    #[Post("version", new VInt(), "Version of the assignment.")]
    #[Path("id", new VString(), "Identifier of the assignment", required: true)]
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
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     * @throws InvalidStateException
     * @throws NotFoundException
     */
    #[Post("exerciseId", new VMixed(), "Identifier of the exercise", nullable: true)]
    #[Post("groupId", new VMixed(), "Identifier of the group", nullable: true)]
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
     */
    #[Path("id", new VString(), "Identifier of the assignment to be removed", required: true)]
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
     * @POST
     * @throws BadRequestException
     * @throws NotFoundException
     */
    #[Path("id", new VString(), "Identifier of the assignment that should be synchronized", required: true)]
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
     * @throws NotFoundException
     */
    #[Path("id", new VString(), "Identifier of the assignment", required: true)]
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
     */
    #[Path("id", new VString(), "Identifier of the assignment", required: true)]
    #[Path("userId", new VString(), "Identifier of the user", required: true)]
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
     * @throws ForbiddenRequestException
     */
    #[Path("id", new VString(), "Identifier of the assignment", required: true)]
    #[Path("userId", new VString(), "Identifier of the user", required: true)]
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
     * @throws NotFoundException
     */
    #[Path("id", new VString(), "Identifier of the assignment", required: true)]
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
     * @throws NotFoundException
     * @throws \Nette\Application\AbortException
     * @throws \Nette\Application\BadRequestException
     */
    #[Path("id", new VString(), "Identifier of the assignment", required: true)]
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
