<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\EmailHelper;
use App\Model\Entity\User;
use App\Model\Repository\Groups;
use App\Security\ACL\IEmailPermissions;
use App\Security\ACL\IGroupPermissions;
use App\Security\Roles;

class EmailsPresenter extends BasePresenter {

  /**
   * @var EmailHelper
   * @inject
   */
  public $emailHelper;

  /**
   * @var IEmailPermissions
   * @inject
   */
  public $emailAcl;

  /**
   * @var Groups
   * @inject
   */
  public $groups;

  /**
   * @var IGroupPermissions
   * @inject
   */
  public $groupAcl;


  public function checkDefault() {
    if (!$this->emailAcl->canSendToAll()) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Sends an email with provided subject and message to all ReCodEx users.
   * @POST
   * @Param(type="post", name="subject", validation="string:1..", description="Subject for the soon to be sent email")
   * @Param(type="post", name="message", validation="string:1..", description="Message which will be sent, can be html code")
   */
  public function actionDefault() {
    $users = $this->users->findAll();
    $emails = array_map(function (User $user) {
      return $user->getEmail();
    }, $users);

    $req = $this->getRequest();
    $subject = $req->getPost("subject");
    $message = $req->getPost("message");

    $this->emailHelper->send(null, [], $subject, $message, $emails);
    $this->sendSuccessResponse("OK");
  }

  public function checkSendToSupervisors() {
    if (!$this->emailAcl->canSendToSupervisors()) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Sends an email with provided subject and message to all supervisors and superadmins.
   * @POST
   * @Param(type="post", name="subject", validation="string:1..", description="Subject for the soon to be sent email")
   * @Param(type="post", name="message", validation="string:1..", description="Message which will be sent, can be html code")
   */
  public function actionSendToSupervisors() {
    $supervisors = $this->users->findByRoles(Roles::SUPERVISOR_ROLE, Roles::SUPERADMIN_ROLE);
    $emails = array_map(function (User $user) {
      return $user->getEmail();
    }, $supervisors);

    $req = $this->getRequest();
    $subject = $req->getPost("subject");
    $message = $req->getPost("message");

    $this->emailHelper->send(null, [], $subject, $message, $emails);
    $this->sendSuccessResponse("OK");
  }

  public function checkSendToRegularUsers() {
    if (!$this->emailAcl->canSendToRegularUsers()) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Sends an email with provided subject and message to all regular users.
   * @POST
   * @Param(type="post", name="subject", validation="string:1..", description="Subject for the soon to be sent email")
   * @Param(type="post", name="message", validation="string:1..", description="Message which will be sent, can be html code")
   */
  public function actionSendToRegularUsers() {
    $users = $this->users->findByRoles(Roles::STUDENT_ROLE);
    $emails = array_map(function (User $user) {
      return $user->getEmail();
    }, $users);

    $req = $this->getRequest();
    $subject = $req->getPost("subject");
    $message = $req->getPost("message");

    $this->emailHelper->send(null, [], $subject, $message, $emails);
    $this->sendSuccessResponse("OK");
  }

  public function checkSendToGroupMembers(string $groupId) {
    $group = $this->groups->findOrThrow($groupId);
    if (!$this->groupAcl->canSendEmail($group)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Sends an email with provided subject and message to all members of given group.
   * @POST
   * @param string $groupId
   * @Param(type="post", name="subject", validation="string:1..", description="Subject for the soon to be sent email")
   * @Param(type="post", name="message", validation="string:1..", description="Message which will be sent, can be html code")
   * @throws NotFoundException
   */
  public function actionSendToGroupMembers(string $groupId) {
    $group = $this->groups->findOrThrow($groupId);
    $users = $group->getMembers()->getValues();
    $emails = array_map(function (User $user) {
      return $user->getEmail();
    }, $users);

    $req = $this->getRequest();
    $subject = $req->getPost("subject");
    $message = $req->getPost("message");

    $this->emailHelper->send(null, [], $subject, $message, $emails);
    $this->sendSuccessResponse("OK");
  }
}
