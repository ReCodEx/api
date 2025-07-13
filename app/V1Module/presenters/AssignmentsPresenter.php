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
use App\Helpers\MetaFormats\Validators\VUuid;
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


    /**
     * Get details of an assignment
     * @GET
     */
    #[Path("id", new VUuid(), "Identifier of the assignment", required: true)]
    public function actionDetail(string $id)
    {
        $this->sendSuccessResponse("OK");
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
        "This assignment is dedicated for an exam (should be solved in exam mode)",
        required: false,
    )]
    #[Path("id", new VUuid(), "Identifier of the updated assignment", required: true)]
    public function actionUpdateDetail(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Check if the version of the assignment is up-to-date.
     * @POST
     * @throws ForbiddenRequestException
     */
    #[Post("version", new VInt(), "Version of the assignment.")]
    #[Path("id", new VUuid(), "Identifier of the assignment", required: true)]
    public function actionValidate($id)
    {
        $this->sendSuccessResponse("OK");
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
        $this->sendSuccessResponse("OK");
    }



    /**
     * Delete an assignment
     * @DELETE
     */
    #[Path("id", new VUuid(), "Identifier of the assignment to be removed", required: true)]
    public function actionRemove(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Update the assignment so that it matches with the current version of the exercise (limits, texts, etc.)
     * @POST
     * @throws BadRequestException
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the assignment that should be synchronized", required: true)]
    public function actionSyncWithExercise($id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Get a list of solutions of all users for the assignment
     * @GET
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the assignment", required: true)]
    public function actionSolutions(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Get a list of solutions created by a user of an assignment
     * @GET
     */
    #[Path("id", new VUuid(), "Identifier of the assignment", required: true)]
    #[Path("userId", new VString(), "Identifier of the user", required: true)]
    public function actionUserSolutions(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Get the best solution by a user to an assignment
     * @GET
     * @throws ForbiddenRequestException
     */
    #[Path("id", new VUuid(), "Identifier of the assignment", required: true)]
    #[Path("userId", new VString(), "Identifier of the user", required: true)]
    public function actionBestSolution(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Get the best solutions to an assignment for all students in group.
     * @GET
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the assignment", required: true)]
    public function actionBestSolutions(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Download the best solutions of an assignment for all students in group.
     * @GET
     * @throws NotFoundException
     * @throws \Nette\Application\AbortException
     * @throws \Nette\Application\BadRequestException
     */
    #[Path("id", new VUuid(), "Identifier of the assignment", required: true)]
    public function actionDownloadBestSolutionsArchive(string $id)
    {
        $this->sendSuccessResponse("OK");
    }
}
