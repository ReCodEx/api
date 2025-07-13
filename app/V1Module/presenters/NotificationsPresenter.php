<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidApiArgumentException;
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


    public function noncheckDefault()
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckAll()
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckCreate()
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
     * @throws InvalidApiArgumentException
     */
    #[Post("groupsIds", new VArray(), "Identification of groups")]
    #[Post("visibleFrom", new VTimestamp(), "Date from which is notification visible")]
    #[Post("visibleTo", new VTimestamp(), "Date to which is notification visible")]
    #[Post("role", new VString(1), "Users with this role and its children can see notification")]
    #[Post("type", new VString(), "Type of the notification (custom)")]
    #[Post("localizedTexts", new VArray(), "Text of notification")]
    public function actionCreate()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Helper function for create and update endpoints.
     * @param Notification $notification
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws InvalidApiArgumentException
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
            throw new InvalidApiArgumentException('role', "Unknown role '$role'");
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
     * @throws InvalidApiArgumentException
     */
    private function updateNotificationLocalizations(Notification $notification)
    {
        // Retrieve localizations and prepare some temp variables
        $localizedTexts = $this->getRequest()->getPost("localizedTexts");
        $localizations = [];

        // localized texts cannot be empty
        if (count($localizedTexts) == 0) {
            throw new InvalidApiArgumentException('localizedTexts', "No entry for localized texts given.");
        }

        // go through given localizations and construct database entities
        foreach ($localizedTexts as $localization) {
            if (!array_key_exists("locale", $localization) || !array_key_exists("text", $localization)) {
                throw new InvalidApiArgumentException('localizedTexts', "Malformed localized text entry");
            }

            $lang = $localization["locale"];
            if (array_key_exists($lang, $localizations)) {
                throw new InvalidApiArgumentException('lang', "Duplicate entry for language $lang");
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

    public function noncheckUpdate(string $id)
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
     * @throws InvalidApiArgumentException
     */
    #[Post("groupsIds", new VArray(), "Identification of groups")]
    #[Post("visibleFrom", new VTimestamp(), "Date from which is notification visible")]
    #[Post("visibleTo", new VTimestamp(), "Date to which is notification visible")]
    #[Post("role", new VString(1), "Users with this role and its children can see notification")]
    #[Post("type", new VString(), "Type of the notification (custom)")]
    #[Post("localizedTexts", new VArray(), "Text of notification")]
    #[Path("id", new VUuid(), required: true)]
    public function actionUpdate(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRemove(string $id)
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
    #[Path("id", new VUuid(), required: true)]
    public function actionRemove(string $id)
    {
        $this->sendSuccessResponse("OK");
    }
}
