<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Model\Entity\Notification;
use App\Model\Repository\Notifications;
use App\Security\ACL\INotificationPermissions;

class NotificationsPresenter extends BasePresenter {

  /**
   * @var INotificationPermissions
   * @inject
   */
  public $notificationAcl;

  /**
   * @var Notifications
   * @inject
   */
  public $notifications;


  public function checkDefault() {
    if (!$this->notificationAcl->canViewCurrent()) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Get all notifications which are currently active.
   * @GET
   */
  public function actionDefault() {
    $notifications = $this->notifications->findAllCurrent();
    $notifications = array_filter($notifications,
      function (Notification $notification) {
        return $this->notificationAcl->canViewDetail($notification);
      });

    $this->sendSuccessResponse($notifications);
  }
}
