<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VFloat;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
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


    public function checkDetail(string $id)
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
    #[Path("id", new VString(), "Identifier of the assignment", required: true)]
    public function actionDetail(string $id)
    {
        $assignment = $this->shadowAssignments->findOrThrow($id);
        $this->sendSuccessResponse($this->shadowAssignmentViewFactory->getAssignment($assignment));
    }

    public function checkValidate(string $id)
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
    #[Path("id", new VString(), "Identifier of the shadow assignment", required: true)]
    public function actionValidate($id)
    {
        $assignment = $this->shadowAssignments->findOrThrow($id);
        $version = intval($this->getHttpRequest()->getPost("version"));
        $this->sendSuccessResponse(
            [
                "versionIsUpToDate" => $assignment->getVersion() === $version
            ]
        );
    }

    public function checkUpdateDetail(string $id)
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
     * @throws InvalidArgumentException
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
    #[Path("id", new VString(), "Identifier of the updated assignment", required: true)]
    public function actionUpdateDetail(string $id)
    {
        $assignment = $this->shadowAssignments->findOrThrow($id);

        $req = $this->getRequest();
        $version = intval($req->getPost("version"));
        if ($version !== $assignment->getVersion()) {
            $v = $assignment->getVersion();
            throw new BadRequestException(
                "The shadow assignment was edited in the meantime and the version has changed. Current version is $v.",
                FrontendErrorMappings::E400_010__ENTITY_VERSION_TOO_OLD,
                [
                    'entity' => 'shadowAssignment',
                    'id' => $id,
                    'version' => $v
                ]
            );
        }

        // localized texts cannot be empty
        if (count($req->getPost("localizedTexts")) == 0) {
            throw new InvalidArgumentException("No entry for localized texts given.");
        }

        // old values of some attributes
        $wasPublic = $assignment->isPublic();
        $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);
        $sendNotification = $req->getPost("sendNotification") ? filter_var(
            $req->getPost("sendNotification"),
            FILTER_VALIDATE_BOOLEAN
        ) : true;

        $assignment->incrementVersion();
        $assignment->updatedNow();
        $assignment->setIsPublic($isPublic);
        $assignment->setIsBonus(filter_var($req->getPost("isBonus"), FILTER_VALIDATE_BOOLEAN));
        $assignment->setMaxPoints($req->getPost("maxPoints"));

        $deadline = (int)$req->getPost("deadline");
        $assignment->setDeadline($deadline ? DateTime::createFromFormat('U', $deadline) : null);


        // go through localizedTexts and construct database entities
        $localizedTexts = [];
        foreach ($req->getPost("localizedTexts") as $localization) {
            $lang = $localization["locale"];

            if (array_key_exists($lang, $localizedTexts)) {
                throw new InvalidArgumentException("Duplicate entry for language '$lang' in localizedTexts");
            }

            // create all new localized texts
            $externalAssignmentLink = trim(Arrays::get($localization, "link", ""));
            if ($externalAssignmentLink !== "" && !Validators::isUrl($externalAssignmentLink)) {
                throw new InvalidArgumentException("External assignment link is not a valid URL");
            }

            $localized = new LocalizedShadowAssignment(
                $lang,
                trim(Arrays::get($localization, "name", "")),
                trim(Arrays::get($localization, "text", "")),
                $externalAssignmentLink ?: null
            );

            $localizedTexts[$lang] = $localized;
        }

        // make changes to database
        Localizations::updateCollection($assignment->getLocalizedTexts(), $localizedTexts);

        foreach ($assignment->getLocalizedTexts() as $localizedText) {
            $this->shadowAssignments->persist($localizedText, false);
        }

        // sending notification has to be after setting new localized texts
        if ($sendNotification && $wasPublic === false && $isPublic === true) {
            // assignment is moving from non-public to public, send notification to students
            $this->assignmentEmailsSender->assignmentCreated($assignment);
        }

        $this->shadowAssignments->flush();
        $this->sendSuccessResponse($this->shadowAssignmentViewFactory->getAssignment($assignment));
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
        $req = $this->getRequest();
        $group = $this->groups->findOrThrow($req->getPost("groupId"));

        if (!$this->groupAcl->canCreateShadowAssignment($group)) {
            throw new ForbiddenRequestException("You are not allowed to create assignment in given group.");
        }

        if ($group->isOrganizational()) {
            throw new BadRequestException("You cannot create assignment in organizational groups");
        }

        $assignment = ShadowAssignment::createInGroup($group);
        $this->shadowAssignments->persist($assignment);
        $this->sendSuccessResponse($this->shadowAssignmentViewFactory->getAssignment($assignment));
    }

    public function checkRemove(string $id)
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
    #[Path("id", new VString(), "Identifier of the assignment to be removed", required: true)]
    public function actionRemove(string $id)
    {
        $assignment = $this->shadowAssignments->findOrThrow($id);
        $this->shadowAssignments->remove($assignment);
        $this->sendSuccessResponse("OK");
    }

    public function checkCreatePoints(string $id)
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
    #[Path("id", new VString(), "Identifier of the shadow assignment", required: true)]
    public function actionCreatePoints(string $id)
    {
        $req = $this->getRequest();
        $userId = $req->getPost("userId");
        $points = (int)$req->getPost("points");
        $note = $req->getPost("note");

        $awardedAt = $req->getPost("awardedAt") ?: null;
        $awardedAt = $awardedAt ? DateTime::createFromFormat('U', $awardedAt) : null;

        $assignment = $this->shadowAssignments->findOrThrow($id);
        if ($assignment->getGroup() === null) {
            throw new NotFoundException("Group for assignment '$id' was deleted");
        }

        $user = $this->users->findOrThrow($userId);
        if (!$assignment->getGroup()->isStudentOf($user)) {
            throw new BadRequestException("User is not member of the group");
        }

        if ($assignment->getPointsByUser($user)) {
            throw new BadRequestException("Given user already has shadow assignment points");
        }

        $pointsEntity = new ShadowAssignmentPoints(
            $points,
            $note,
            $assignment,
            $this->getCurrentUser(),
            $user,
            $awardedAt
        );
        $this->shadowAssignmentPointsRepository->persist($pointsEntity);

        // user was awarded with points, send an email
        $this->pointsChangedEmailsSender->shadowPointsUpdated($pointsEntity);

        $this->sendSuccessResponse($this->shadowAssignmentViewFactory->getPoints($pointsEntity));
    }

    public function checkUpdatePoints(string $pointsId)
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
        $pointsEntity = $this->shadowAssignmentPointsRepository->findOrThrow($pointsId);
        $oldPoints = $pointsEntity->getPoints();

        $req = $this->getRequest();
        $points = (int)$req->getPost("points");
        $note = $req->getPost("note");

        $awardedAt = $req->getPost("awardedAt") ?: null;
        $awardedAt = $awardedAt ? DateTime::createFromFormat('U', $awardedAt) : null;

        $pointsEntity->updatedNow();
        $pointsEntity->setPoints($points);
        $pointsEntity->setNote($note);
        $pointsEntity->setAwardedAt($awardedAt);
        $this->shadowAssignmentPointsRepository->flush();

        if ($oldPoints !== $points) {
            // user points was updated, send an email
            $this->pointsChangedEmailsSender->shadowPointsUpdated($pointsEntity);
        }

        $this->sendSuccessResponse($this->shadowAssignmentViewFactory->getPoints($pointsEntity));
    }

    public function checkRemovePoints(string $pointsId)
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
        $points = $this->shadowAssignmentPointsRepository->findOrThrow($pointsId);
        $this->shadowAssignmentPointsRepository->remove($points);
        $this->sendSuccessResponse("OK");
    }
}
