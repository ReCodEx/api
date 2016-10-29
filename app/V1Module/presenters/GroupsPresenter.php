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
use App\Exceptions\NotFoundException;

/**
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
   *
   * @param type $id
   * @return Group
   * @throws NotFoundException
   */
  protected function findGroupOrThrow($id) {
    $group = $this->groups->get($id);
    if (!$group) {
      throw new NotFoundException("Group $id");
    }

    return $group;
  }

  /**
   * @GET
   * @UserIsAllowed(groups="view-all")
   */
  public function actionDefault() {
    $groups = $this->groups->findAll();
    $this->sendSuccessResponse($groups);
  }

  /**
   * @POST
   * @UserIsAllowed(groups="add")
   * @Param(type="post", name="name", validation="string:2..")
   * @Param(type="post", name="description", required=FALSE)
   * @Param(type="post", name="instanceId", validation="string:36")
   * @Param(type="post", name="parentGroupId", validation="string:36", required=FALSE)
   * @Param(type="post", name="publicStats", validation="bool", required=FALSE)
   */
  public function actionAddGroup() {
    $req = $this->getHttpRequest();
    $name = $req->getPost("name");
    $instanceId = $req->getPost("instanceId");
    $parentGroupId = $req->getPost("parentGroupId", NULL);
    $user = $this->users->findCurrentUserOrThrow();
    $instance = $this->instances->get($instanceId);

    if (!$user->belongsTo($instance)) {
      throw new ForbiddenRequestException("You cannot create group for instance '$instanceId'");
    }

    $description = $req->getPost("description", "");
    $publicStats = $req->getPost("publicStats", TRUE);
    $parentGroup = !$parentGroupId ? NULL : $this->groups->get($parentGroupId);

    if ($parentGroup !== NULL && !$parentGroup->isAdminOf($user)) {
      throw new ForbiddenRequestException("Only group administrators can create group.");
    }

    if (!$this->groups->nameIsFree($name, $instance->getId(), $parentGroup !== NULL ? $parentGroup->getId() : NULL)) {
      throw new ForbiddenRequestException("There is already a group of this name, please choose a different one.");
    }

    $group = new Group($name, $description, $instance, $user, $parentGroup, $publicStats);

    $this->groups->persist($group);
    $this->sendSuccessResponse($group);
  }

  /**
   * @POST
   * @UserIsAllowed(groups="add")
   * @Param(name="name", type="post")
   * @Param(name="instanceId", type="post")
   * @Param(name="parentGroupId", type="post", required=false)
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
   * @DELETE
   * @UserIsAllowed(groups="remove")
   */
  public function actionRemoveGroup(string $id) {
    $user = $this->users->findCurrentUserOrThrow();
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
   * @GET
   * @UserIsAllowed(groups="view-detail")
   */
  public function actionDetail(string $id) {
    $group = $this->findGroupOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();

    if (!$user->belongsTo($group->getInstance())
      && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You are not member of the same instance as the group.");
    }

    $this->sendSuccessResponse($group);
  }

  /**
   * @GET
   * @UserIsAllowed(groups="view-subgroups")
   */
  public function actionSubgroups(string $id) {
    $group = $this->findGroupOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();

    if (!$group->isMemberOf($user)
      && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You are not allowed to view subgroups of this group.");
    }

    $this->sendSuccessResponse($group->getChildGroups()->getValues());
  }

  /**
   * @GET
   * @UserIsAllowed(groups="view-students")
   * @UserIsAllowed(groups="view-supervisors")
   */
  public function actionMembers(string $id) {
    $group = $this->findGroupOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();

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
   * @GET
   * @UserIsAllowed(groups="view-supervisors")
   */
  public function actionSupervisors(string $id) {
    $group = $this->findGroupOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();

    if (!$group->isSupervisorOf($user)
      && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You are not allowed to view supervisors of this group.");
    }

    $this->sendSuccessResponse($group->getSupervisors()->getValues());
  }

  /**
   * @GET
   * @UserIsAllowed(groups="view-students")
   */
  public function actionStudents(string $id) {
    $group = $this->findGroupOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();

    if (!$group->isMemberOf($user)) {
      throw new ForbiddenRequestException("You are not allowed to view students of this group.");
    }

    $this->sendSuccessResponse($group->getStudents()->getValues());
  }

  /**
   * @GET
   * @UserIsAllowed(groups="view-detail")
   */
  public function actionAssignments(string $id) {
    $group = $this->findGroupOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();

    if (!$group->isMemberOf($user)
      && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You are not allowed to view assignments of this group.");
    }

    $this->sendSuccessResponse($group->getAssignments()->getValues());
  }

  /**
   * @GET
   * @UserIsAllowed(groups="view-detail")
   */
  public function actionStats(string $id) {
    $currentUser = $this->users->findCurrentUserOrThrow();
    $group = $this->findGroupOrThrow($id);

    if (!$group->statsArePublic()
      && !$group->isSupervisorOf($currentUser)
      && $currentUser->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You cannot view these stats.");
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
   * @GET
   * @UserIsAllowed(groups="view-detail")
   */
  public function actionStudentsStats(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->users->findCurrentUserOrThrow();
    $group = $this->findGroupOrThrow($id);

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
   * @GET
   * @UserIsAllowed(groups="view-detail")
   */
  public function actionStudentsBestResults(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->users->findCurrentUserOrThrow();
    $group = $this->findGroupOrThrow($id);

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
   * @POST
   * @UserIsAllowed(groups="add-student")
   */
  public function actionAddStudent(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->users->findCurrentUserOrThrow();
    $group = $this->findGroupOrThrow($id);

    // check that the user has rights to join the group
    if ($user->getId() !== $currentUser->getId()
      && !$group->isSupervisorOf($currentUser)
      && $currentUser->getRole()->hasLimitedRights()) {
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
   * @DELETE
   * @UserIsAllowed(groups="remove-student")
   */
  public function actionRemoveStudent(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->users->findCurrentUserOrThrow();
    $group = $this->findGroupOrThrow($id);

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
   * @POST
   * @UserIsAllowed(groups="add-supervisor")
   */
  public function actionAddSupervisor(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->users->findCurrentUserOrThrow();
    $group = $this->findGroupOrThrow($id);

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
   * @DELETE
   * @UserIsAllowed(groups="remove-supervisor")
   */
  public function actionRemoveSupervisor(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->users->findCurrentUserOrThrow();
    $group = $this->findGroupOrThrow($id);

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
      if ($user->findGroupMembershipsAsSupervisor()->isEmpty()
          && $user->getRole()->isSupervisor()) {
        $user->setRole($this->roles->get(Role::STUDENT));
        $this->users->flush();
      }
    }

    $this->sendSuccessResponse($group);
  }

  /**
   * @GET
   * @UserIsAllowed(groups="view-admin")
   */
  public function actionAdmin($id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendSuccessResponse($group->getAdminIds());
  }

  /**
   * @POST
   * @UserIsAllowed(groups="set-admin")
   * @Param(type="post", name="userId")
   */
  public function actionMakeAdmin(string $id) {
    $userId = $this->getHttpRequest()->getPost("userId");
    $user = $this->users->findOrThrow($userId);
    $currentUser = $this->users->findCurrentUserOrThrow();
    $group = $this->findGroupOrThrow($id);

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
