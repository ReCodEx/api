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
use App\Model\Entity\Exercise;
use App\Model\Entity\Group;
use App\Model\Entity\Instance;
use App\Model\Entity\LocalizedGroup;
use App\Model\Repository\Groups;
use App\Model\Repository\Users;
use App\Model\Repository\Instances;
use App\Model\Repository\GroupMemberships;
use App\Model\View\ExerciseViewFactory;
use App\Model\View\AssignmentViewFactory;
use App\Model\View\ShadowAssignmentViewFactory;
use App\Model\View\GroupViewFactory;
use App\Model\View\UserViewFactory;
use App\Security\ACL\IAssignmentPermissions;
use App\Security\ACL\IShadowAssignmentPermissions;
use App\Security\ACL\IExercisePermissions;
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
     * @var IAssignmentPermissions
     * @inject
     */
    public $assignmentAcl;

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
     * @var ExerciseViewFactory
     * @inject
     */
    public $exerciseViewFactory;

    /**
     * @var AssignmentViewFactory
     * @inject
     */
    public $assignmentViewFactory;

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
     * @param int|null $archivedAgeLimit Only works in combination with archived;
     *  restricts maximal age (how long the groups have been in archive) in days.
     */
    public function actionDefault(
        string $instanceId = null,
        bool $ancestors = false,
        string $search = null,
        bool $archived = false,
        bool $onlyArchived = false,
        int $archivedAgeLimit = null
    ) {
        $user = $this->groupAcl->canViewAll() ? null : $this->getCurrentUser(); // user for membership restriction
        $groups = $this->groups->findFiltered($user, $instanceId, $search, $archived, $onlyArchived, $archivedAgeLimit);
        if ($ancestors) {
            $groups = $this->groups->groupsAncestralClosure($groups);
        }
        $this->sendSuccessResponse($this->groupViewFactory->getGroups($groups, false));
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
     * @Param(type="post", name="localizedTexts", validation="array", required=false,
     *        description="Localized names and descriptions")
     * @Param(type="post", name="hasThreshold", validation="bool",
     *        description="True if threshold was given, false if it should be unset")
     * @Param(type="post", name="threshold", validation="numericint", required=false,
     *        description="A minimum percentage of points needed to pass the course")
     * @Param(type="post", name="noAdmin", validation="bool", required=false,
     *        description="If true, no admin is assigned to group (current user is assigned as admin by default.")
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     */
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
            throw new InvalidArgumentException("It is not permitted to create subgroups in archived groups");
        }

        if (!$this->groupAcl->canAddSubgroup($parentGroup)) {
            throw new ForbiddenRequestException("You are not allowed to add subgroups to this group");
        }

        $externalId = $req->getPost("externalId") === null ? "" : $req->getPost("externalId");
        $publicStats = filter_var($req->getPost("publicStats"), FILTER_VALIDATE_BOOLEAN);
        $detaining = filter_var($req->getPost("detaining"), FILTER_VALIDATE_BOOLEAN);
        $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);
        $isOrganizational = filter_var($req->getPost("isOrganizational"), FILTER_VALIDATE_BOOLEAN);
        $hasThreshold = filter_var($req->getPost("hasThreshold"), FILTER_VALIDATE_BOOLEAN);
        $noAdmin = filter_var($req->getPost("noAdmin"), FILTER_VALIDATE_BOOLEAN);

        $group = new Group(
            $externalId,
            $instance,
            $noAdmin ? null : $user,
            $parentGroup,
            $publicStats,
            $isPublic,
            $isOrganizational,
            $detaining
        );
        if ($hasThreshold) {
            $threshold = $req->getPost("threshold") !== null
                ? $req->getPost("threshold") / 100
                : $group->getThreshold();
            $group->setThreshold($threshold);
        }
        $this->updateLocalizations($req, $group);

        $this->groups->persist($group, false);
        $this->groups->flush();

        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
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
     * @Param(type="post", name="externalId", required=false,
     *        description="An informative, human readable indentifier of the group")
     * @Param(type="post", name="publicStats", validation="bool",
     *        description="Should students be able to see each other's results?")
     * @Param(type="post", name="detaining", validation="bool",
     *        required=false, description="Are students prevented from leaving the group on their own?")
     * @Param(type="post", name="isPublic", validation="bool",
     *        description="Should the group be visible to all student?")
     * @Param(type="post", name="hasThreshold", validation="bool",
     *        description="True if threshold was given, false if it should be unset")
     * @Param(type="post", name="threshold", validation="numericint", required=false,
     *        description="A minimum percentage of points needed to pass the course")
     * @Param(type="post", name="localizedTexts", validation="array", description="Localized names and descriptions")
     * @param string $id An identifier of the updated group
     * @throws InvalidArgumentException
     */
    public function actionUpdateGroup(string $id)
    {
        $req = $this->getRequest();
        $publicStats = filter_var($req->getPost("publicStats"), FILTER_VALIDATE_BOOLEAN);
        $detaining = filter_var($req->getPost("detaining"), FILTER_VALIDATE_BOOLEAN);
        $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);
        $hasThreshold = filter_var($req->getPost("hasThreshold"), FILTER_VALIDATE_BOOLEAN);

        $group = $this->groups->findOrThrow($id);
        $group->setExternalId($req->getPost("externalId"));
        $group->setPublicStats($publicStats);
        $group->setDetaining($detaining);
        $group->setIsPublic($isPublic);

        if ($hasThreshold) {
            $threshold = $req->getPost("threshold") !== null ? $req->getPost("threshold") / 100 : $group->getThreshold(
            );
            $group->setThreshold($threshold);
        } else {
            $group->setThreshold(null);
        }

        $this->updateLocalizations($req, $group);

        $this->groups->persist($group);
        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkSetOrganizational(string $id)
    {
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canUpdate($group)) {
            throw new ForbiddenRequestException();
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
     * @Param(type="post", name="value", validation="bool", required=true, description="The value of the flag")
     * @param string $id An identifier of the updated group
     * @throws NotFoundException
     */
    public function actionSetArchived(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        $archive = filter_var($this->getRequest()->getPost("value"), FILTER_VALIDATE_BOOLEAN);

        if ($archive) {
            $group->archive(new DateTime());
        } else {
            $group->undoArchivation();
        }

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
     * @param string $id Identifier of the group
     */
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
     * @param string $id Identifier of the group
     */
    public function actionDetail(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkSubgroups(string $id)
    {
        /** @var Group $group */
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canViewSubgroups($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of subgroups of a group
     * @GET
     * @param string $id Identifier of the group
     */
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

        if (!($this->groupAcl->canViewStudents($group) && $this->groupAcl->canViewSupervisors($group))) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of members of a group
     * @GET
     * @param string $id Identifier of the group
     */
    public function actionMembers(string $id)
    {
        $group = $this->groups->findOrThrow($id);

        $this->sendSuccessResponse(
            [
                "supervisors" => $this->userViewFactory->getUsers($group->getSupervisors()->getValues()),
                "students" => $this->userViewFactory->getUsers($group->getStudents()->getValues())
            ]
        );
    }

    public function checkSupervisors(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        if (!$this->groupAcl->canViewSupervisors($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of supervisors in a group
     * @GET
     * @param string $id Identifier of the group
     */
    public function actionSupervisors(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        $this->sendSuccessResponse($this->userViewFactory->getUsers($group->getSupervisors()->getValues()));
    }

    public function checkStudents(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        if (!$this->groupAcl->canViewStudents($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of students in a group
     * @GET
     * @param string $id Identifier of the group
     */
    public function actionStudents(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        $this->sendSuccessResponse($this->userViewFactory->getUsers($group->getStudents()->getValues()));
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
     * @param string $id Identifier of the group
     */
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
     * @param string $id Identifier of the group
     */
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

    public function checkExercises(string $id)
    {
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canViewExercises($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get all exercises for a group
     * @GET
     * @param string $id Identifier of the group
     */
    public function actionExercises(string $id)
    {
        $group = $this->groups->findOrThrow($id);
        $exercises = array();

        while ($group !== null) {
            $groupExercises = $group->getExercises()->filter(
                function (Exercise $exercise) {
                    return $this->exerciseAcl->canViewDetail($exercise);
                }
            )->toArray();

            $exercises = array_merge($groupExercises, $exercises);
            $group = $group->getParentGroup();
        }

        $this->sendSuccessResponse(array_map([$this->exerciseViewFactory, "getExercise"], $exercises));
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
     * @param string $id Identifier of the group
     * @throws ForbiddenRequestException
     */
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
     * @param string $id Identifier of the group
     * @param string $userId Identifier of the student
     * @throws BadRequestException
     */
    public function actionStudentsStats(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        if ($group->isStudentOf($user) === false) {
            throw new BadRequestException("User $userId is not student of $id");
        }

        $this->sendSuccessResponse($this->groupViewFactory->getStudentsStats($group, $user));
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
     * @param string $id Identifier of the group
     * @param string $userId Identifier of the student
     */
    public function actionRemoveStudent(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        // make sure that the user is student of the group
        if ($group->isStudentOf($user) === true) {
            $membership = $user->findMembershipAsStudent($group);
            if ($membership) {
                $this->groups->remove($membership);
                $this->groups->flush();
            }
        }

        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkAddSupervisor(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        /** @var IGroupPermissions $userAcl */
        $userAcl = $this->aclLoader->loadACLModule(
            IGroupPermissions::class,
            $this->authorizator,
            new Identity($user, null)
        );

        if (!$this->groupAcl->canAddSupervisor($group, $user) || !$userAcl->canBecomeSupervisor($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Add a supervisor to a group
     * @POST
     * @param string $id Identifier of the group
     * @param string $userId Identifier of the supervisor
     */
    public function actionAddSupervisor(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        // make sure that the user is not already supervisor of the group
        if ($group->isSupervisorOf($user) === false) {
            $user->makeSupervisorOf($group);
            $this->users->flush();
            $this->groups->flush();
        }

        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkRemoveSupervisor(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canRemoveSupervisor($group, $user)) {
            throw new ForbiddenRequestException();
        }

        // if supervisor is also admin, do not allow to remove his/hers supervisor privileges
        if ($group->isPrimaryAdminOf($user) === true) {
            throw new ForbiddenRequestException(
                "Supervisor is admin of group and thus cannot be removed as supervisor."
            );
        }
    }

    /**
     * Remove a supervisor from a group
     * @DELETE
     * @param string $id Identifier of the group
     * @param string $userId Identifier of the supervisor
     */
    public function actionRemoveSupervisor(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        // make sure that the user is really supervisor of the group
        if ($group->isSupervisorOf($user) === true) {
            $membership = $user->findMembershipAsSupervisor($group); // should be always there
            $this->groupMemberships->remove($membership);
            $this->groupMemberships->flush();
        }

        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkAdmins($id)
    {
        $group = $this->groups->findOrThrow($id);
        if (!$this->groupAcl->canViewAdmin($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get identifiers of administrators of a group
     * @GET
     * @param string $id Identifier of the group
     */
    public function actionAdmins($id)
    {
        $group = $this->groups->findOrThrow($id);
        $this->sendSuccessResponse($group->getAdminsIds());
    }

    /**
     * Make a user an administrator of a group
     * @POST
     * @Param(type="post", name="userId", description="Identifier of a user to be made administrator")
     * @param string $id Identifier of the group
     * @throws ForbiddenRequestException
     */
    public function actionAddAdmin(string $id)
    {
        $userId = $this->getRequest()->getPost("userId");
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canSetAdmin($group)) {
            throw new ForbiddenRequestException();
        }

        // user has to be supervisor first
        if ($group->isSupervisorOf($user) === false) {
            throw new ForbiddenRequestException("User has to be supervisor before assigning as an admin");
        }

        // make user admin of the group
        $group->removePrimaryAdmin($user);
        $group->addPrimaryAdmin($user);
        $this->groups->flush();
        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
    }

    public function checkRemoveAdmin(string $id, string $userId)
    {
        $group = $this->groups->findOrThrow($id);

        if (!$this->groupAcl->canSetAdmin($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Remove user as an administrator of a group
     * @DELETE
     * @param string $id Identifier of the group
     * @param string $userId identifier of admin
     */
    public function actionRemoveAdmin(string $id, string $userId)
    {
        $user = $this->users->findOrThrow($userId);
        $group = $this->groups->findOrThrow($id);

        // delete admin and flush changes
        $group->removePrimaryAdmin($user);
        $this->groups->flush();
        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
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
