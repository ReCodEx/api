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

    public function noncheckDetail(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdateDetail(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckValidate(string $id)
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
        $this->sendSuccessResponse("OK");
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRemove(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSyncWithExercise(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSolutions(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUserSolutions(string $id, string $userId)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckBestSolution(string $id, string $userId)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckBestSolutions(string $id)
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDownloadBestSolutionsArchive(string $id)
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
        $this->sendSuccessResponse("OK");
    }
}
