<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Attributes\ResponseFormat;
use App\Helpers\MetaFormats\FormatDefinitions\GroupFormat;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\InvalidApiArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\FrontendErrorMappings;
use App\Helpers\Localizations;
use App\Model\Entity\Assignment;
use App\Model\Entity\ShadowAssignment;
use App\Model\Entity\Group;
use App\Model\Entity\GroupExamLock;
use App\Model\Entity\Instance;
use App\Model\Entity\LocalizedGroup;
use App\Model\Entity\GroupMembership;
use App\Model\Entity\AssignmentSolution;
use App\Model\Repository\Assignments;
use App\Model\Repository\Groups;
use App\Model\Repository\GroupExams;
use App\Model\Repository\GroupExamLocks;
use App\Model\Repository\Users;
use App\Model\Repository\Instances;
use App\Model\Repository\GroupMemberships;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\SecurityEvents;
use App\Model\View\AssignmentViewFactory;
use App\Model\View\AssignmentSolutionViewFactory;
use App\Model\View\ShadowAssignmentViewFactory;
use App\Model\View\GroupViewFactory;
use App\Model\View\UserViewFactory;
use App\Security\ACL\IAssignmentPermissions;
use App\Security\ACL\IAssignmentSolutionPermissions;
use App\Security\ACL\IShadowAssignmentPermissions;
use App\Security\ACL\IGroupPermissions;
use App\Security\Identity;
use App\Security\Loader;
use App\Security\UserStorage;
use DateTime;
use Nette\Application\Request;

/**
 * Endpoints for group manipulation
 * @LoggedIn
 */
class GroupsPresenter extends BasePresenter
{
    /**
     * @var Groups
     * @inject
     */
    public $groups;

    /**
     * @var GroupExams
     * @inject
     */
    public $groupExams;

    /**
     * @var GroupExamLocks
     * @inject
     */
    public $groupExamLocks;

    /**
     * @var Instances
     * @inject
     */
    public $instances;

    /**
     * @var Users
     * @inject
     */
    public $users;

    /**
     * @var GroupMemberships
     * @inject
     */
    public $groupMemberships;

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
     * @var SecurityEvents
     * @inject
     */
    public $securityEvents;

    /**
     * @var IGroupPermissions
     * @inject
     */
    public $groupAcl;

    /**
     * @var IAssignmentPermissions
     * @inject
     */
    public $assignmentAcl;

    /**
     * @var IAssignmentSolutionPermissions
     * @inject
     */
    public $assignmentSolutionAcl;


    /**
     * @var IShadowAssignmentPermissions
     * @inject
     */
    public $shadowAssignmentAcl;

    /**
     * @var Loader
     * @inject
     */
    public $aclLoader;

    /**
     * @var UserStorage
     * @inject
     */
    public $userStorage;

    /**
     * @var GroupViewFactory
     * @inject
     */
    public $groupViewFactory;

    /**
     * @var UserViewFactory
     * @inject
     */
    public $userViewFactory;

    /**
     * @var AssignmentViewFactory
     * @inject
     */
    public $assignmentViewFactory;

    /**
     * @var AssignmentSolutionViewFactory
     * @inject
     */
    public $solutionsViewFactory;

    /**
     * @var ShadowAssignmentViewFactory
     * @inject
     */
    public $shadowAssignmentViewFactory;

    /**
     * Get a list of all non-archived groups a user can see. The return set is filtered by parameters.
     * @GET
     */
    #[Query("instanceId", new VString(), "Only groups of this instance are returned.", required: false, nullable: true)]
    #[Query(
        "ancestors",
        new VBool(),
        "If true, returns an ancestral closure of the initial result set. "
            . "Included ancestral groups do not respect other filters (archived, search, ...).",
        required: false,
    )]
    #[Query(
        "search",
        new VString(),
        "Search string. Only groups containing this string as a substring of their names are returned.",
        required: false,
        nullable: true,
    )]
    #[Query("archived", new VBool(), "Include also archived groups in the result.", required: false)]
    #[Query(
        "onlyArchived",
        new VBool(),
        "Automatically implies \$archived flag and returns only archived groups.",
        required: false,
    )]
    public function actionDefault(
        ?string $instanceId = null,
        bool $ancestors = false,
        ?string $search = null,
        bool $archived = false,
        bool $onlyArchived = false
    ) {
        $user = $this->groupAcl->canViewAll() ? null : $this->getCurrentUser(); // user for membership restriction
        $groups = $this->groups->findFiltered($user, $instanceId, $search, $archived, $onlyArchived);
        if ($ancestors) {
            $groups = $this->groups->groupsAncestralClosure($groups);
        }
        $this->sendSuccessResponse($this->groupViewFactory->getGroups($groups, false));
    }

    /**
     * Helper method that handles updating points limit and threshold to a group entity (from a request).
     * @param Request $req request data
     * @param Group $group to be updated
     */
    private function setGroupPoints(Request $req, Group $group): void
    {
        $threshold = $req->getPost("threshold");
        $pointsLimit = $req->getPost("pointsLimit");
        if ($threshold !== null && $pointsLimit !== null) {
            throw new InvalidApiArgumentException(
                'threshold',
                "A group may have either a threshold or points limit, not both."
            );
        }
        if ($threshold !== null) {
            if ($threshold <= 0 || $threshold > 100) {
                throw new InvalidApiArgumentException('threshold', "A threshold must be in the (0, 100] (%) range.");
            }
            $group->setThreshold($threshold / 100);
        } else {
            $group->setThreshold(null);
        }
        if ($pointsLimit !== null) {
            if ($pointsLimit <= 0) {
                throw new InvalidApiArgumentException('pointsLimit', "A points limit must be a positive number.");
            }
            $group->setPointsLimit($pointsLimit);
        } else {
            $group->setPointsLimit(null);
        }
    }

    /**
     * Create a new group
     * @POST
     * @throws ForbiddenRequestException
     * @throws InvalidApiArgumentException
     */
    #[Post("instanceId", new VUuid(), "An identifier of the instance where the group should be created")]
    #[Post(
        "externalId",
        new VMixed(),
        "An informative, human readable identifier of the group",
        required: false,
        nullable: true,
    )]
    #[Post(
        "parentGroupId",
        new VUuid(),
        "Identifier of the parent group (if none is given, a top-level group is created)",
        required: false,
    )]
    #[Post("publicStats", new VBool(), "Should students be able to see each other's results?", required: false)]
    #[Post("detaining", new VBool(), "Are students prevented from leaving the group on their own?", required: false)]
    #[Post("isPublic", new VBool(), "Should the group be visible to all student?", required: false)]
    #[Post(
        "isOrganizational",
        new VBool(),
        "Whether the group is organizational (no assignments nor students).",
        required: false,
    )]
    #[Post("isExam", new VBool(), "Whether the group is an exam group.", required: false)]
    #[Post("localizedTexts", new VArray(), "Localized names and descriptions", required: false)]
    #[Post("threshold", new VInt(), "A minimum percentage of points needed to pass the course", required: false)]
    #[Post("pointsLimit", new VInt(), "A minimum of (absolute) points needed to pass the course", required: false)]
    #[Post(
        "noAdmin",
        new VBool(),
        "If true, no admin is assigned to group (current user is assigned as admin by default.",
        required: false,
    )]
    #[ResponseFormat(GroupFormat::class)]
    public function actionAddGroup()
    {
        $req = $this->getRequest();
        $instanceId = $req->getPost("instanceId");
        $parentGroupId = $req->getPost("parentGroupId");
        $user = $this->getCurrentUser();

        /** @var Instance $instance */
        $instance = $this->instances->findOrThrow($instanceId);
        $parentGroup = !$parentGroupId ? $instance->getRootGroup() : $this->groups->findOrThrow($parentGroupId);

        if ($parentGroup->isArchived()) {
            throw new InvalidApiArgumentException(
                'parentGroupId',
                "It is not permitted to create subgroups in archived groups"
            );
        }

        if (!$this->groupAcl->canAddSubgroup($parentGroup)) {
            throw new ForbiddenRequestException("You are not allowed to add subgroups to this group");
        }

        $externalId = $req->getPost("externalId") === null ? "" : $req->getPost("externalId");
        $publicStats = filter_var($req->getPost("publicStats"), FILTER_VALIDATE_BOOLEAN);
        $detaining = filter_var($req->getPost("detaining"), FILTER_VALIDATE_BOOLEAN);
        $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);
        $isOrganizational = filter_var($req->getPost("isOrganizational"), FILTER_VALIDATE_BOOLEAN);
        $isExam = filter_var($req->getPost("isExam"), FILTER_VALIDATE_BOOLEAN);
        $noAdmin = filter_var($req->getPost("noAdmin"), FILTER_VALIDATE_BOOLEAN);

        if ($isOrganizational && $isExam) {
            throw new InvalidApiArgumentException(
                'isOrganizational, isExam',
                "A group cannot be both organizational and exam."
            );
        }

        $group = new Group(
            $externalId,
            $instance,
            $noAdmin ? null : $user,
            $parentGroup,
            $publicStats,
            $isPublic,
            $isOrganizational,
            $detaining,
            $isExam,
        );

        $this->setGroupPoints($req, $group);
        $this->updateLocalizations($req, $group);

        $this->groups->persist($group, false);
        $this->groups->flush();

        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    /**
     * Validate group creation data
     * @POST
     * @throws ForbiddenRequestException
     */
    #[Post("name", new VMixed(), "Name of the group", nullable: true)]
    #[Post("locale", new VMixed(), "The locale of the name", nullable: true)]
    #[Post("instanceId", new VMixed(), "Identifier of the instance where the group belongs", nullable: true)]
    #[Post("parentGroupId", new VMixed(), "Identifier of the parent group", required: false, nullable: true)]
    public function actionValidateAddGroupData()
    {
        $req = $this->getRequest();
        $name = $req->getPost("name");
        $locale = $req->getPost("locale");
        $parentGroupId = $req->getPost("parentGroupId");
        $instance = $this->instances->findOrThrow($req->getPost("instanceId"));
        $parentGroup = $parentGroupId !== null ? $this->groups->findOrThrow($parentGroupId) : $instance->getRootGroup();

        if (!$this->groupAcl->canAddSubgroup($parentGroup)) {
            throw new ForbiddenRequestException();
        }

        $this->sendSuccessResponse(
            [
                "groupNameIsFree" => count($this->groups->findByName($locale, $name, $instance, $parentGroup)) === 0
            ]
        );
    }

    public function checkUpdateGroup(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        if (!$this->groupAcl->canUpdate($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Update group info
     * @POST
     * @throws InvalidApiArgumentException
     */
    #[Post(
        "externalId",
        new VMixed(),
        "An informative, human readable identifier of the group",
        required: false,
        nullable: true,
    )]
    #[Post("publicStats", new VBool(), "Should students be able to see each other's results?")]
    #[Post("detaining", new VBool(), "Are students prevented from leaving the group on their own?", required: false)]
    #[Post("isPublic", new VBool(), "Should the group be visible to all student?")]
    #[Post("threshold", new VInt(), "A minimum percentage of points needed to pass the course", required: false)]
    #[Post("pointsLimit", new VInt(), "A minimum of (absolute) points needed to pass the course", required: false)]
    #[Post("localizedTexts", new VArray(), "Localized names and descriptions")]
    #[Path("id", new VUuid(), "An identifier of the updated group", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionUpdateGroup(string $id)
    {
        $req = $this->getRequest();
        $publicStats = filter_var($req->getPost("publicStats"), FILTER_VALIDATE_BOOLEAN);
        $detaining = filter_var($req->getPost("detaining"), FILTER_VALIDATE_BOOLEAN);
        $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);

        $group = $this->groups->findOrThrow($id);
        $group->setExternalId($req->getPost("externalId"));
        $group->setPublicStats($publicStats);
        $group->setDetaining($detaining);
        $group->setIsPublic($isPublic);

        $this->setGroupPoints($req, $group);
        $this->updateLocalizations($req, $group);

        $this->groups->persist($group);
        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkSetOrganizational(string $id)
    {
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canSetOrganizational($group)) {
            throw new ForbiddenRequestException();
        }

        if ($group->isExam()) {
            throw new BadRequestException("Organizational group must not be exam group.");
        }
    }

    /**
     * Set the 'isOrganizational' flag for a group
     * @POST
     * @throws BadRequestException
     * @throws NotFoundException
     */
    #[Post("value", new VBool(), "The value of the flag", required: true)]
    #[Path("id", new VUuid(), "An identifier of the updated group", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionSetOrganizational(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        $isOrganizational = filter_var($this->getRequest()->getPost("value"), FILTER_VALIDATE_BOOLEAN);

        if ($isOrganizational) {
            if ($group->getStudents()->count() > 0) {
                throw new BadRequestException("The group already contains students");
            }

            if ($group->getAssignments()->count() > 0) {
                throw new BadRequestException("The group already contains assignments");
            }
        }

        $group->setOrganizational($isOrganizational);
        $this->groups->persist($group);
        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkSetArchived(string $id)
    {
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canArchive($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Set the 'isArchived' flag for a group
     * @POST
     * @throws NotFoundException
     */
    #[Post("value", new VBool(), "The value of the flag", required: true)]
    #[Path("id", new VUuid(), "An identifier of the updated group", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionSetArchived(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        $archive = filter_var($this->getRequest()->getPost("value"), FILTER_VALIDATE_BOOLEAN);

        if ($archive) {
            $group->archive(new DateTime());

            // snapshot the inherited membership-relations
            $typePriorities = array_flip(GroupMembership::INHERITABLE_TYPES);

            // this is actually a hack for PHP Stan (can be removed when there will be more than 1 inheritable type)
            $typePriorities[''] = -1; // adding a fake priority for fake type

            $membershipsToInherit = []; // aggregated memberships from all ancestors, key is user ID

            // scan ancestors and aggregate memberships by priorities
            $g = $group; // current group is included as well to remove redundant relations
            while ($g !== null) {
                $memberships = $g->getMemberships(...GroupMembership::INHERITABLE_TYPES);
                foreach ($memberships as $membership) {
                    $userId = $membership->getUser()->getId();
                    if (
                        !empty($membershipsToInherit[$userId])
                        && $typePriorities[$membershipsToInherit[$userId]->getType()]
                        > $typePriorities[$membership->getType()] // lower value = higher priority
                    ) {
                        continue; // existing membership with higher priority is already recorded
                    }

                    $membershipsToInherit[$userId] = $membership;
                }
                $g = $g->getParentGroup();
            }

            // create inherited membership records in the database
            foreach ($membershipsToInherit as $membership) {
                // direct memberships are ignored, they were just used to remove redundant relations
                if ($membership->getGroup()->getId() !== $group->getId()) {
                    $group->inheritMembership($membership);
                }
            }
        } else {
            $group->undoArchiving();

            // remove inherited memberships what so ever
            $memberships = $group->getInheritedMemberships(...GroupMembership::INHERITABLE_TYPES);
            foreach ($memberships as $membership) {
                $group->removeMembership($membership);
                $this->groupMemberships->remove($membership);
            }
        }

        $this->groups->persist($group);
        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkSetExam(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        if (!$group->getChildGroups()->isEmpty()) {
            throw new BadRequestException("Exam group must have no sub-groups.");
        }

        if ($group->isOrganizational()) {
            throw new BadRequestException("Exam group must not be organizational.");
        }

        if (!$this->groupAcl->canSetExamFlag($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Change the group "exam" indicator. If denotes that the group should be listed in exam groups instead of
     * regular groups and the assignments should have "isExam" flag set by default.
     * @POST
     * @throws BadRequestException
     * @throws NotFoundException
     */
    #[Post("value", new VBool(), "The value of the flag", required: true)]
    #[Path("id", new VUuid(), "An identifier of the updated group", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionSetExam(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        $isExam = filter_var($this->getRequest()->getPost("value"), FILTER_VALIDATE_BOOLEAN);
        $group->setExam($isExam);
        $this->groups->persist($group);
        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkSetExamPeriod(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        if (!$this->groupAcl->canSetExamPeriod($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Set an examination period (in the future) when the group will be secured for submitting.
     * Only locked students may submit solutions in the group during this period.
     * This endpoint is also used to update already planned exam period, but only dates in the future
     * can be edited (e.g., once an exam begins, the beginning may no longer be updated).
     * @POST
     * @throws NotFoundException
     */
    #[Post(
        "begin",
        new VTimestamp(),
        "When the exam begins (unix ts in the future, optional if update is performed).",
        required: false,
        nullable: true,
    )]
    #[Post(
        "end",
        new VTimestamp(),
        "When the exam ends (unix ts in the future, no more than a day after 'begin').",
        required: true,
    )]
    #[Post("strict", new VBool(), "Whether locked users are prevented from accessing other groups.", required: false)]
    #[Path("id", new VUuid(), "An identifier of the updated group", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionSetExamPeriod(string $id)
    {
        $group = $this->groups->findOrThrow($id);

        $req = $this->getRequest();
        $beginTs = (int)$req->getPost("begin");
        $endTs = (int)$req->getPost("end");
        $strict = $req->getPost("strict") !== null
            ? filter_var($req->getPost("strict"), FILTER_VALIDATE_BOOLEAN) : null;
        $now = (new DateTime())->getTimestamp();
        $nowTolerance = 60;  // 60s is a tolerance when comparing with "now"

        if ($strict === null) {
            if ($group->hasExamPeriodSet()) {
                $strict = $group->isExamLockStrict(); // flag is not present -> is not changing
            } else {
                throw new BadRequestException("The strict flag must be present when new exam is being set.");
            }
        }

        // beginning must be in the future (or must not be modified)
        if ((!$group->hasExamPeriodSet() || $beginTs) && $beginTs < $now - $nowTolerance) {
            throw new BadRequestException("The exam must be set in the future.");
        }

        // if begin was not sent, or the exam already started, use old begin value
        $beginTs = ($group->hasExamPeriodSet() && (!$beginTs || $group->getExamBegin()->getTimestamp() <= $now))
            ? $group->getExamBegin()->getTimestamp() : $beginTs;

        // an exam should not last more than a day (yes, we hardcode the day interval here for safety)
        if ($beginTs >= $endTs || $endTs - $beginTs > 86400) {
            throw new BadRequestException("The [begin,end] interval must be valid and less than a day wide.");
        }

        // the end should also be in the future (this is necessary only for updates)
        if ($endTs < $now - $nowTolerance) {
            throw new BadRequestException("The exam end must be set in the future.");
        }

        $begin = DateTime::createFromFormat('U', $beginTs);
        $end = DateTime::createFromFormat('U', $endTs);

        if ($group->hasExamPeriodSet()) {
            if ($group->getExamBegin()->getTimestamp() <= $now) { // ... already begun
                if ($strict !== $group->isExamLockStrict()) {
                    throw new BadRequestException("The strict flag cannot be changed once the exam begins.");
                }

                // the exam already begun, we need to fix any group-locked users
                foreach ($group->getStudents() as $student) {
                    if ($student->getGroupLock()?->getId() === $id) {
                        $student->setGroupLock($group, $end, $strict);
                        if ($student->isIpLocked()) {
                            $student->setIpLock($student->getIpLockRaw(), $end);
                        }
                        $this->users->persist($student, false);
                    }
                }

                // we need to fix deadlines of all aligned exam assignments
                foreach ($group->getAssignments() as $assignment) {
                    if (
                        $assignment->isExam() &&
                        $assignment->getFirstDeadline()->getTimestamp() === $group->getExamEnd()->getTimestamp()
                    ) {
                        $assignment->setFirstDeadline($end);
                        $this->assignments->persist($assignment, false);
                    }
                }
            } elseif ($group->getExamBegin() !== $begin) {
                // we also need to fix times of appearance for scheduled assignments
                foreach ($group->getAssignments() as $assignment) {
                    if (
                        $assignment->isExam() && $assignment->isPublic() &&
                        $assignment->getVisibleFrom()?->getTimestamp() === $group->getExamBegin()->getTimestamp()
                    ) {
                        $assignment->setVisibleFrom($now < $beginTs ? $begin : null);
                        $this->assignments->persist($assignment, false);
                    }
                }
            }
        }

        $exam = $this->groupExams->findPendingForGroup($group);
        if ($exam) {
            $exam->update($begin, $end, $strict);
            $this->groupExams->persist($exam, false);
        }

        $group->setExamPeriod($begin, $end, $strict);
        $this->groups->persist($group);

        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkRemoveExamPeriod(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        if (!$group->hasExamPeriodSet()) {
            throw new BadRequestException("The group has no exam period set.");
        }

        if (!$this->groupAcl->canRemoveExamPeriod($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Change the group back to regular group (remove information about an exam).
     * @DELETE
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "An identifier of the updated group", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionRemoveExamPeriod(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        $group->removeExamPeriod();
        $this->groups->persist($group);
        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkRelocate(string $id, string $newParentId)
    {
        $group = $this->groups->findOrThrow($id);
        $newParent = $this->groups->findOrThrow($newParentId);

        if ($group->isArchived() || $newParent->isArchived()) {
            throw new BadRequestException(
                "Cannot manipulate with archived group.",
                FrontendErrorMappings::E400_501__GROUP_ARCHIVED
            );
        }

        if (
            !$this->groupAcl->canRelocate($group)
            || !$this->groupAcl->canAddSubgroup($newParent)
        ) {
            throw new ForbiddenRequestException();
        }
    }

    public function checkGetExamLocks(string $id, string $examId)
    {
        $groupExam = $this->groupExams->findOrThrow($examId);
        if ($groupExam->getGroup()?->getId() !== $id) {
            throw new BadRequestException(
                "Exam $examId is not in group $id.",
                FrontendErrorMappings::E400_500__GROUP_ERROR
            );
        }

        $group = $this->groups->findOrThrow($id);
        if (!$this->groupAcl->canViewExamLocks($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Retrieve a list of locks for given exam
     * @GET
     */
    #[Path("id", new VUuid(), "An identifier of the related group", required: true)]
    #[Path("examId", new VInt(), "An identifier of the exam", required: true)]
    public function actionGetExamLocks(string $id, string $examId)
    {
        $group = $this->groups->findOrThrow($id);
        $exam = $this->groupExams->findOrThrow($examId);
        $locks = $this->groupExamLocks->findBy(["groupExam" => $exam]);
        $this->sendSuccessResponse($this->groupViewFactory->getGroupExamLocks($group, $locks));
    }

    /**
     * Relocate the group under a different parent.
     * @POST
     * @throws NotFoundException
     * @throws BadRequestException
     */
    #[Path("id", new VUuid(), "An identifier of the relocated group", required: true)]
    #[Path("newParentId", new VString(), "An identifier of the new parent group", required: true)]
    public function actionRelocate(string $id, string $newParentId)
    {
        $group = $this->groups->findOrThrow($id);
        $newParent = $this->groups->findOrThrow($newParentId);

        if ($group->getInstance() !== null && $group->getInstance()->getRootGroup() === $group) {
            throw new BadRequestException(
                "The root group of an instance cannot relocate.",
                FrontendErrorMappings::E400_502__GROUP_INSTANCE_ROOT_CANNOT_RELOCATE,
                ['groupId' => $id, 'instanceId' => $group->getInstance()->getId()]
            );
        }

        foreach ($this->groups->groupsAncestralClosure([$newParent]) as $parent) {
            if ($parent->getId() === $id) { // group cannot be relocated under its descendant
                throw new BadRequestException(
                    "The relocation would create a loop in the group hierarchy.",
                    FrontendErrorMappings::E400_503__GROUP_RELOCATION_WOULD_CREATE_LOOP,
                    ['groupId' => $id, 'newParentId' => $newParentId]
                );
            }
        }

        $group->setParentGroup($newParent);
        $this->groups->persist($group);
        $this->forward(
            'Groups:',
            ['instanceId' => $newParent->getInstance()->getId(), 'ancestors' => true]
        ); // return all groups
    }

    public function checkRemoveGroup(string $id)
    {
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canRemove($group)) {
            throw new ForbiddenRequestException();
        }

        if ($group->getChildGroups()->count() !== 0) {
            throw new ForbiddenRequestException("There are subgroups of group '$id'. Please remove them first.");
        } else {
            if ($group->getInstance() !== null && $group->getInstance()->getRootGroup() === $group) {
                throw new ForbiddenRequestException(
                    "Group '$id' is the root group of instance '"
                        . $group->getInstance()->getId()
                        . "' and root groups cannot be deleted."
                );
            }
        }
    }

    /**
     * Delete a group
     * @DELETE
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    public function actionRemoveGroup(string $id)
    {
        $group = $this->groups->findOrThrow($id);

        $this->groups->remove($group);
        $this->groups->flush();

        $this->sendSuccessResponse("OK");
    }

    public function checkDetail(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        if (!$this->groupAcl->canViewPublicDetail($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get details of a group
     * @GET
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionDetail(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkSubgroups(string $id)
    {
        /** @var Group $group */
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canViewDetail($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of subgroups of a group
     * @GET
     * @DEPRECATED Subgroup list is part of group view.
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    public function actionSubgroups(string $id)
    {
        /** @var Group $group */
        $group = $this->groups->findOrThrow($id);

        $subgroups = array_values(
            array_filter(
                $group->getAllSubgroups(),
                function (Group $subgroup) {
                    return $this->groupAcl->canViewPublicDetail($subgroup);
                }
            )
        );

        $this->sendSuccessResponse($this->groupViewFactory->getGroups($subgroups));
    }

    public function checkMembers(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        if (!$this->groupAcl->canViewDetail($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of members of a group
     * @GET
     * @DEPRECATED Members are listed in group view.
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    public function actionMembers(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        $this->sendSuccessResponse(
            [
                "admins" => $this->userViewFactory->getUsers($group->getPrimaryAdmins()->getValues()),
                "supervisors" => $this->userViewFactory->getUsers($group->getSupervisors()->getValues()),
            ]
        );
    }

    public function checkAddMember(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        /** @var IGroupPermissions $userAcl */
        $userAcl = $this->aclLoader->loadACLModule(
            IGroupPermissions::class,
            $this->authorizator,
            new Identity($user, null)
        );

        if (!$this->groupAcl->canAddMember($group, $user) || !$userAcl->canBecomeMember($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Add/update a membership (other than student) for given user
     * @POST
     */
    #[Post("type", new VString(1), "Identifier of membership type (admin, supervisor, ...)", required: true)]
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the supervisor", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionAddMember(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        $type = $this->getRequest()->getPost("type");
        if ($type === GroupMembership::TYPE_STUDENT || !in_array($type, GroupMembership::KNOWN_TYPES)) {
            throw new InvalidApiArgumentException('type', "Unknown membership type '$type'");
        }

        $membership = $group->getMembershipOfUser($user);
        if ($membership) {
            // update type of existing membership (if it is not a student)
            if ($membership->getType() === GroupMembership::TYPE_STUDENT) {
                throw new InvalidApiArgumentException(
                    'userId',
                    "The user is a student of the group and students cannot be made also members"
                );
            }
            $membership->setType($type);
        } else {
            // create new membership
            $membership = new GroupMembership($group, $user, $type);
            $group->addMembership($membership);
        }
        $this->groupMemberships->persist($membership);

        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkRemoveMember(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canRemoveMember($group, $user)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Remove a member (other than student) from a group
     * @DELETE
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the supervisor", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionRemoveMember(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        $membership = $group->getMembershipOfUser($user);
        if (!$membership) {
            throw new InvalidApiArgumentException('userId', "The user is not a member of the group");
        }
        if ($membership->getType() === GroupMembership::TYPE_STUDENT) {
            throw new InvalidApiArgumentException(
                'userId',
                "The user is a student of the group and students must be removed by separate endpoint"
            );
        }

        $group->removeMembership($membership);
        $this->groupMemberships->remove($membership);
        $this->groupMemberships->flush();

        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkAssignments(string $id)
    {
        /** @var Group $group */
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canViewAssignments($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get all exercise assignments for a group
     * @GET
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    public function actionAssignments(string $id)
    {
        /** @var Group $group */
        $group = $this->groups->findOrThrow($id);

        $assignments = $group->getAssignments();
        $this->sendSuccessResponse(
            $assignments->filter(
                function (Assignment $assignment) {
                    return $this->assignmentAcl->canViewDetail($assignment);
                }
            )->map(
                function (Assignment $assignment) {
                    return $this->assignmentViewFactory->getAssignment($assignment);
                }
            )->getValues()
        );
    }

    public function checkShadowAssignments(string $id)
    {
        /** @var Group $group */
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canViewAssignments($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get all shadow assignments for a group
     * @GET
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    public function actionShadowAssignments(string $id)
    {
        /** @var Group $group */
        $group = $this->groups->findOrThrow($id);

        $assignments = $group->getShadowAssignments();
        $this->sendSuccessResponse(
            $assignments->filter(
                function (ShadowAssignment $assignment) {
                    return $this->shadowAssignmentAcl->canViewDetail($assignment);
                }
            )->map(
                function (ShadowAssignment $assignment) {
                    return $this->shadowAssignmentViewFactory->getAssignment($assignment);
                }
            )->getValues()
        );
    }

    public function checkStats(string $id)
    {
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canViewStats($group)) {
            $user = $this->getCurrentUser();

            if (!($this->groupAcl->canViewStudentStats($group, $user) && $group->isStudentOf($user))) {
                throw new ForbiddenRequestException();
            }
        }
    }

    /**
     * Get statistics of a group. If the user does not have the rights to view all of these, try to at least
     * return their statistics.
     * @GET
     * @throws ForbiddenRequestException
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    public function actionStats(string $id)
    {
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canViewStats($group)) {
            $user = $this->getCurrentUser();
            $stats = $this->groupViewFactory->getStudentsStats($group, $user);
            $this->sendSuccessResponse([$stats]);
        } else {
            $this->sendSuccessResponse($this->groupViewFactory->getAllStudentsStats($group));
        }
    }

    public function checkStudentsStats(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canViewStudentStats($group, $user)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get statistics of a single student in a group
     * @GET
     * @throws BadRequestException
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the student", required: true)]
    public function actionStudentsStats(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        if ($group->isStudentOf($user) === false) {
            throw new BadRequestException("User $userId is not student of $id");
        }

        $this->sendSuccessResponse($this->groupViewFactory->getStudentsStats($group, $user));
    }

    public function checkStudentsSolutions(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        // actually studentStats permission is like a soft pre-condition, the solutions are filtered individually
        if (!$this->groupAcl->canViewAssignments($group) || !$this->groupAcl->canViewStudentStats($group, $user)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get all solutions of a single student from all assignments in a group
     * @GET
     * @throws BadRequestException
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the student", required: true)]
    public function actionStudentsSolutions(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        if ($group->isStudentOf($user) === false) {
            throw new BadRequestException("User $userId is not student of $id");
        }

        $allSolutions = $this->assignmentSolutions->findGroupSolutionsOfStudent($group, $user);
        $bestSolutions = $this->assignmentSolutions->filterBestSolutions($allSolutions);
        $solutions = array_filter(
            $allSolutions,
            function (AssignmentSolution $solution) {
                return $this->assignmentSolutionAcl->canViewDetail($solution);
            }
        );

        $this->sendSuccessResponse(
            $this->solutionsViewFactory->getSolutionsData($solutions, $bestSolutions)
        );
    }

    public function checkAddStudent(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canAddStudent($group, $user)) {
            throw new ForbiddenRequestException();
        }

        if ($group->isArchived() && !$this->groupAcl->canAddStudentToArchivedGroup($group, $user)) {
            throw new ForbiddenRequestException();
        }

        if ($group->isOrganizational()) {
            throw new InvalidApiArgumentException('id', "It is forbidden to add students to organizational groups");
        }
    }

    /**
     * Add a student to a group
     * @POST
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the student", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionAddStudent(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        // make sure that the user is not already member of the group
        if ($group->isStudentOf($user) === false) {
            $user->makeStudentOf($group);
            $this->groups->flush();
        }

        // join the group
        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkRemoveStudent(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canRemoveStudent($group, $user)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Remove a student from a group
     * @DELETE
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the student", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionRemoveStudent(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        // make sure that the user is student of the group
        if ($group->isStudentOf($user) === true) {
            $membership = $user->findMembershipAsStudent($group);
            if ($membership) {
                $this->groupMemberships->remove($membership);
                $this->groups->flush();
            }
        }

        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkLockStudent(string $id, string $userId)
    {
        $group = $this->groups->findOrThrow($id);
        $user = $this->users->findOrThrow($userId);
        if (!$this->groupAcl->canLockStudent($group, $user)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Lock student in a group and with an IP from which the request was made.
     * @POST
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the student", required: true)]
    public function actionLockStudent(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        $expiration = $group->getExamEnd();
        $user->setIpLock($this->getHttpRequest()->getRemoteAddress(), $expiration);
        $user->setGroupLock($group, $expiration, $group->isExamLockStrict());
        $this->users->persist($user, false);

        // make sure the locking is also logged
        $exam = $this->groupExams->findOrCreate($group);
        $examLock = new GroupExamLock($exam, $user, $user->getIpLockRaw());
        $this->groupExamLocks->persist($examLock);

        $this->sendSuccessResponse([
            'user' => $this->userViewFactory->getUser($user),
            'group' => $this->groupViewFactory->getGroup($group),
        ]);
    }

    public function checkUnlockStudent(string $id, string $userId)
    {
        $group = $this->groups->findOrThrow($id);
        $user = $this->users->findOrThrow($userId);
        if ($user->getGroupLock()?->getId() !== $group->getId()) {
            throw new InvalidApiArgumentException('userId', "The user is not locked in given group.");
        }

        if (!$this->groupAcl->canUnlockStudent($group, $user)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Unlock a student currently locked in a group.
     * @DELETE
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the student", required: true)]
    public function actionUnlockStudent(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);

        $lock = $this->groupExamLocks->getCurrentLock($user);
        if ($lock) {
            $lock->setUnlockedAt();
            $this->groupExamLocks->persist($lock, false);
        }

        $user->removeIpLock();
        $user->removeGroupLock();
        $this->users->persist($user);

        $this->sendSuccessResponse($this->userViewFactory->getUser($user));
    }


    /**
     * @param Request $req
     * @param Group $group
     * @throws InvalidApiArgumentException
     */
    private function updateLocalizations(Request $req, Group $group): void
    {
        $localizedTexts = $req->getPost("localizedTexts");

        if (count($localizedTexts) > 0) {
            $localizations = [];

            foreach ($localizedTexts as $item) {
                $lang = $item["locale"];
                $otherGroups = $this->groups->findByName(
                    $lang,
                    $item["name"],
                    $group->getInstance(),
                    $group->getParentGroup()
                );

                foreach ($otherGroups as $otherGroup) {
                    if ($otherGroup !== $group) {
                        throw new InvalidApiArgumentException(
                            'name',
                            "There is already a group of this name, please choose a different one."
                        );
                    }
                }

                if (array_key_exists($lang, $localizations)) {
                    throw new InvalidApiArgumentException(
                        'localizedTexts',
                        sprintf("Duplicate entry for locale %s", $lang)
                    );
                }

                $name = $item["name"] ?: "";
                $description = $item["description"] ?: "";
                $localizations[$lang] = new LocalizedGroup($lang, $name, $description);
            }

            /** @var LocalizedGroup $text */
            foreach ($group->getLocalizedTexts() as $text) {
                // Localizations::updateCollection only updates the inverse side of the relationship. Doctrine needs us
                // to update the other side manually. We set it to null for all potentially removed localizations first.
                $text->setGroup(null);
            }

            Localizations::updateCollection($group->getLocalizedTexts(), $localizations);

            foreach ($group->getLocalizedTexts() as $text) {
                $text->setGroup($group);
                $this->groups->persist($text, false);
            }
        }
    }
}
