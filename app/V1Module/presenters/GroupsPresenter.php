<?php

namespace App\V1Module\Presenters;

use App\Exceptions\InvalidArgumentException;
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
     * @param string|null $instanceId Only groups of this instance are returned.
     * @param bool $ancestors If true, returns an ancestral closure of the initial result set.
     *  Included ancestral groups do not respect other filters (archived, search, ...).
     * @param string|null $search Search string. Only groups containing this string as
     *  a substring of their names are returned.
     * @param bool $archived Include also archived groups in the result.
     * @param bool $onlyArchived Automatically implies $archived flag and returns only archived groups.
     */
    public function actionDefault(
        string $instanceId = null,
        bool $ancestors = false,
        string $search = null,
        bool $archived = false,
        bool $onlyArchived = false
    ) {
        $this->sendSuccessResponse("OK");
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
            throw new InvalidArgumentException("A group may have either a threshold or points limit, not both.");
        }
        if ($threshold !== null) {
            if ($threshold <= 0 || $threshold > 100) {
                throw new InvalidArgumentException("A threshold must be in the (0, 100] (%) range.");
            }
            $group->setThreshold($threshold / 100);
        } else {
            $group->setThreshold(null);
        }
        if ($pointsLimit !== null) {
            if ($pointsLimit <= 0) {
                throw new InvalidArgumentException("A points limit must be a positive number.");
            }
            $group->setPointsLimit($pointsLimit);
        } else {
            $group->setPointsLimit(null);
        }
    }

    /**
     * Create a new group
     * @POST
     * @Param(type="post", name="instanceId", validation="string:36",
     *        description="An identifier of the instance where the group should be created")
     * @Param(type="post", name="externalId", required=false,
     *        description="An informative, human readable identifier of the group")
     * @Param(type="post", name="parentGroupId", validation="string:36", required=false,
     *        description="Identifier of the parent group (if none is given, a top-level group is created)")
     * @Param(type="post", name="publicStats", validation="bool", required=false,
     *        description="Should students be able to see each other's results?")
     * @Param(type="post", name="detaining", validation="bool", required=false,
     *        description="Are students prevented from leaving the group on their own?")
     * @Param(type="post", name="isPublic", validation="bool", required=false,
     *        description="Should the group be visible to all student?")
     * @Param(type="post", name="isOrganizational", validation="bool", required=false,
     *        description="Whether the group is organizational (no assignments nor students).")
     * @Param(type="post", name="isExam", validation="bool", required=false,
     *        description="Whether the group is an exam group.")
     * @Param(type="post", name="localizedTexts", validation="array", required=false,
     *        description="Localized names and descriptions")
     * @Param(type="post", name="threshold", validation="numericint", required=false,
     *        description="A minimum percentage of points needed to pass the course")
     * @Param(type="post", name="pointsLimit", validation="numericint", required=false,
     *        description="A minimum of (absolute) points needed to pass the course")
     * @Param(type="post", name="noAdmin", validation="bool", required=false,
     *        description="If true, no admin is assigned to group (current user is assigned as admin by default.")
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     */
    public function actionAddGroup()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Validate group creation data
     * @POST
     * @Param(name="name", type="post", description="Name of the group")
     * @Param(name="locale", type="post", description="The locale of the name")
     * @Param(name="instanceId", type="post", description="Identifier of the instance where the group belongs")
     * @Param(name="parentGroupId", type="post", required=false, description="Identifier of the parent group")
     * @throws ForbiddenRequestException
     */
    public function actionValidateAddGroupData()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdateGroup(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        if (!$this->groupAcl->canUpdate($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Update group info
     * @POST
     * @Param(type="post", name="externalId", required=false,
     *        description="An informative, human readable indentifier of the group")
     * @Param(type="post", name="publicStats", validation="bool",
     *        description="Should students be able to see each other's results?")
     * @Param(type="post", name="detaining", validation="bool",
     *        required=false, description="Are students prevented from leaving the group on their own?")
     * @Param(type="post", name="isPublic", validation="bool",
     *        description="Should the group be visible to all student?")
     * @Param(type="post", name="threshold", validation="numericint", required=false,
     *        description="A minimum percentage of points needed to pass the course")
     * @Param(type="post", name="pointsLimit", validation="numericint", required=false,
     *        description="A minimum of (absolute) points needed to pass the course")
     * @Param(type="post", name="localizedTexts", validation="array", description="Localized names and descriptions")
     * @param string $id An identifier of the updated group
     * @throws InvalidArgumentException
     */
    public function actionUpdateGroup(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetOrganizational(string $id)
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
     * @Param(type="post", name="value", validation="bool", required=true, description="The value of the flag")
     * @param string $id An identifier of the updated group
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function actionSetOrganizational(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetArchived(string $id)
    {
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canArchive($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Set the 'isArchived' flag for a group
     * @POST
     * @Param(type="post", name="value", validation="bool", required=true, description="The value of the flag")
     * @param string $id An identifier of the updated group
     * @throws NotFoundException
     */
    public function actionSetArchived(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetExam(string $id)
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
     * @Param(type="post", name="value", validation="bool", required=true, description="The value of the flag")
     * @param string $id An identifier of the updated group
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function actionSetExam(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetExamPeriod(string $id)
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
     * can be editted (e.g., once an exam begins, the beginning may no longer be updated).
     * @POST
     * @Param(type="post", name="begin", validation="timestamp|null", required=false,
     *        description="When the exam begins (unix ts in the future, optional if update is performed).")
     * @Param(type="post", name="end", validation="timestamp", required=true,
     *        description="When the exam ends (unix ts in the future, no more than a day after 'begin').")
     * @Param(type="post", name="strict", validation="bool", required=false,
     *        description="Whether locked users are prevented from accessing other groups.")
     * @param string $id An identifier of the updated group
     * @throws NotFoundException
     */
    public function actionSetExamPeriod(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRemoveExamPeriod(string $id)
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
     * @param string $id An identifier of the updated group
     * @throws NotFoundException
     */
    public function actionRemoveExamPeriod(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRelocate(string $id, string $newParentId)
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

    public function noncheckGetExamLocks(string $id, string $examId)
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
     * @param string $id An identifier of the related group
     * @param string $examId An identifier of the exam
     */
    public function actionGetExamLocks(string $id, string $examId)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Relocate the group under a different parent.
     * @POST
     * @param string $id An identifier of the relocated group
     * @param string $newParentId An identifier of the new parent group
     * @throws NotFoundException
     * @throws BadRequestException
     */
    public function actionRelocate(string $id, string $newParentId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRemoveGroup(string $id)
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
     * @param string $id Identifier of the group
     */
    public function actionRemoveGroup(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDetail(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        if (!$this->groupAcl->canViewPublicDetail($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get details of a group
     * @GET
     * @param string $id Identifier of the group
     */
    public function actionDetail(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSubgroups(string $id)
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
     * @param string $id Identifier of the group
     * @DEPRECTATED Subgroup list is part of group view.
     */
    public function actionSubgroups(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckMembers(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        if (!$this->groupAcl->canViewDetail($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of members of a group
     * @GET
     * @param string $id Identifier of the group
     * @DEPRECATED Members are listed in group view.
     */
    public function actionMembers(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckAddMember(string $id, string $userId)
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
     * @Param(type="post", name="type", validation="string:1..", required=true,
     *        description="Identifier of membership type (admin, supervisor, ...)")
     * @param string $id Identifier of the group
     * @param string $userId Identifier of the supervisor
     */
    public function actionAddMember(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRemoveMember(string $id, string $userId)
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
     * @param string $id Identifier of the group
     * @param string $userId Identifier of the supervisor
     */
    public function actionRemoveMember(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckAssignments(string $id)
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
     * @param string $id Identifier of the group
     */
    public function actionAssignments(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckShadowAssignments(string $id)
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
     * @param string $id Identifier of the group
     */
    public function actionShadowAssignments(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckStats(string $id)
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
     * @param string $id Identifier of the group
     * @throws ForbiddenRequestException
     */
    public function actionStats(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckStudentsStats(string $id, string $userId)
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
     * @param string $id Identifier of the group
     * @param string $userId Identifier of the student
     * @throws BadRequestException
     */
    public function actionStudentsStats(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckStudentsSolutions(string $id, string $userId)
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
     * @param string $id Identifier of the group
     * @param string $userId Identifier of the student
     * @throws BadRequestException
     */
    public function actionStudentsSolutions(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckAddStudent(string $id, string $userId)
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
            throw new InvalidArgumentException("It is forbidden to add students to organizational groups");
        }
    }

    /**
     * Add a student to a group
     * @POST
     * @param string $id Identifier of the group
     * @param string $userId Identifier of the student
     */
    public function actionAddStudent(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRemoveStudent(string $id, string $userId)
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
     * @param string $id Identifier of the group
     * @param string $userId Identifier of the student
     */
    public function actionRemoveStudent(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckLockStudent(string $id, string $userId)
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
     * @param string $id Identifier of the group
     * @param string $userId Identifier of the student
     */
    public function actionLockStudent(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUnlockStudent(string $id, string $userId)
    {
        $group = $this->groups->findOrThrow($id);
        $user = $this->users->findOrThrow($userId);
        if ($user->getGroupLock()?->getId() !== $group->getId()) {
            throw new InvalidArgumentException("The user is not locked in given group.");
        }

        if (!$this->groupAcl->canUnlockStudent($group, $user)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Unlock a student currently locked in a group.
     * @DELETE
     * @param string $id Identifier of the group
     * @param string $userId Identifier of the student
     */
    public function actionUnlockStudent(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * @param Request $req
     * @param Group $group
     * @throws InvalidArgumentException
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
                        throw new InvalidArgumentException(
                            "There is already a group of this name, please choose a different one."
                        );
                    }
                }

                if (array_key_exists($lang, $localizations)) {
                    throw new InvalidArgumentException(sprintf("Duplicate entry for locale %s", $lang));
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
