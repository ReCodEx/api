<?php

namespace App\V1Module\Presenters;

use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Helpers\Localizations;
use App\Model\Entity\Assignment;
use App\Model\Entity\Exercise;
use App\Model\Entity\Group;
use App\Model\Entity\Instance;
use App\Model\Entity\LocalizedGroup;
use App\Model\Entity\User;
use App\Model\Repository\Groups;
use App\Model\Repository\Users;
use App\Model\Repository\Instances;
use App\Model\Repository\GroupMemberships;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Model\View\GroupViewFactory;
use App\Model\View\UserViewFactory;
use App\Security\ACL\IAssignmentPermissions;
use App\Security\ACL\IExercisePermissions;
use App\Security\ACL\IGroupPermissions;
use DateTime;
use Nette\Application\Request;

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
   * @var GroupViewFactory
   * @inject
   */
  public $groupViewFactory;

  /**
   * @var UserViewFactory
   * @inject
   */
  public $userViewFactory;

  public function checkDefault() {
    if (!$this->groupAcl->canViewAll()) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Get a list of all non-archived groups
   * @GET
   */
  public function actionDefault() {
    $groups = $this->groups->findUnarchived();
    $this->sendSuccessResponse($this->groupViewFactory->getGroups($groups));
  }

  public function checkAll() {
    if (!$this->groupAcl->canViewAll()) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Get a list of all groups
   * @GET
   */
  public function actionAll() {
    $groups = $this->groups->findAll();
    $this->sendSuccessResponse($this->groupViewFactory->getGroups($groups, false));
  }

  /**
   * Create a new group
   * @POST
   * @Param(type="post", name="instanceId", validation="string:36", description="An identifier of the instance where the group should be created")
   * @Param(type="post", name="externalId", required=false, description="An informative, human readable identifier of the group")
   * @Param(type="post", name="parentGroupId", validation="string:36", required=false, description="Identifier of the parent group (if none is given, a top-level group is created)")
   * @Param(type="post", name="publicStats", validation="bool", required=false, description="Should students be able to see each other's results?")
   * @Param(type="post", name="isPublic", validation="bool", required=false, description="Should the group be visible to all student?")
   * @Param(type="post", name="localizedTexts", validation="array", required=false, description="Localized names and descriptions")
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   */
  public function actionAddGroup() {
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
    $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);

    $group = new Group($externalId, $instance, $user, $parentGroup, $publicStats, $isPublic);
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
  public function actionValidateAddGroupData() {
    $req = $this->getRequest();
    $name = $req->getPost("name");
    $locale = $req->getPost("locale");
    $parentGroupId = $req->getPost("parentGroupId");
    $instance = $this->instances->findOrThrow($req->getPost("instanceId"));
    $parentGroup = $parentGroupId !== null ? $this->groups->findOrThrow($parentGroupId) : $instance->getRootGroup();

    if (!$this->groupAcl->canAddSubgroup($parentGroup)) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse([
      "groupNameIsFree" => count($this->groups->findByName($locale, $name, $instance, $parentGroup)) === 0
    ]);
  }

  public function checkUpdateGroup(string $id) {
    $group = $this->groups->findOrThrow($id);
    if (!$this->groupAcl->canUpdate($group)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Update group info
   * @POST
   * @Param(type="post", name="externalId", required=false, description="An informative, human readable indentifier of the group")
   * @Param(type="post", name="publicStats", validation="bool", description="Should students be able to see each other's results?")
   * @Param(type="post", name="isPublic", validation="bool", description="Should the group be visible to all student?")
   * @Param(type="post", name="hasThreshold", validation="bool", description="True if threshold was given, false if it should be unset")
   * @Param(type="post", name="threshold", validation="numericint", required=false, description="A minimum percentage of points needed to pass the course")
   * @Param(type="post", name="localizedTexts", validation="array", description="Localized names and descriptions")
   * @param string $id An identifier of the updated group
   * @throws InvalidArgumentException
   */
  public function actionUpdateGroup(string $id) {
    $req = $this->getRequest();
    $publicStats = filter_var($req->getPost("publicStats"), FILTER_VALIDATE_BOOLEAN);
    $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);
    $hasThreshold = filter_var($req->getPost("hasThreshold"), FILTER_VALIDATE_BOOLEAN);

    $group = $this->groups->findOrThrow($id);
    $group->setExternalId($req->getPost("externalId"));
    $group->setPublicStats($publicStats);
    $group->setIsPublic($isPublic);

    if ($hasThreshold) {
      $threshold = $req->getPost("threshold") !== null ? $req->getPost("threshold") / 100 : $group->getThreshold();
      $group->setThreshold($threshold);
    } else {
      $group->setThreshold(null);
    }

    $this->updateLocalizations($req, $group);

    $this->groups->persist($group);
    $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
  }

  public function checkSetOrganizational(string $id) {
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
   * @throws InvalidArgumentException
   * @throws NotFoundException
   */
  public function actionSetOrganizational(string $id) {
    $group = $this->groups->findOrThrow($id);
    $isOrganizational = filter_var($this->getRequest()->getPost("value"), FILTER_VALIDATE_BOOLEAN);

    if ($group->getStudents()->count() > 0 && $isOrganizational) {
      throw new InvalidArgumentException("The group already contains students");
    }

    $group->setOrganizational($isOrganizational);
    $this->groups->persist($group);
    $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
  }

  public function checkSetArchived(string $id) {
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
  public function actionSetArchived(string $id) {
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

  public function checkRemoveGroup(string $id) {
    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canRemove($group)) {
      throw new ForbiddenRequestException();
    }

    if ($group->getChildGroups()->count() !== 0) {
      throw new ForbiddenRequestException("There are subgroups of group '$id'. Please remove them first.");
    } else if ($group->getInstance() !== null && $group->getInstance()->getRootGroup() === $group) {
      throw new ForbiddenRequestException("Group '$id' is the root group of instance '{$group->getInstance()->getId()}' and root groups cannot be deleted.");
    }
  }

  /**
   * Delete a group
   * @DELETE
   * @param string $id Identifier of the group
   */
  public function actionRemoveGroup(string $id) {
    $group = $this->groups->findOrThrow($id);

    $this->groups->remove($group);
    $this->groups->flush();

    $this->sendSuccessResponse("OK");
  }

  public function checkDetail(string $id) {
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
  public function actionDetail(string $id) {
    $group = $this->groups->findOrThrow($id);
    $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
  }

  public function checkSubgroups(string $id) {
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
  public function actionSubgroups(string $id) {
    /** @var Group $group */
    $group = $this->groups->findOrThrow($id);

    $subgroups = array_filter(
      $group->getAllSubgroups(),
      function (Group $subgroup) {
        return $this->groupAcl->canViewPublicDetail($subgroup);
      });

    $this->sendSuccessResponse($this->groupViewFactory->getGroups($subgroups));
  }

  public function checkMembers(string $id) {
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
  public function actionMembers(string $id) {
    $group = $this->groups->findOrThrow($id);

    $this->sendSuccessResponse([
      "supervisors" => $this->userViewFactory->getUsers($group->getSupervisors()->getValues()),
      "students" => $this->userViewFactory->getUsers($group->getStudents()->getValues())
    ]);
  }

  public function checkSupervisors(string $id) {
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
  public function actionSupervisors(string $id) {
    $group = $this->groups->findOrThrow($id);
    $this->sendSuccessResponse($this->userViewFactory->getUsers($group->getSupervisors()->getValues()));
  }

  public function checkStudents(string $id) {
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
  public function actionStudents(string $id) {
    $group = $this->groups->findOrThrow($id);
    $this->sendSuccessResponse($this->userViewFactory->getUsers($group->getStudents()->getValues()));
  }

  public function checkAssignments(string $id) {
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
  public function actionAssignments(string $id) {
    /** @var Group $group */
    $group = $this->groups->findOrThrow($id);

    $assignments = $group->getAssignments();
    $this->sendSuccessResponse(array_values(array_filter($assignments->getValues(), function (Assignment $assignment) {
      return $this->assignmentAcl->canViewDetail($assignment);
    })));
  }

  public function checkExercises(string $id) {
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
  public function actionExercises(string $id) {
    $group = $this->groups->findOrThrow($id);
    $exercises = array();

    while ($group !== null) {
      $groupExercises = $group->getExercises()->filter(function (Exercise $exercise) {
        return $this->exerciseAcl->canViewDetail($exercise);
      })->toArray();

      $exercises = array_merge($groupExercises, $exercises);
      $group = $group->getParentGroup();
    }

    $this->sendSuccessResponse($exercises);
  }

  public function checkStats(string $id) {
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
  public function actionStats(string $id) {
    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canViewStats($group)) {
      $user = $this->getCurrentUser();
      $stats = $this->groupViewFactory->getStudentsStats($group, $user);
      $this->sendSuccessResponse([$stats]);
    }

    $this->sendSuccessResponse(
      array_map(
        function ($student) use ($group) {
          return $this->groupViewFactory->getStudentsStats($group, $student);
        },
        $group->getStudents()->getValues()
      )
    );
  }

  public function checkStudentsStats(string $id, string $userId) {
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
  public function actionStudentsStats(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $group = $this->groups->findOrThrow($id);

    if ($group->isStudentOf($user) === false) {
      throw new BadRequestException("User $userId is not student of $id");
    }

    $this->sendSuccessResponse($this->groupViewFactory->getStudentsStats($group, $user));
  }

  public function checkAddStudent(string $id, string $userId) {
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
  public function actionAddStudent(string $id, string $userId) {
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

  public function checkRemoveStudent(string $id, string $userId) {
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
  public function actionRemoveStudent(string $id, string $userId) {
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

  public function checkAddSupervisor(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canAddSupervisor($group, $user)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Add a supervisor to a group
   * @POST
   * @param string $id Identifier of the group
   * @param string $userId Identifier of the supervisor
   * @throws ForbiddenRequestException
   */
  public function actionAddSupervisor(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $group = $this->groups->findOrThrow($id);

    // make sure that the user is not already supervisor of the group
    if ($group->isSupervisorOf($user) === false) {
      if ($user->getRole() === "student") {
        $user->setRole("supervisor");
      }
      $user->makeSupervisorOf($group);
      $this->users->flush();
      $this->groups->flush();
    }

    $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
  }

  public function checkRemoveSupervisor(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $group = $this->groups->findOrThrow($id);

    if (!$this->groupAcl->canRemoveSupervisor($group, $user)) {
      throw new ForbiddenRequestException();
    }

    // if supervisor is also admin, do not allow to remove his/hers supervisor privileges
    if ($group->isPrimaryAdminOf($user) === true) {
      throw new ForbiddenRequestException("Supervisor is admin of group and thus cannot be removed as supervisor.");
    }
  }

  /**
   * Remove a supervisor from a group
   * @DELETE
   * @param string $id Identifier of the group
   * @param string $userId Identifier of the supervisor
   */
  public function actionRemoveSupervisor(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $group = $this->groups->findOrThrow($id);

    // make sure that the user is really supervisor of the group
    if ($group->isSupervisorOf($user) === true) {
      $membership = $user->findMembershipAsSupervisor($group); // should be always there
      $this->groupMemberships->remove($membership);
      $this->groupMemberships->flush();

      // if user is not supervisor in any other group, lets downgrade his/hers privileges
      if (empty($user->findGroupMembershipsAsSupervisor())
        && $user->getRole() === "supervisor") {
        $user->setRole("student");
        $this->users->flush();
      }
    }

    $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
  }

  public function checkAdmins($id) {
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
  public function actionAdmins($id) {
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
  public function actionAddAdmin(string $id) {
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

  public function checkRemoveAdmin(string $id, string $userId) {
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
  public function actionRemoveAdmin(string $id, string $userId) {
    $user = $this->users->findOrThrow($userId);
    $group = $this->groups->findOrThrow($id);

    // delete admin and flush changes
    $group->removePrimaryAdmin($user);
    $this->groups->flush();
    $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
  }

  /**
   * @param $req
   * @param $group
   * @throws InvalidArgumentException
   */
  private function updateLocalizations(Request $req, Group $group): void
  {
    $localizedTexts = $req->getPost("localizedTexts");

    if (count($localizedTexts) > 0) {
      $localizations = [];

      foreach ($localizedTexts as $item) {
        $lang = $item["locale"];
        $otherGroups = $this->groups->findByName($lang, $item["name"], $group->getInstance(), $group->getParentGroup());

        foreach ($otherGroups as $otherGroup) {
          if ($otherGroup !== $group) {
            throw new InvalidArgumentException("There is already a group of this name, please choose a different one.");
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
        // Localizations::updateCollection only updates the inverse side of the relationship - Doctrine needs us to
        // update the owning side manually. We set it to null for all potentially removed localizations first.
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
