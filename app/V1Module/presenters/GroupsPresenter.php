<?php

namespace App\V1Module\Presenters;

use App\Model\Entity\Group;
use App\Model\Repository\Groups;
use App\Model\Repository\Instances;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use Kdyby\Doctrine\EntityManager;

/**
 * @LoggedIn
 */
class GroupsPresenter extends BasePresenter {

  /** @inject @var Groups */
  public $groups;

  /** @inject @var Instances */
  public $instances;

  /** @inject @var EntityManager */
  public $em;

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
   * @UserIsAllowed(groups="create")
   * @Param(type="post", name="name", validation="string:2..")
   * @Param(type="post", name="description", required=FALSE)
   * @Param(type="post", name="instanceId", validation="string:36")
   * @Param(type="post", name="parentGroupId", validation="string:36", required=FALSE)
   */
  public function actionAddGroup() {
    $req = $this->getHttpRequest();
    $name = $req->getPost("name");
    $instanceId = $req->getPost("instanceId");
    $parentGroupId = $req->getPost("parentGroupId", NULL);
    $user = $this->findUserOrThrow("me");
    $instance = $this->instances->get($instanceId);

    if (!$user->belongsTo($instance)) {
      throw new ForbiddenRequestException("You cannot create group for instance '$instanceId'");
    }

    $description = $req->getPost("description");
    $parentGroup = !$parentGroupId ? NULL : $this->groups->get($parentGroupId);

    if ($parentGroup !== NULL && !$parentGroup->isAdminOf($user)) {
      throw new ForbiddenRequestException("Only group administrators can create group.");
    }

    if (!$this->groups->nameIsFree($name, $instance->getId(), $parentGroup !== NULL ? $parentGroup->getId() : NULL)) {
      throw new ForbiddenRequestException("There is already a group of this name, please choose a different one.");
    }

    $group = new Group($name, $description, $instance, $user, $parentGroup);

    $this->groups->persist($group);
    $this->sendSuccessResponse($group);
  }

  /**
   * @POST
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
   * @GET
   */
  public function actionDetail(string $id) {
    $group = $this->findGroupOrThrow($id);
    $user = $this->findUserOrThrow('me');

    if (!$user->belongsTo($group->getInstance())
      && !$this->user->isInRole("superadmin")) {
      throw new ForbiddenRequestException("You are not member of the same instance as the group.");
    }

    $this->sendSuccessResponse($group);
  }

  /**
   * @GET
   */
  public function actionSubgroups(string $id) {
    $group = $this->findGroupOrThrow($id);
    $user = $this->findUserOrThrow('me');

    if (!$group->isMemberOf($user)
      && !$this->user->isInRole("superadmin")) {
      throw new ForbiddenRequestException("You are not supervisor of this group.");
    }

    $this->sendSuccessResponse($group->getChildGroups()->toArray());
  }

  /**
   * @GET
   */
  public function actionMembers(string $id) {
    $group = $this->findGroupOrThrow($id);
    $user = $this->findUserOrThrow('me');

    if (!$group->isSupervisorOf($user)
      && !$this->user->isInRole("superadmin")) {
      throw new ForbiddenRequestException("You are not supervisor of this group.");
    }

    $this->sendSuccessResponse([
      "supervisors" => $group->getSupervisors()->toArray(),
      "students" => $group->getStudents()->toArray()
    ]);
  }

  /**
   * @GET
   */
  public function actionSupervisors(string $id) {
    $group = $this->findGroupOrThrow($id);
    $user = $this->findUserOrThrow('me');

    if (!$group->isSupervisorOf($user)
      && !$this->user->isInRole("superadmin")) {
      throw new ForbiddenRequestException("You are not supervisor of this group.");
    }

    $this->sendSuccessResponse($group->getSupervisors()->toArray());
  }

  /**
   * @GET
   */
  public function actionStudents(string $id) {
    $group = $this->findGroupOrThrow($id);
    $user = $this->findUserOrThrow('me');

    if (!$group->isSupervisorOf($user)
      && !$this->user->isInRole("superadmin")) {
      throw new ForbiddenRequestException("You are not supervisor of this group.");
    }

    $this->sendSuccessResponse($group->getStudents()->toArray());
  }

  /**
   * @GET
   */
  public function actionAssignments(string $id) {
    $group = $this->findGroupOrThrow($id);
    $user = $this->findUserOrThrow('me');

    if (!$group->isMemberOf($user)
      && !$this->user->isInRole("superadmin")) {
      throw new ForbiddenRequestException("You are not supervisor of this group.");
    }

    $this->sendSuccessResponse($group->getAssignments()->toArray());
  }

  /** @GET */
  public function actionStats(string $id) {
    $currentUser = $this->findUserOrThrow('me');
    $group = $this->findGroupOrThrow($id);

    if (!$group->isSupervisorOf($currentUser)
      && !$this->user->isInRole("superadmin")) {
      throw new ForbiddenRequestException("You cannot view these stats.");
    }

    $this->sendSuccessResponse(
      array_map(
        function ($student) use ($group) {
          return $group->getStudentsStats($student);
        },
        $group->getStudents()->toArray()
      )
    );
  }

  /** @GET */
  public function actionStudentsStats(string $id, string $userId) {
    $user = $this->findUserOrThrow($userId);
    $currentUser = $this->findUserOrThrow('me');
    $group = $this->findGroupOrThrow($id);

    if ($user->getId() !== $this->user->id
      && !$group->isSupervisorOf($currentUser)
      && !$this->user->isInRole("superadmin")) {
      throw new ForbiddenRequestException("You cannot view these stats.");
    }

    if ($group->isStudentOf($user) === FALSE) {
      throw new BadRequestException("User $userId is not student of $id");
    }

    $this->sendSuccessResponse($group->getStudentsStats($user));
  }

  public function actionStudentsBestResults(string $id, string $userId) {
    $user = $this->findUserOrThrow($userId);
    $currentUser = $this->findUserOrThrow('me');
    $group = $this->findGroupOrThrow($id);

    if ($user->getId() !== $this->user->id
      && !$group->isSupervisorOf($currentUser)
      && !$this->user->isInRole("superadmin")) {
      throw new ForbiddenRequestException("You cannot view these stats.");
    }

    if ($group->isStudentOf($user) === FALSE) {
      throw new BadRequestException("User $userId is not student of $id");
    }

    $statsMap = array_reduce(
      $group->getBestSolutions($user),
      function ($arr, $best) {
        if ($best !== NULL) {
          $arr[$best->getExerciseAssignment()->getId()] = [
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

  /** @POST */
  public function actionAddStudent(string $id, string $userId) {
    $user = $this->findUserOrThrow($userId);
    $currentUser = $this->findUserOrThrow('me');
    $group = $this->findGroupOrThrow($id);

    // check that the user has rights to join the group
    if ($user->getId() !== $currentUser->getId()
      && !$group->isSupervisorOf($currentUser)
      && !$this->user->isInRole("superadmin")) {
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

  /** @DELETE */
  public function actionRemoveStudent(string $id, string $userId) {
    $user = $this->findUserOrThrow($userId);
    $currentUser = $this->findUserOrThrow('me');
    $group = $this->findGroupOrThrow($id);

    // check that the user has rights to join the group
    if ($user->getId() !== $currentUser->getId()
      && !$group->isSupervisorOf($currentUser)
      && !$this->user->isInRole("superadmin")) {
      throw new ForbiddenRequestException("You cannot alter membership status of user '$userId' in group '$id'.");
    }

    // make sure that the user is student of the group 
    if ($group->isStudentOf($user) === TRUE) {
      $membership = $user->removeStudentFrom($group);
      $this->em->remove($membership);
      $this->em->flush();
    }

    // join the group
    $this->sendSuccessResponse($group);
  }

  /** @POST */
  public function actionAddSupervisor(string $id, string $userId) {
    $user = $this->findUserOrThrow($userId);
    $currentUser = $this->findUserOrThrow('me');
    $group = $this->findGroupOrThrow($id);

    // check that the user is the admin of the group
    if (!$group->isAdminOf($currentUser)
      && !$this->user->isInRole("superadmin")) {
      throw new ForbiddenRequestException("You cannot alter membership status of user '$userId' in group '$id'.");
    }

    // make sure that the user is not already supervisor of the group 
    if ($group->isSupervisorOf($user) === FALSE) {
      $user->makeSupervisorOf($group);
      $this->users->flush();
    }

    $this->sendSuccessResponse($group);
  }

  /** @DELETE */
  public function actionRemoveSupervisor(string $id, string $userId) {
    $user = $this->findUserOrThrow($userId);
    $currentUser = $this->findUserOrThrow('me');
    $group = $this->findGroupOrThrow($id);

    // check that the user has rights to join the group
    if (!$group->isSupervisorOf($currentUser)
      && !$this->user->isInRole("superadmin")) {
      throw new ForbiddenRequestException("You cannot alter membership status of user '$userId' in group '$id'.");
    }

    // make sure that the user is not already supervisor of the group 
    if ($group->isSupervisorOf($user) === TRUE) {
      $user->removeSupervisorFrom($group);
      $this->users->flush();
    }

    $this->sendSuccessResponse($group);
  }

  /** @GET */
  public function actionAdmin($id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendSuccessResponse($group->getAdmin());
  }

  /**
   * @POST
   * @Param(type="post", name="userId")
   */
  public function actionMakeAdmin(string $id) {
    $userId = $this->getHttpRequest()->getPost("userId");
    $user = $this->findUserOrThrow($userId);
    $currentUser = $this->findUserOrThrow("me");
    $group = $this->findGroupOrThrow($id);

    // check that the user has rights to join the group
    if (!$group->getAdmin() !== $currentUser
      && !$this->user->isInRole("superadmin")) {
      throw new ForbiddenRequestException("You cannot alter membership status of user '$userId' in group '$id'.");
    }

    // make sure that the user is not already member of the group 
    if ($group->getAdmin() !== $user) {
      $group->makeAdmin($user);
      $this->groups->flush();
    }

    $this->sendSuccessResponse($group);
  }

}
