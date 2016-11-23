<?php

namespace App\V1Module\Presenters;

use App\Model\Entity\Group;
use App\Model\Entity\Role;
use App\Model\Repository\Groups;
use App\Model\Repository\Users;
use App\Model\Repository\Instances;
use App\Model\Repository\Roles;
use App\Model\Repository\GroupMemberships;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;

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
   * @UserIsAllowed(groups="add")
   * @Param(type="post", name="name", validation="string:2..")
   * @Param(type="post", name="description", required=FALSE)
   * @Param(type="post", name="instanceId", validation="string:36")
   * @Param(type="post", name="externalId", required=FALSE)
   * @Param(type="post", name="parentGroupId", validation="string:36", required=FALSE)
   * @Param(type="post", name="publicStats", validation="bool", required=FALSE)
   * @Param(type="post", name="isPublic", validation="bool", required=FALSE)
   */
  public function actionAddGroup() {
    $req = $this->getHttpRequest();
    $name = $req->getPost("name");
    $instanceId = $req->getPost("instanceId");
    $externalId = $req->getPost("externalId");
    $parentGroupId = $req->getPost("parentGroupId", NULL);
    $user = $this->getCurrentUser();
    $instance = $this->instances->get($instanceId);

    if (!$user->belongsTo($instance)) {
      throw new ForbiddenRequestException("You cannot create group for instance '$instanceId'");
    }

    $description = $req->getPost("description", "");
    $publicStats = $req->getPost("publicStats", TRUE);
    $isPublic = $req->getPost("isPublic") === NULL ? TRUE : $req->getPost("isPublic");
    $parentGroup = !$parentGroupId ? NULL : $this->groups->get($parentGroupId);

    if ($parentGroup !== NULL && !$parentGroup->isAdminOf($user)) {
      throw new ForbiddenRequestException("Only group administrators can create group.");
    }

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
   * @UserIsAllowed(groups="add")
   * @Param(name="name", type="post", description="Name of the group")
   * @Param(name="instanceId", type="post", description="Identifier of the instance where the group belongs")
   * @Param(name="parentGroupId", type="post", required=false, description="Identifier of the parent group")
   */
  public function actionValidateAddGroupData() {
    $req = $this->getHttpRequest();
    $name = $req->getPost("name");
    $instanceId = $req->getPost("instanceId");
    $parentGroupId = $req->getPost("parentGroupId", NULL);

    $this->sendSuccessResponse([
      "groupNameIsFree" => $this->groups->nameIsFree($name, $instanceId, $parentGroupId)
    ]);
  }

  /**
   * Update group info
   * @POST
   * @UserIsAllowed(groups="update")
   * @Param(type="post", name="name", validation="string:2..")
   * @Param(type="post", name="externalId", validation="string:1..")
   * @Param(type="post", name="description", validation="string")
   * @Param(type="post", name="publicStats", validation="bool")
   * @Param(type="post", name="isPublic", validation="bool")
   * @Param(type="post", name="threshold", validation="numericint", required=FALSE)
   */
  public function actionUpdateGroup(string $id) {
    $req = $this->getHttpRequest();
    $publicStats = filter_var($req->getPost("publicStats"), FILTER_VALIDATE_BOOLEAN);
    $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);

    $user = $this->getCurrentUser();
    $group = $this->groups->findOrThrow($id);

    if (!$group->isAdminOf($user) && !$user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("Only group administrators can update group detail.");
    }

    $group->setName($req->getPost("externalId"));
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
   */
  public function actionRemoveGroup(string $id) {
    $user = $this->getCurrentUser();
    $group = $this->groups->findOrThrow($id);

    if ($group->isAdminOf($user) === FALSE) {
      throw new ForbiddenRequestException("Only administrator of a group can remove it");
    }
    if ($group->getChildGroups()->count() !== 0) {
      throw new ForbiddenRequestException("There are subgroups of group '$id'. Please remove them first.");
    }

    $this->groups->remove($group);
    $this->groups->flush();

    $this->sendSuccessResponse("OK");
  }

  /**
   * Get details of a group
   * @GET
   * @UserIsAllowed(groups="view-detail")
   */
  public function actionDetail(string $id) {
    $group = $this->groups->findOrThrow($id);
    $user = $this->getCurrentUser();

    if (!$user->belongsTo($group->getInstance())
      && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You are not member of the same instance as the group.");
    }

    if (!$group->canUserAccessGroupDetail($user)) {
      throw new ForbiddenRequestException("You are not allowed to view this group detail.");
    }

    $this->sendSuccessResponse($group);
  }

  /**
   * Get a list of subgroups of a group
   * @GET
   * @UserIsAllowed(groups="view-subgroups")
   */
  public function actionSubgroups(string $id) {
    $group = $this->groups->findOrThrow($id);
    $user = $this->getCurrentUser();

    if (!$user->belongsTo($group->getInstance())
      && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You are not member of the same instance as the group.");
    }

    if (!$group->canUserAccessGroupDetail($user)) {
      throw new ForbiddenRequestException("You are not allowed to view this group detail.");
    }

    $subgroups = array_values(
      array_filter(
        $group->getAllSubgroups(),
        function ($subgroup) use ($user) {
          return $subgroup->canUserAccessGroupDetail($user);
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
   */
  public function actionMembers(string $id) {
    $group = $this->groups->findOrThrow($id);
    $user = $this->getCurrentUser();

    if (!$group->isSupervisorOf($user)
      && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You are not allowed to view members of this group.");
    }

    $this->sendSuccessResponse([
      "supervisors" => $group->getSupervisors()->getValues(),
      "students" => $group->getStudents()->getValues()
    ]);
  }

  /**
   * Get a list of supervisors in a group
   * @GET
   * @UserIsAllowed(groups="view-supervisors")
   */
  public function actionSupervisors(string $id) {
    $group = $this->groups->findOrThrow($id);
    $user = $this->getCurrentUser();

    if ($group->isPrivate() && !$group->isMemberOf($user)
      && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You are not allowed to view supervisors of this group.");
    }

    $this->sendSuccessResponse($group->getSupervisors()->getValues());
  }

  /**
   * Get a list of students in a group
   * @GET
   * @UserIsAllowed(groups="view-students")
   */
  public function actionStudents(string $id) {
    $group = $this->groups->findOrThrow($id);
    $user = $this->getCurrentUser();

    if (!$group->isMemberOf($user)) {
      throw new ForbiddenRequestException("You are not allowed to view students of this group.");
    }

    $this->sendSuccessResponse($group->getStudents()->getValues());
  }

  /**
   * Get all exercise assignments for a group
   * @GET
   * @UserIsAllowed(groups="view-detail")
   */
  public function actionAssignments(string $id) {
    $group = $this->groups->findOrThrow($id);
    $user = $this->getCurrentUser();

    if (!$group->isMemberOf($user)
      && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You are not allowed to view assignments of this group.");
    }

    $assignments = $group->getAssignmentsForUser($user);
    $this->sendSuccessResponse($assignments->getValues());
  }

  /**
   * Get statistics of a group
   * @GET
   * @UserIsAllowed(groups="view-detail")
   */
  public function actionStats(string $id) {
    $currentUser = $this->getCurrentUser();
    $group = $this->groups->findOrThrow($id);

    if (!$group->statsArePublic()
      && !$group->isSupervisorOf($currentUser)
      && $currentUser->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You cannot view these stats.");
    }

    if (!$group->canUserAccessGroupDetail($currentUser)) {
      throw new ForbiddenRequestException("You are not allowed to view this group detail.");
    }

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
   * @UserIsAllowed(groups="view-detail")
   */
  public function actionStudentsStats(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->getCurrentUser();
    $group = $this->groups->findOrThrow($id);

    if ($user->getId() !== $this->user->id
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
   * Get the best solution of an assignment for a group submitted by a student
   * @GET
   * @UserIsAllowed(groups="view-detail")
   */
  public function actionStudentsBestResults(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->getCurrentUser();
    $group = $this->groups->findOrThrow($id);

    if ($user->getId() !== $this->user->id
      && !$group->isSupervisorOf($currentUser)
      && $currentUser->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You cannot view these stats.");
    }

    if ($group->isStudentOf($user) === FALSE) {
      throw new BadRequestException("User $userId is not student of $id");
    }

    $statsMap = array_reduce(
      $group->getBestSolutions($user),
      function ($arr, $best) {
        if ($best !== NULL) {
          $arr[$best->getAssignment()->getId()] = [
            "submissionId" => $best->getId(),
            "score" => $best->getEvaluation()->getScore(),
            "points" => $best->getEvaluation()->getPoints(),
            "bonusPoints" => $best->getEvaluation()->getBonusPoints()
          ];
        }

        return $arr;
      },
      []
    );

    $this->sendSuccessResponse($statsMap);
  }

  /**
   * Add a student to a group
   * @POST
   * @UserIsAllowed(groups="add-student")
   */
  public function actionAddStudent(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->getCurrentUser();
    $group = $this->groups->findOrThrow($id);

    // check if current user isn't trying to add someone to a private group without sufficient rights
    $currentUserHasRights = $group->isSupervisorOf($currentUser) || !$currentUser->role->hasLimitedRights();

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
   */
  public function actionAddSupervisor(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->getCurrentUser();
    $group = $this->groups->findOrThrow($id);

    // check that the user is the admin of the group
    if (!$group->isAdminOf($currentUser)
      && $currentUser->getRole()->hasLimitedRights()) {
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
   */
  public function actionAdmin($id) {
    $group = $this->groups->findOrThrow($id);
    $this->sendSuccessResponse($group->getAdminIds());
  }

  /**
   * Make a user an administrator of a group
   * @POST
   * @UserIsAllowed(groups="set-admin")
   * @Param(type="post", name="userId", description="Identifier of a user to be made administrator")
   */
  public function actionMakeAdmin(string $id) {
    $userId = $this->getHttpRequest()->getPost("userId");
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->getCurrentUser();
    $group = $this->groups->findOrThrow($id);

    // check that the user has rights to join the group
    if ($group->isAdminOf($currentUser) === FALSE
      && $currentUser->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You cannot alter membership status of user '$userId' in group '$id'.");
    }

    // make sure that the user is not already member of the group
    if ($group->isAdminOf($user) === FALSE) {
      $group->makeAdmin($user);
      $this->groups->flush();
    }

    $this->sendSuccessResponse($group);
  }

}
