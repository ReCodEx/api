<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\InvalidStateException;
use App\Exceptions\NotFoundException;
use App\Exceptions\FrontendErrorMappings;
use App\Helpers\Localizations;
use App\Helpers\Notifications\AssignmentEmailsSender;
use App\Helpers\Notifications\PointsChangedEmailsSender;
use App\Helpers\Validators;
use App\Model\Entity\LocalizedShadowAssignment;
use App\Model\Entity\ShadowAssignment;
use App\Model\Entity\ShadowAssignmentPoints;
use App\Model\Repository\Groups;
use App\Model\Repository\ShadowAssignmentPointsRepository;
use App\Model\Repository\ShadowAssignments;
use App\Model\View\ShadowAssignmentViewFactory;
use App\Security\ACL\IGroupPermissions;
use App\Security\ACL\IShadowAssignmentPermissions;
use DateTime;
use Nette\Utils\Arrays;

/**
 * Endpoints for points assignment manipulation
 * @LoggedIn
 */
class ShadowAssignmentsPresenter extends BasePresenter
{
    /**
     * @var ShadowAssignments
     * @inject
     */
    public $shadowAssignments;

    /**
     * @var ShadowAssignmentPointsRepository
     * @inject
     */
    public $shadowAssignmentPointsRepository;

    /**
     * @var IShadowAssignmentPermissions
     * @inject
     */
    public $shadowAssignmentAcl;

    /**
     * @var ShadowAssignmentViewFactory
     * @inject
     */
    public $shadowAssignmentViewFactory;

    /**
     * @var AssignmentEmailsSender
     * @inject
     */
    public $assignmentEmailsSender;

    /**
     * @var Groups
     * @inject
     */
    public $groups;

    /**
     * @var IGroupPermissions
     * @inject
     */
    public $groupAcl;

    /**
     * @var PointsChangedEmailsSender
     * @inject
     */
    public $pointsChangedEmailsSender;


    public function noncheckDetail(string $id)
    {
        $assignment = $this->shadowAssignments->findOrThrow($id);
        if (!$this->shadowAssignmentAcl->canViewDetail($assignment)) {
            throw new ForbiddenRequestException("You cannot view this shadow assignment.");
        }
    }

    /**
     * Get details of a shadow assignment
     * @GET
     * @param string $id Identifier of the assignment
     * @throws NotFoundException
     */
    public function actionDetail(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckValidate(string $id)
    {
        $assignment = $this->shadowAssignments->findOrThrow($id);
        if (!$this->shadowAssignmentAcl->canUpdate($assignment)) {
            throw new ForbiddenRequestException("You cannot access this shadow assignment.");
        }
    }

    /**
     * Check if the version of the shadow assignment is up-to-date.
     * @POST
     * @Param(type="post", name="version", validation="numericint", description="Version of the shadow assignment.")
     * @param string $id Identifier of the shadow assignment
     * @throws ForbiddenRequestException
     */
    public function actionValidate($id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdateDetail(string $id)
    {
        $assignment = $this->shadowAssignments->findOrThrow($id);
        if (!$this->shadowAssignmentAcl->canUpdate($assignment)) {
            throw new ForbiddenRequestException("You cannot update this shadow assignment.");
        }
    }

    /**
     * Update details of an shadow assignment
     * @POST
     * @param string $id Identifier of the updated assignment
     * @Param(type="post", name="version", validation="numericint",
     *        description="Version of the edited assignment")
     * @Param(type="post", name="isPublic", validation="bool",
     *        description="Is the assignment ready to be displayed to students?")
     * @Param(type="post", name="isBonus", validation="bool",
     *        description="If true, the points from this exercise will not be included in overall score of group")
     * @Param(type="post", name="localizedTexts", validation="array",
     *        description="A description of the assignment")
     * @Param(type="post", name="maxPoints", validation="numericint",
     *        description="A maximum of points that user can be awarded")
     * @Param(type="post", name="sendNotification", required=false, validation="bool",
     *        description="If email notification should be sent")
     * @Param(type="post", name="deadline", validation="timestamp|null", required=false,
     *        description="Deadline (only for visualization), missing value meas no deadline (same as null)")
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function actionUpdateDetail(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Create new shadow assignment in given group.
     * @POST
     * @Param(type="post", name="groupId", description="Identifier of the group")
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function actionCreate()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRemove(string $id)
    {
        $assignment = $this->shadowAssignments->findOrThrow($id);

        if (!$this->shadowAssignmentAcl->canRemove($assignment)) {
            throw new ForbiddenRequestException("You cannot remove this shadow assignment.");
        }
    }

    /**
     * Delete shadow assignment
     * @DELETE
     * @param string $id Identifier of the assignment to be removed
     * @throws NotFoundException
     */
    public function actionRemove(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckCreatePoints(string $id)
    {
        $assignment = $this->shadowAssignments->findOrThrow($id);
        if (!$this->shadowAssignmentAcl->canCreatePoints($assignment)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Create new points for shadow assignment and user.
     * @POST
     * @param string $id Identifier of the shadow assignment
     * @Param(type="post", name="userId", validation="string",
     *        description="Identifier of the user which is marked as awardee for points")
     * @Param(type="post", name="points", validation="numericint", description="Number of points assigned to the user")
     * @Param(type="post", name="note", validation="string", description="Note about newly created points")
     * @Param(type="post", name="awardedAt", validation="timestamp", required=false,
     *        description="Datetime when the points were awarded, whatever that means")
     * @throws NotFoundException
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     * @throws InvalidStateException
     */
    public function actionCreatePoints(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdatePoints(string $pointsId)
    {
        $points = $this->shadowAssignmentPointsRepository->findOrThrow($pointsId);
        $assignment = $points->getShadowAssignment();
        if (!$this->shadowAssignmentAcl->canUpdatePoints($assignment)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Update detail of shadow assignment points.
     * @POST
     * @param string $pointsId Identifier of the shadow assignment points
     * @Param(type="post", name="points", validation="numericint", description="Number of points assigned to the user")
     * @Param(type="post", name="note", validation="string:0..1024", description="Note about newly created points")
     * @Param(type="post", name="awardedAt", validation="timestamp", required=false,
     *        description="Datetime when the points were awarded, whatever that means")
     * @throws NotFoundException
     * @throws InvalidStateException
     */
    public function actionUpdatePoints(string $pointsId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRemovePoints(string $pointsId)
    {
        $points = $this->shadowAssignmentPointsRepository->findOrThrow($pointsId);
        $assignment = $points->getShadowAssignment();
        if (!$this->shadowAssignmentAcl->canRemovePoints($assignment)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Remove points of shadow assignment.
     * @DELETE
     * @param string $pointsId Identifier of the shadow assignment points
     * @throws NotFoundException
     */
    public function actionRemovePoints(string $pointsId)
    {
        $this->sendSuccessResponse("OK");
    }
}
