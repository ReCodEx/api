<?php
namespace App\V1Module\Presenters;


use App\Exceptions\ApiException;
use App\Exceptions\BadRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Helpers\SisHelper;
use App\Model\Entity\Group;
use App\Model\Entity\SisGroupBinding;
use App\Model\Entity\User;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Groups;
use App\Model\Repository\Instances;
use App\Model\Repository\SisGroupBindings;

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
   * @GET
   */
  public function actionCurrentTerm() {
    $this->sendSuccessResponse([ // TODO
      "year" => 2017,
      "term" => 1,
      "starting" => TRUE
    ]);
  }

  /**
   * Get ReCodEx group bound to SIS groups of which the user is a student
   * @GET
   * @param $userId
   * @param $year
   * @param $term
   * @throws InvalidArgumentException
   */
  public function actionSubscribedGroups($userId, $year, $term) {
    $user = $this->users->findOrThrow($userId);
    $sisUserId = $this->getSisUserIdOrThrow($user);

    $groups = [];

    foreach ($this->sisHelper->getCourses($sisUserId, $year, $term) as $course) {
      if (!$course->isOwnerStudent()) {
        continue;
      }

      $bindings = $this->sisGroupBindings->findByCode($course->getCode());
      foreach ($bindings as $binding) {
        if ($binding->getGroup() !== NULL) {
          $groups[] = $binding->getGroup();
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
   */
  public function actionSupervisedCourses($userId, $year, $term) {
    $user = $this->users->findOrThrow($userId);
    $sisUserId = $this->getSisUserIdOrThrow($user);

    $result = [];

    foreach ($this->sisHelper->getCourses($sisUserId, $year, $term) as $course) {
      $bindings = $this->sisGroupBindings->findByCode($course->getCode());

      $result[] = [
        'course' => $course,
        'groups' => array_map(function (SisGroupBinding $binding) { return $binding->getGroup(); }, $bindings)
      ];
    }

    $this->sendSuccessResponse($result);
  }

  /**
   * Create a new group based on a SIS group
   * @POST
   * @param $courseId
   * @throws BadRequestException
   * @Param(name="instanceId", type="post")
   * @Param(name="parentGroupId", type="post", required=FALSE)
   * @Param(name="language", type="post")
   */
  public function actionCreateGroup($courseId) {
    $user = $this->getCurrentUser();
    $sisUserId = $this->getSisUserIdOrThrow($user);
    $request = $this->getRequest();
    $language = $request->getParameter("language") ?: "en";
    $instance = $this->instances->findOrThrow($request->getParameter("instance"));
    $parentGroupId = $request->getParameter("parentGroupId");
    $parentGroup = $parentGroupId ? $this->groups->findOrThrow($parentGroupId) : NULL;

    $remoteCourse = $this->findRemoteCourseOrThrow($courseId, $sisUserId);

    $timeInfo = $this->dayToString($remoteCourse->getDayOfWeek(), $language) . ", " . $remoteCourse->getTime();
    if ($remoteCourse->isFortnightly()) {
      $timeInfo .= ', ' . $this->oddWeeksToString($remoteCourse->getOddWeeks(), $language);
    }
    $caption = sprintf("%s (%s)", $remoteCourse->getCaption($language), $timeInfo);

    $group = new Group(
      $caption,
      $remoteCourse->getCourseId(),
      $remoteCourse->getAnnotation($language),
      $instance,
      $user,
      $parentGroup
    );
    $this->groups->persist($group, FALSE);

    $binding = new SisGroupBinding($group, $remoteCourse->getCode());
    $this->sisGroupBindings->persist($binding, TRUE);

    $this->sendSuccessResponse($group);
  }

  /**
   * Bind an existing local group to a SIS group
   * @POST
   * @param $courseId
   * @throws ApiException
   * @Param(name="groupId", type="post")
   */
  public function actionBindGroup($courseId) {
    $user = $this->getCurrentUser();
    $sisUserId = $this->getSisUserIdOrThrow($user);
    $remoteCourse = $this->findRemoteCourseOrThrow($courseId, $sisUserId);
    $group = $this->groups->findOrThrow($this->getRequest()->getPost("groupId"));

    if ($this->sisGroupBindings->findByGroupAndCode($group, $remoteCourse->getCode())) {
      throw new ApiException("The group is already bound to the course");
    }

    $binding = new SisGroupBinding($group, $remoteCourse->getCode());
    $this->sisGroupBindings->persist($binding);
    $this->sendSuccessResponse($group);
  }

  protected function getSisUserIdOrThrow(User $user) {
    $login = $this->externalLogins->findByUser($user, "cas-uk");

    if ($login === NULL) {
      throw new InvalidArgumentException("Given user is not bound to a CAS UK account");
    }

    return $login->getExternalId();
  }

  /**
   * @param $remoteGroupId
   * @param $sisUserId
   * @return \App\Helpers\SisCourseRecord|mixed
   * @throws BadRequestException
   */
  private function findRemoteCourseOrThrow($remoteGroupId, $sisUserId) {
    foreach ($this->sisHelper->getCourses($sisUserId) as $course) {
      if ($course->getCode() === $remoteGroupId) {
        return $course;
      }
    }

    throw new BadRequestException();
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