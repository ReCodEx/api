<?php
namespace App\V1Module\Presenters;


use App\Exceptions\ApiException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Helpers\SisCourseRecord;
use App\Helpers\SisHelper;
use App\Model\Entity\Group;
use App\Model\Entity\LocalizedGroup;
use App\Model\Entity\SisGroupBinding;
use App\Model\Entity\SisValidTerm;
use App\Model\Entity\User;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Groups;
use App\Model\Repository\Instances;
use App\Model\Repository\SisGroupBindings;
use App\Model\Repository\SisValidTerms;
use App\Model\View\GroupViewFactory;
use App\Security\ACL\ISisPermissions;
use App\Security\ACL\SisGroupContext;
use App\Security\ACL\SisIdWrapper;
use DateTime;
use Exception;
use Nette\Utils\Json;

/**
 * @LoggedIn
 */
class SisPresenter extends BasePresenter {
  /**
   * @var SisHelper
   * @inject
   */
  public $sisHelper;

  /**
   * @var ExternalLogins
   * @inject
   */
  public $externalLogins;

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
   * @var SisGroupBindings
   * @inject
   */
  public $sisGroupBindings;

  /**
   * @var SisValidTerms
   * @inject
   */
  public $sisValidTerms;

  /**
   * @var GroupViewFactory
   * @inject
   */
  public $groupViewFactory;

  /**
   * @var ISisPermissions
   * @inject
   */
  public $sisAcl;


  /**
   * @GET
   * @throws ForbiddenRequestException
   */
  public function actionStatus() {
    $login = $this->externalLogins->findByUser($this->getCurrentUser(), "cas-uk");
    $now = new DateTime();
    $terms = [];

    /** @var SisValidTerm $term */
    foreach ($this->sisValidTerms->findAll() as $term) {
      $month = intval($now->format('w'));
      $terms[] = [
        'year' => $term->getYear(),
        'term' => $term->getTerm(),
        'starting' => $term->isAdvertised($now)
      ];
    }

    $this->sendSuccessResponse([
      "accessible" => $login !== null,
      "terms" => $terms
    ]);
  }

  /**
   * Get a list of all registered SIS terms
   * @GET
   * @throws ForbiddenRequestException
   */
  public function actionGetTerms() {
    if (!$this->sisAcl->canViewTerms()) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse($this->sisValidTerms->findAll());
  }

  /**
   * Register a new term
   * @POST
   * @Param(name="year", type="post")
   * @Param(name="term", type="post")
   * @throws InvalidArgumentException
   * @throws ForbiddenRequestException
   */
  public function actionRegisterTerm() {
    if (!$this->sisAcl->canCreateTerm()) {
      throw new ForbiddenRequestException();
    }

    $year = intval($this->getRequest()->getPost("year"));
    $term = intval($this->getRequest()->getPost("term"));

    if ($this->sisValidTerms->isValid($year, $term)) {
      $this->sendSuccessResponse("OK");
    }

    // Throws InvalidArgumentException when given term is invalid
    $this->sisHelper->getCourses($this->getSisUserIdOrThrow($this->getCurrentUser()), $year, $term);

    $termEntity = new SisValidTerm($year, $term);
    $this->sisValidTerms->persist($termEntity);
    
    $this->sendSuccessResponse($termEntity);
  }

  /**
   * Set details of a term
   * @POST
   * @Param(name="beginning", type="post", validation="timestamp")
   * @Param(name="end", type="post", validation="timestamp")
   * @Param(name="advertiseUntil", type="post", validation="timestamp")
   * @param $id
   * @throws InvalidArgumentException
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionEditTerm(string $id) {
    $term = $this->sisValidTerms->findOrThrow($id);

    if (!$this->sisAcl->canEditTerm($term)) {
      throw new ForbiddenRequestException();
    }

    $beginning = DateTime::createFromFormat("U", $this->getRequest()->getPost("beginning"));
    $end = DateTime::createFromFormat("U", $this->getRequest()->getPost("end"));

    $advertiseUntil = null;
    if ($this->getRequest()->getPost("advertiseUntil") !== null) {
      $advertiseUntil = DateTime::createFromFormat("U", $this->getRequest()->getPost("advertiseUntil"));
    }

    if ($beginning > $end) {
      throw new InvalidArgumentException("beginning", "The beginning must precede the end");
    }

    if ($advertiseUntil !== null && ($advertiseUntil > $end || $advertiseUntil < $beginning)) {
      throw new InvalidArgumentException("advertiseUntil", "The 'advertiseUntil' timestamp must be within the semester");
    }

    $term->setBeginning($beginning);
    $term->setEnd($end);
    $term->setAdvertiseUntil($advertiseUntil);

    $this->sisValidTerms->persist($term);
    $this->sendSuccessResponse($term);
  }

  /**
   * Delete a term
   * @DELETE
   * @param $id
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionDeleteTerm(string $id) {
    $term = $this->sisValidTerms->findOrThrow($id);
    if (!$this->sisAcl->canDeleteTerm($term)) {
      throw new ForbiddenRequestException();
    }

    $this->sisValidTerms->remove($term);
    $this->sendSuccessResponse("OK");
  }

  /**
   * Get ReCodEx group bound to SIS groups of which the user is a student
   * @GET
   * @param $userId
   * @param $year
   * @param $term
   * @throws InvalidArgumentException
   * @throws ForbiddenRequestException
   */
  public function actionSubscribedGroups($userId, $year, $term) {
    $user = $this->users->findOrThrow($userId);
    $sisUserId = $this->getSisUserIdOrThrow($user);

    if (!$this->sisAcl->canViewCourses(new SisIdWrapper($sisUserId))) {
      throw new ForbiddenRequestException();
    }

    $groups = [];

    foreach ($this->sisHelper->getCourses($sisUserId, $year, $term) as $course) {
      if (!$course->isOwnerStudent()) {
        continue;
      }

      $bindings = $this->sisGroupBindings->findByCode($course->getCode());
      foreach ($bindings as $binding) {
        if ($binding->getGroup() !== null && !$binding->getGroup()->isArchived()) {
          /** @var Group $group */
          $group = $binding->getGroup();
          $serializedGroup = $this->groupViewFactory->getGroup($group);
          $serializedGroup["sisCode"] = $binding->getCode();
          $groups[] = $serializedGroup;
        }
      }
    }

    $this->sendSuccessResponse($groups);
  }

  /**
   * Get SIS groups of which the user is a supervisor (regardless of them being bound to a local group)
   * @GET
   * @param $userId
   * @param $year
   * @param $term
   * @throws InvalidArgumentException
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionSupervisedCourses($userId, $year, $term) {
    $user = $this->users->findOrThrow($userId);
    $sisUserId = $this->getSisUserIdOrThrow($user);

    if (!$this->sisAcl->canViewCourses(new SisIdWrapper($sisUserId))) {
      throw new ForbiddenRequestException();
    }

    $result = [];

    foreach ($this->sisHelper->getCourses($sisUserId, $year, $term) as $course) {
      if (!$course->isOwnerSupervisor()) {
        continue;
      }

      $bindings = $this->sisGroupBindings->findByCode($course->getCode());
      $groups = array_map(function (SisGroupBinding $binding) { return $binding->getGroup(); }, $bindings);
      $groups = array_filter($groups, function (Group $group) { return !$group->isArchived(); });

      $result[] = [
        'course' => $course,
        'groups' => $this->groupViewFactory->getGroups($groups)
      ];
    }

    $this->sendSuccessResponse($result);
  }

  /**
   * Create a new group based on a SIS group
   * @POST
   * @param $courseId
   * @throws BadRequestException
   * @Param(name="parentGroupId", type="post")
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   * @throws Exception
   */
  public function actionCreateGroup($courseId) {
    $user = $this->getCurrentUser();
    $sisUserId = $this->getSisUserIdOrThrow($user);
    $request = $this->getRequest();
    $parentGroupId = $request->getPost("parentGroupId");
    $parentGroup = $this->groups->findOrThrow($parentGroupId);

    if ($parentGroup->isArchived()) {
      throw new InvalidArgumentException("It is not permitted to create subgroups in archived groups");
    }

    $remoteCourse = $this->findRemoteCourseOrThrow($courseId, $sisUserId);

    if (!$this->sisAcl->canCreateGroup(new SisGroupContext($parentGroup, $remoteCourse), $remoteCourse)) {
      throw new ForbiddenRequestException();
    }

    $group = new Group($remoteCourse->getCourseId(), $parentGroup->getInstance(), $user, $parentGroup);

    foreach (["en", "cs"] as $language) {
      $timeInfo = $this->dayToString($remoteCourse->getDayOfWeek(), $language) . ", " . $remoteCourse->getTime();
      if ($remoteCourse->isFortnightly()) {
        $timeInfo .= ', ' . $this->oddWeeksToString($remoteCourse->getOddWeeks(), $language);
      }
      $caption = sprintf("%s (%s)", $remoteCourse->getCaption($language), $timeInfo);

      $localization = new LocalizedGroup($language, $caption, $remoteCourse->getAnnotation($language));
      $group->addLocalizedText($localization);

      $this->groups->persist($localization, false);
    }

    $this->groups->persist($group, false);

    $binding = new SisGroupBinding($group, $remoteCourse->getCode());
    $this->sisGroupBindings->persist($binding, true);

    $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
  }

  /**
   * Bind an existing local group to a SIS group
   * @POST
   * @param $courseId
   * @throws ApiException
   * @throws ForbiddenRequestException
   * @Param(name="groupId", type="post")
   */
  public function actionBindGroup($courseId) {
    $user = $this->getCurrentUser();
    $sisUserId = $this->getSisUserIdOrThrow($user);
    $remoteCourse = $this->findRemoteCourseOrThrow($courseId, $sisUserId);
    $group = $this->groups->findOrThrow($this->getRequest()->getPost("groupId"));

    if ($group->isArchived()) {
      throw new InvalidArgumentException("It is not permitted to create subgroups in archived groups");
    }

    if (!$this->sisAcl->canBindGroup($group, $remoteCourse)) {
      throw new ForbiddenRequestException();
    }

    if ($this->sisGroupBindings->findByGroupAndCode($group, $remoteCourse->getCode())) {
      throw new ApiException("The group is already bound to the course");
    }

    $binding = new SisGroupBinding($group, $remoteCourse->getCode());
    $this->sisGroupBindings->persist($binding);
    $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
  }

  /**
   * Find groups that can be chosen as parents of a group created from given SIS group by current user
   * @GET
   * @param $courseId
   * @throws ApiException
   * @throws ForbiddenRequestException
   */
  public function actionPossibleParents($courseId) {
    $sisUserId = $this->getSisUserIdOrThrow($this->getCurrentUser());
    $remoteCourse = $this->findRemoteCourseOrThrow($courseId, $sisUserId);

    $groups = array_filter($this->groups->findAll(), function (Group $group) use ($remoteCourse) {
      return $this->sisAcl->canCreateGroup(new SisGroupContext($group, $remoteCourse), $remoteCourse) && !$group->isArchived();
    });
    $this->sendSuccessResponse($this->groupViewFactory->getGroups($groups));
  }

  protected function getSisUserIdOrThrow(User $user) {
    $login = $this->externalLogins->findByUser($user, "cas-uk");

    if ($login === null) {
      throw new InvalidArgumentException("Given user is not bound to a CAS UK account");
    }

    return $login->getExternalId();
  }

  /**
   * @param $remoteGroupId
   * @param $sisUserId
   * @return SisCourseRecord|mixed
   * @throws BadRequestException
   * @throws InvalidArgumentException
   */
  private function findRemoteCourseOrThrow($remoteGroupId, $sisUserId) {
    foreach ($this->sisValidTerms->findAll() as $term) {
      foreach ($this->sisHelper->getCourses($sisUserId, $term->getYear(), $term->getTerm()) as $course) {
        if ($course->getCode() === $remoteGroupId) {
          return $course;
        }
      }
    }

    throw new BadRequestException(sprintf("Sis course %s was not found", $remoteGroupId));
  }

  private function dayToString($day, $language) {
    static $dayLabels = [
      'en' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
      'cs' => ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne']
    ];

    return $dayLabels[$language][$day];
  }

  private function oddWeeksToString($oddWeeks, $language) {
    static $labels = [
      'en' => ['Even weeks', 'Odd weeks'],
      'cs' => ['Sudé týdny', 'Liché týdny']
    ];

    return $labels[$language][$oddWeeks];
  }
}
