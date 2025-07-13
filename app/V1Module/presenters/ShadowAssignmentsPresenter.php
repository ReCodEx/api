<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
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
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the assignment", required: true)]
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
     * @throws ForbiddenRequestException
     */
    #[Post("version", new VInt(), "Version of the shadow assignment.")]
    #[Path("id", new VUuid(), "Identifier of the shadow assignment", required: true)]
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
     * @throws BadRequestException
     * @throws InvalidApiArgumentException
     * @throws NotFoundException
     */
    #[Post("version", new VInt(), "Version of the edited assignment")]
    #[Post("isPublic", new VBool(), "Is the assignment ready to be displayed to students?")]
    #[Post(
        "isBonus",
        new VBool(),
        "If true, the points from this exercise will not be included in overall score of group",
    )]
    #[Post("localizedTexts", new VArray(), "A description of the assignment")]
    #[Post("maxPoints", new VInt(), "A maximum of points that user can be awarded")]
    #[Post("sendNotification", new VBool(), "If email notification should be sent", required: false)]
    #[Post(
        "deadline",
        new VTimestamp(),
        "Deadline (only for visualization), missing value meas no deadline (same as null)",
        required: false,
        nullable: true,
    )]
    #[Path("id", new VUuid(), "Identifier of the updated assignment", required: true)]
    public function actionUpdateDetail(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Create new shadow assignment in given group.
     * @POST
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     * @throws NotFoundException
     */
    #[Post("groupId", new VMixed(), "Identifier of the group", nullable: true)]
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
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the assignment to be removed", required: true)]
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
     * @throws NotFoundException
     * @throws ForbiddenRequestException
     * @throws BadRequestException
     * @throws InvalidStateException
     */
    #[Post("userId", new VString(), "Identifier of the user which is marked as awardee for points")]
    #[Post("points", new VInt(), "Number of points assigned to the user")]
    #[Post("note", new VString(), "Note about newly created points")]
    #[Post(
        "awardedAt",
        new VTimestamp(),
        "Datetime when the points were awarded, whatever that means",
        required: false,
    )]
    #[Path("id", new VUuid(), "Identifier of the shadow assignment", required: true)]
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
     * @throws NotFoundException
     * @throws InvalidStateException
     */
    #[Post("points", new VInt(), "Number of points assigned to the user")]
    #[Post("note", new VString(0, 1024), "Note about newly created points")]
    #[Post(
        "awardedAt",
        new VTimestamp(),
        "Datetime when the points were awarded, whatever that means",
        required: false,
    )]
    #[Path("pointsId", new VString(), "Identifier of the shadow assignment points", required: true)]
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
     * @throws NotFoundException
     */
    #[Path("pointsId", new VString(), "Identifier of the shadow assignment points", required: true)]
    public function actionRemovePoints(string $pointsId)
    {
        $this->sendSuccessResponse("OK");
    }
}
