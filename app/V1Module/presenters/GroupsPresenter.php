<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\Groups;
use App\Exception\BadRequestException;
use App\Exception\ForbiddenRequestException;
use App\Exception\NotFoundException;

/**
 * @LoggedIn
 */
class GroupsPresenter extends BasePresenter {

  /** @var Groups */
  private $groups;

  /**
   * @param Groups $groups  Groups repository
   */
  public function __construct(Groups $groups) {
    $this->groups = $groups;
  }

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
   * @GET
   */
  public function actionDetail(string $id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendSuccessResponse($group);
  }

  /**
   * @GET
   */
  public function actionMembers(string $id) {
    $group = $this->findGroupOrThrow($id);
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
    $this->sendSuccessResponse($group->getSupervisors()->toArray());
  }

  /**
   * @GET
   */
  public function actionStudents(string $id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendSuccessResponse($group->getStudents()->toArray());
  }

  /**
   * @GET
   */
  public function actionAssignments(string $id) {
    $group = $this->findGroupOrThrow($id);
    $this->sendSuccessResponse($group->getAssignments()->toArray());
  }

  /** @GET */
  public function actionStats(string $id) {
    $currentUser = $this->findUserOrThrow('me');
    $group = $this->findGroupOrThrow($id);

    if (!$group->isAdminOf($currentUser)
      && !$group->isSupervisorOf($currentUser)
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
      $user->removeStudentFrom($group);
      $this->users->flush();
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
   * @RequiredField(type="post", name="userId")
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
