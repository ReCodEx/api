<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VFloat;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Helpers\Localizations;
use App\Model\Entity\LocalizedNotification;
use App\Model\Entity\Notification;
use App\Model\Repository\Groups;
use App\Model\Repository\Notifications;
use App\Security\ACL\INotificationPermissions;
use App\Security\Roles;
use DateTime;
use Nette\Utils\Arrays;

class NotificationsPresenter extends BasePresenter
{
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

    /**
     * @var Groups
     * @inject
     */
    public $groups;

    /**
     * @var Roles
     * @inject
     */
    public $roles;


    public function checkDefault()
    {
        if (!$this->notificationAcl->canViewCurrent()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get all notifications which are currently active. If groupsIds is given
     * returns only the ones from given groups (and their ancestors) and
     * global ones (without group).
     * @GET
     */
    #[Query("groupsIds", new VArray(), "identifications of groups", required: false)]
    public function actionDefault(array $groupsIds)
    {
        $ancestralGroupsIds = $this->groups->groupsIdsAncestralClosure($groupsIds);
        $notifications = $this->notifications->findAllCurrent($ancestralGroupsIds);
        $notifications = array_filter(
            $notifications,
            function (Notification $notification) {
                return $this->notificationAcl->canViewDetail($notification);
            }
        );

        $this->sendSuccessResponse(array_values($notifications));
    }

    public function checkAll()
    {
        if (!$this->notificationAcl->canViewAll()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get all notifications in the system.
     * @GET
     */
    public function actionAll()
    {
        $notifications = $this->notifications->findAll();
        $notifications = array_filter(
            $notifications,
            function (Notification $notification) {
                return $this->notificationAcl->canViewDetail($notification);
            }
        );

        $this->sendSuccessResponse(array_values($notifications));
    }

    public function checkCreate()
    {
        if (!$this->notificationAcl->canCreate()) {
            throw new ForbiddenRequestException("You are not allowed to create notification.");
        }
    }

    /**
     * Create notification with given attributes
     * @POST
     * @throws NotFoundException
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     */
    #[Post("groupsIds", new VArray(), "Identification of groups")]
    #[Post("visibleFrom", new VTimestamp(), "Date from which is notification visible")]
    #[Post("visibleTo", new VTimestamp(), "Date to which is notification visible")]
    #[Post("role", new VString(1), "Users with this role and its children can see notification")]
    #[Post("type", new VString(), "Type of the notification (custom)")]
    #[Post("localizedTexts", new VArray(), "Text of notification")]
    public function actionCreate()
    {
        $notification = new Notification($this->getCurrentUser());
        $this->updateNotification($notification);
        $this->notifications->persist($notification);
        $this->sendSuccessResponse($notification);
    }

    /**
     * Helper function for create and update endpoints.
     * @param Notification $notification
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws InvalidArgumentException
     */
    private function updateNotification(Notification $notification)
    {
        $req = $this->getRequest();

        $groupsIds = $req->getPost("groupsIds");
        if (empty($groupsIds) && !$this->notificationAcl->canCreateGlobal()) {
            throw new ForbiddenRequestException("You are not allowed to create global notification");
        }

        $notification->getGroups()->clear(); // clear all previous groups
        foreach ($groupsIds as $groupId) {
            $group = $this->groups->findOrThrow($groupId);
            if (!$this->notificationAcl->canAddGroup($group)) {
                throw new ForbiddenRequestException("You are not allowed to attach notification to given group.");
            }

            $notification->addGroup($group);
        }

        $visibleFromTimestamp = (int)$req->getPost("visibleFrom");
        $visibleToTimestamp = (int)$req->getPost("visibleTo");
        $role = $req->getPost("role");
        $type = $req->getPost("type");

        if (!$this->roles->validateRole($role)) {
            throw new InvalidArgumentException("role", "Unknown role");
        }

        $notification->setVisibleFrom(DateTime::createFromFormat('U', $visibleFromTimestamp));
        $notification->setVisibleTo(DateTime::createFromFormat('U', $visibleToTimestamp));
        $notification->setRole($role);
        $notification->setType($type);

        // retrieve and process localizations
        $this->updateNotificationLocalizations($notification);
    }

    /**
     * Helper function which takes care of localized notification texts
     * @param Notification $notification
     * @throws InvalidArgumentException
     */
    private function updateNotificationLocalizations(Notification $notification)
    {
        // Retrieve localizations and prepare some temp variables
        $localizedTexts = $this->getRequest()->getPost("localizedTexts");
        $localizations = [];

        // localized texts cannot be empty
        if (count($localizedTexts) == 0) {
            throw new InvalidArgumentException("localizedTexts", "No entry for localized texts given.");
        }

        // go through given localizations and construct database entities
        foreach ($localizedTexts as $localization) {
            if (!array_key_exists("locale", $localization) || !array_key_exists("text", $localization)) {
                throw new InvalidArgumentException("Malformed localized text entry");
            }

            $lang = $localization["locale"];
            if (array_key_exists($lang, $localizations)) {
                throw new InvalidArgumentException("Duplicate entry for language $lang");
            }

            $localization["text"] = $localization["text"] ?? "";

            $localized = new LocalizedNotification(
                $lang,
                trim(Arrays::get($localization, "text", ""))
            );
            $localizations[$lang] = $localized;
        }

        Localizations::updateCollection($notification->getLocalizedTexts(), $localizations);
    }

    public function checkUpdate(string $id)
    {
        $notification = $this->notifications->findOrThrow($id);
        if (!$this->notificationAcl->canUpdate($notification)) {
            throw new ForbiddenRequestException("You are not allowed to update this notification");
        }
    }

    /**
     * Update notification
     * @POST
     * @throws NotFoundException
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     */
    #[Post("groupsIds", new VArray(), "Identification of groups")]
    #[Post("visibleFrom", new VTimestamp(), "Date from which is notification visible")]
    #[Post("visibleTo", new VTimestamp(), "Date to which is notification visible")]
    #[Post("role", new VString(1), "Users with this role and its children can see notification")]
    #[Post("type", new VString(), "Type of the notification (custom)")]
    #[Post("localizedTexts", new VArray(), "Text of notification")]
    #[Path("id", new VString(), required: true)]
    public function actionUpdate(string $id)
    {
        $notification = $this->notifications->findOrThrow($id);
        $this->updateNotification($notification);
        $this->notifications->flush();
        $this->sendSuccessResponse($notification);
    }

    public function checkRemove(string $id)
    {
        $notification = $this->notifications->findOrThrow($id);
        if (!$this->notificationAcl->canRemove($notification)) {
            throw new ForbiddenRequestException("You are not allowed to remove this notification.");
        }
    }

    /**
     * Delete a notification
     * @DELETE
     * @throws NotFoundException
     */
    #[Path("id", new VString(), required: true)]
    public function actionRemove(string $id)
    {
        $notification = $this->notifications->findOrThrow($id);
        $this->notifications->remove($notification);
        $this->sendSuccessResponse("OK");
    }
}
