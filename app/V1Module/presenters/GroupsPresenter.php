<?php

namespace App\V1Module\Presenters;

use App\Model\Entity\Group;
use App\Model\Entity\Instance;
use App\Model\Entity\Role;
use App\Model\Repository\Groups;
use App\Model\Repository\Users;
use App\Model\Repository\Instances;
use App\Model\Repository\Roles;
use App\Model\Repository\GroupMemberships;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Security\Identity;

/**
 * Endpoints for group manipulation
 * @LoggedIn
 */
class GroupsPresenter extends BasePresenter {

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
   * @var Roles
   * @inject
   */
  public $roles;

  /**
   * @var GroupMemberships
   * @inject
   */
  public $groupMemberships;

  /**
   * Get a list of all groups
   * @GET
   * @UserIsAllowed(groups="view-all")
   */
  public function actionDefault() {
    $groups = $this->groups->findAll();
    $this->sendSuccessResponse($groups);
  }

  /**
   * Create a new group
   * @POST
   * @UserIsAllowed(instances="add-group", groups="add-subgroup")
   * @Resource(instances="instanceId", groups="parentGroupId")
   * @Param(type="post", name="name", validation="string:2..", description="Name of the group")
   * @Param(type="post", name="description", required=FALSE, description="Description of the group")
   * @Param(type="post", name="instanceId", validation="string:36", description="An identifier of the instance where the group should be created")
   * @Param(type="post", name="externalId", required=FALSE, description="An informative, human readable indentifier of the group")
   * @Param(type="post", name="parentGroupId", validation="string:36", required=FALSE, description="Identifier of the parent group (if none is given, a top-level group is created)")
   * @Param(type="post", name="publicStats", validation="bool", required=FALSE, description="Should students be able to see each other's results?")
   * @Param(type="post", name="isPublic", validation="bool", required=FALSE, description="Should the group be visible to all student?")
   */
  public function actionAddGroup() {
    $req = $this->getRequest();
    $instanceId = $req->getPost("instanceId");
    $parentGroupId = $req->getPost("parentGroupId");
    $user = $this->getCurrentUser();

    /** @var Instance $instance */
    $instance = $this->instances->findOrThrow($instanceId);

    if (!$user->belongsTo($instance)) {
      throw new ForbiddenRequestException("You cannot create group for instance '$instanceId'");
    }

    $parentGroup = !$parentGroupId ? $instance->getRootGroup() : $this->groups->findOrThrow($parentGroupId);

    $name = $req->getPost("name");
    $externalId = $req->getPost("externalId") === NULL ? "" : $req->getPost("externalId");
    $description = $req->getPost("description") === NULL ? "" : $req->getPost("description");
    $publicStats = filter_var($req->getPost("publicStats"), FILTER_VALIDATE_BOOLEAN);
    $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);

    if (!$this->groups->nameIsFree($name, $instance->getId(), $parentGroup !== NULL ? $parentGroup->getId() : NULL)) {
      throw new ForbiddenRequestException("There is already a group of this name, please choose a different one.");
    }

    $group = new Group($name, $externalId, $description, $instance, $user, $parentGroup, $publicStats, $isPublic);
    $this->groups->persist($group);
    $this->groups->flush();
    $this->sendSuccessResponse($group);
  }

  /**
   * Validate group creation data
   * @POST
   * @UserIsAllowed(instances="add-group")
   * @Resource(instances="instanceId")
   * @Param(name="name", type="post", description="Name of the group")
   * @Param(name="instanceId", type="post", description="Identifier of the instance where the group belongs")
   * @Param(name="parentGroupId", type="post", required=FALSE, description="Identifier of the parent group")
   */
  public function actionValidateAddGroupData() {
    $req = $this->getRequest();
    $name = $req->getPost("name");
    $instanceId = $req->getPost("instanceId");
    $parentGroupId = $req->getPost("parentGroupId");

    if ($parentGroupId === NULL) {
      $instance = $this->instances->get($instanceId);
      $parentGroupId = $instance->getRootGroup() !== NULL ? $instance->getRootGroup()->getId() : NULL;
    }

    $this->sendSuccessResponse([
      "groupNameIsFree" => $this->groups->nameIsFree($name, $instanceId, $parentGroupId)
    ]);
  }

  /**
   * Update group info
   * @POST
   * @UserIsAllowed(groups="update")
   * @Resource(groups="id")
   * @Param(type="post", name="name", validation="string:2..", description="Name of the group")
   * @Param(type="post", name="description", required=FALSE, description="Description of the group")
   * @Param(type="post", name="externalId", required=FALSE, description="An informative, human readable indentifier of the group")
   * @Param(type="post", name="publicStats", validation="bool", required=FALSE, description="Should students be able to see each other's results?")
   * @Param(type="post", name="isPublic", validation="bool", required=FALSE, description="Should the group be visible to all student?")
   * @Param(type="post", name="threshold", validation="numericint", required=FALSE, description="A minimum percentage of points needed to pass the course")
   * @param string $id An identifier of the updated group
   * @throws ForbiddenRequestException
   */
  public function actionUpdateGroup(string $id) {
    $req = $this->getRequest();
    $publicStats = filter_var($req->getPost("publicStats"), FILTER_VALIDATE_BOOLEAN);
    $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);

    $group = $this->groups->findOrThrow($id);

    $group->setExternalId($req->getPost("externalId"));
    $group->setName($req->getPost("name"));
    $group->setDescription($req->getPost("description"));
    $group->setPublicStats($publicStats);
    $group->setIsPublic($isPublic);
    $treshold = $req->getPost("threshold") !== NULL ? $req->getPost("threshold") / 100 : $group->getThreshold();
    $group->setThreshold($treshold);

    $this->groups->persist($group);
    $this->sendSuccessResponse($group);
  }

  /**
   * Delete a group
   * @DELETE
   * @UserIsAllowed(groups="remove")
   * @Resource(groups="id")
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionRemoveGroup(string $id) {
    $group = $this->groups->findOrThrow($id);

    if ($group->getChildGroups()->count() !== 0) {
      throw new ForbiddenRequestException("There are subgroups of group '$id'. Please remove them first.");
    } else if ($group->getInstance() !== NULL && $group->getInstance()->getRootGroup() === $group) {
      throw new ForbiddenRequestException("Group '$id' is the root group of instance '{$group->getInstance()->getId()}' and root groups cannot be deleted.");
    }

    $this->groups->remove($group);
    $this->groups->flush();

    $this->sendSuccessResponse("OK");
  }

  /**
   * Get details of a group
   * @GET
   * @UserIsAllowed(groups="view-detail")
   * @Resource(groups="id")
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionDetail(string $id) {
    $group = $this->groups->findOrThrow($id);
    $this->sendSuccessResponse($group);
  }

  /**
   * Get a list of subgroups of a group
   * @GET
   * @UserIsAllowed(groups="view-subgroups")
   * @Resource(groups="id")
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionSubgroups(string $id) {
    $group = $this->groups->findOrThrow($id);

    /** @var Identity $identity */
    $identity = $this->user->getIdentity();
    if (!($identity instanceof Identity)) {
      throw new ForbiddenRequestException("Access forbidden");
    }

    $subgroups = array_values(
      array_filter(
        $group->getAllSubgroups(),
        function ($subgroup) use ($identity) {
          return $this->authorizator->isAllowed($identity, $subgroup, "view-detail");
        }
      )
    );
    $this->sendSuccessResponse($subgroups);
  }

  /**
   * Get a list of members of a group
   * @GET
   * @UserIsAllowed(groups="view-students")
   * @UserIsAllowed(groups="view-supervisors")
   * @Resource(groups="id")
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionMembers(string $id) {
    $group = $this->groups->findOrThrow($id);

    $this->sendSuccessResponse([
      "supervisors" => $group->getSupervisors()->getValues(),
      "students" => $group->getStudents()->getValues()
    ]);
  }

  /**
   * Get a list of supervisors in a group
   * @GET
   * @UserIsAllowed(groups="view-supervisors")
   * @Resource(groups="id")
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionSupervisors(string $id) {
    $group = $this->groups->findOrThrow($id);
    $this->sendSuccessResponse($group->getSupervisors()->getValues());
  }

  /**
   * Get a list of students in a group
   * @GET
   * @UserIsAllowed(groups="view-students")
   * @Resource(groups="id")
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionStudents(string $id) {
    $group = $this->groups->findOrThrow($id);
    $this->sendSuccessResponse($group->getStudents()->getValues());
  }

  /**
   * Get all exercise assignments for a group
   * @GET
   * @UserIsAllowed(groups="view-assignments")
   * @Resource(groups="id")
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionAssignments(string $id) {
    $group = $this->groups->findOrThrow($id);
    $user = $this->getCurrentUser();

    $assignments = $group->getAssignmentsForUser($user);
    $this->sendSuccessResponse($assignments->getValues());
  }

  /**
   * Get all exercises for a group
   * @GET
   * @UserIsAllowed(groups="view-exercises")
   * @Resource(groups="id")
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionExercises(string $id) {
    $group = $this->groups->findOrThrow($id);
    $user = $this->getCurrentUser();

    $exercises = $group->getExercisesForUser($user);
    $this->sendSuccessResponse($exercises->getValues());
  }

  /**
   * Get statistics of a group
   * @GET
   * @UserIsAllowed(groups="view-stats")
   * @Resource(groups="id")
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionStats(string $id) {
    $group = $this->groups->findOrThrow($id);

    $this->sendSuccessResponse(
      array_map(
        function ($student) use ($group) {
          return $group->getStudentsStats($student);
        },
        $group->getStudents()->getValues()
      )
    );
  }

  /**
   * Get statistics of a single student in a group
   * @GET
   * @UserIsAllowed(groups="view-stats")
   * @UserIsAllowed(users="view-stats")
   * @Resource(groups="id", users="userId")
   * @param string $id Identifier of the group
   * @param string $userId Identifier of the student
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   */
  public function actionStudentsStats(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->getCurrentUser();
    $group = $this->groups->findOrThrow($id);

    if ($user->getId() !== $this->getUser()->getId()
      && !$group->isSupervisorOf($currentUser)
      && $currentUser->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You cannot view these stats.");
    }

    if ($group->isStudentOf($user) === FALSE) {
      throw new BadRequestException("User $userId is not student of $id");
    }

    $this->sendSuccessResponse($group->getStudentsStats($user));
  }

  /**
   * Add a student to a group
   * @POST
   * @UserIsAllowed(groups="add-student")
   * @Resource(groups="id")
   * @param string $id Identifier of the group
   * @param string $userId Identifier of the student
   * @throws ForbiddenRequestException
   */
  public function actionAddStudent(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->getCurrentUser();
    $group = $this->groups->findOrThrow($id);

    // check if current user isn't trying to add someone to a private group without sufficient rights
    $currentUserHasRights = $group->isSupervisorOf($currentUser) || !$currentUser->getRole()->hasLimitedRights();

    if ($group->isPrivate() && !$currentUserHasRights) {
      throw new ForbiddenRequestException("You cannot add user '$userId' to private group '$id'.");
    }

    // check that the user has rights to join the group
    if ($user->getId() !== $currentUser->getId() && !$currentUserHasRights) {
      throw new ForbiddenRequestException("You cannot alter membership status of user '$userId' in group '$id'.");
    }

    // make sure that the user is not already member of the group
    if ($group->isStudentOf($user) === FALSE) {
      $user->makeStudentOf($group);
      $this->groups->flush();
    }

    // join the group
    $this->sendSuccessResponse($group);
  }

  /**
   * Remove a student from a group
   * @DELETE
   * @UserIsAllowed(groups="remove-student")
   * @Resource(groups="id")
   * @param string $id Identifier of the group
   * @param string $userId Identifier of the student
   * @throws ForbiddenRequestException
   */
  public function actionRemoveStudent(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->getCurrentUser();
    $group = $this->groups->findOrThrow($id);

    // check that the user has rights to join the group
    if ($user->getId() !== $currentUser->getId()
      && !$group->isSupervisorOf($currentUser)
      && $currentUser->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You cannot alter membership status of user '$userId' in group '$id'.");
    }

    // make sure that the user is student of the group
    if ($group->isStudentOf($user) === TRUE) {
      $membership = $user->findMembershipAsStudent($group);
      if ($membership) {
        $this->groups->remove($membership);
        $this->groups->flush();
      }
    }

    // join the group
    $this->sendSuccessResponse($group);
  }

  /**
   * Add a supervisor to a group
   * @POST
   * @UserIsAllowed(groups="add-supervisor")
   * @Resource(groups="id")
   * @param string $id Identifier of the group
   * @param string $userId Identifier of the supervisor
   * @throws ForbiddenRequestException
   */
  public function actionAddSupervisor(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->getCurrentUser();
    $group = $this->groups->findOrThrow($id);

    // check that the user is the admin of the group
    if (!$group->isAdminOf($currentUser)) {
      throw new ForbiddenRequestException("You cannot alter membership status of user '$userId' in group '$id'.");
    }

    // make sure that the user is not already supervisor of the group
    if ($group->isSupervisorOf($user) === FALSE) {
      if ($user->getRole()->isStudent()) {
        $user->setRole($this->roles->get(Role::SUPERVISOR));
      }
      $user->makeSupervisorOf($group);
      $this->users->flush();
      $this->groups->flush();
    }

    $this->sendSuccessResponse($group);
  }

  /**
   * Remove a supervisor from a group
   * @DELETE
   * @UserIsAllowed(groups="remove-supervisor")
   * @Resource(groups="id")
   * @param string $id Identifier of the group
   * @param string $userId Identifier of the supervisor
   * @throws ForbiddenRequestException
   */
  public function actionRemoveSupervisor(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->getCurrentUser();
    $group = $this->groups->findOrThrow($id);

    // check that the user has rights to join the group
    if (!$group->isSupervisorOf($currentUser)
      && $currentUser->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You cannot alter membership status of user '$userId' in group '$id'.");
    }

    // make sure that the user is really supervisor of the group
    if ($group->isSupervisorOf($user) === TRUE) {
      $membership = $user->findMembershipAsSupervisor($group); // should be always there
      $this->groupMemberships->remove($membership);
      $this->groupMemberships->flush();

      // if user is not supervisor in any other group, lets downgrade his/hers privileges
      if (empty($user->findGroupMembershipsAsSupervisor())
        && $user->getRole()->isSupervisor()) {
        $user->setRole($this->roles->get(Role::STUDENT));
        $this->users->flush();
      }
    }

    $this->sendSuccessResponse($group);
  }

  /**
   * Get identifiers of administrators of a group
   * @GET
   * @UserIsAllowed(groups="view-admin")
   * @Resource(groups="id")
   * @param string $id Identifier of the group
   */
  public function actionAdmin($id) {
    $group = $this->groups->findOrThrow($id);
    $this->sendSuccessResponse($group->getAdminsIds());
  }

  /**
   * Make a user an administrator of a group
   * @POST
   * @UserIsAllowed(groups="set-admin")
   * @Resource(groups="id")
   * @Param(type="post", name="userId", description="Identifier of a user to be made administrator")
   * @param string $id Identifier of the group
   * @throws ForbiddenRequestException
   */
  public function actionMakeAdmin(string $id) {
    $userId = $this->getRequest()->getPost("userId");
    $user = $this->users->findOrThrow($userId);
    $group = $this->groups->findOrThrow($id);

    // change admin of the group even if user is superadmin
    $group->makeAdmin($user);
    $this->groups->flush();
    $this->sendSuccessResponse($group);
  }

}
