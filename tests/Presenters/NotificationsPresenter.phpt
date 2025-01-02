<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\NotFoundException;
use App\Model\Entity\Notification;
use App\V1Module\Presenters\NotificationsPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * @testCase
 */
class TestNotificationsPresenter extends Tester\TestCase
{
    /** @var NotificationsPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Nette\Security\User */
    private $user;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, NotificationsPresenter::class);
    }

    protected function tearDown()
    {
        Mockery::close();

        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }


    public function testDefaultAll()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $request = new Nette\Application\Request("V1:Notifications", "GET", ["action" => "default"]);
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result["code"]);
        $allCurrent = $this->presenter->notifications->findAllCurrent([]);
        Assert::count(count($allCurrent), $result["payload"]);

        foreach ($result["payload"] as $environment) {
            Assert::type(Notification::class, $environment);
            Assert::contains($environment, $allCurrent);
        }
    }

    public function testDefaultGroups()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        // Demo group does not have notification,
        // so only one global should be returned
        $groups = $this->presenter->groups->findFiltered(
            null,
            null,
            "Demo group"
        );
        Assert::count(1, $groups);
        $group = current($groups);

        $request = new Nette\Application\Request(
            "V1:Notifications",
            "GET",
            ["action" => "default", "groupsIds" => [$group->getId()]]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result["code"]);
        // we have only one current, global group in fixtures, the other ones are
        // either expired or belongs to group which is not "Demo group"
        Assert::count(1, $result["payload"]);

        foreach ($result["payload"] as $environment) {
            Assert::type(Notification::class, $environment);
        }
    }

    public function testAll()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $request = new Nette\Application\Request("V1:Notifications", "GET", ["action" => "all"]);
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result["code"]);
        $all = $this->presenter->notifications->findAll();
        Assert::count(count($all), $result["payload"]);

        foreach ($result["payload"] as $environment) {
            Assert::type(Notification::class, $environment);
            Assert::contains($environment, $all);
        }
    }

    public function testCreate()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $groupsIds = []; // global notification
        $visibleFrom = (new DateTime())->getTimestamp();
        $visibleTo = (new DateTime())->getTimestamp();
        $role = "supervisor";
        $type = "custom-notification-type-create";
        $localizedTexts = [
            ["locale" => "notL", "text" => "notification create description"]
        ];

        $request = new Nette\Application\Request(
            "V1:Notifications",
            "POST",
            ["action" => "create"],
            [
                "groupsIds" => $groupsIds,
                "visibleFrom" => $visibleFrom,
                "visibleTo" => $visibleTo,
                "role" => $role,
                "type" => $type,
                "localizedTexts" => $localizedTexts,
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result["code"]);

        // check updated notification
        /** @var Notification $updatedNotification */
        $updatedNotification = $result["payload"];
        Assert::equal($groupsIds, $updatedNotification->getGroupsIds());
        Assert::equal($visibleFrom, $updatedNotification->getVisibleFrom()->getTimestamp());
        Assert::equal($visibleTo, $updatedNotification->getVisibleTo()->getTimestamp());
        Assert::equal($role, $updatedNotification->getRole());
        Assert::equal($type, $updatedNotification->getType());

        // check localized texts
        Assert::count(1, $updatedNotification->getLocalizedTexts());
        $localized = current($localizedTexts);
        $updatedLocalized = $updatedNotification->getLocalizedTexts()[0];
        Assert::equal($localized["locale"], $updatedLocalized->getLocale());
        Assert::equal($localized["text"], $updatedLocalized->getText());
    }

    public function testUpdate()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $notification = current($this->presenter->notifications->findAll());

        $groupsIds = []; // global notification
        $visibleFrom = (new DateTime())->getTimestamp();
        $visibleTo = (new DateTime())->getTimestamp();
        $role = "supervisor";
        $type = "custom-notification-type-update";
        $localizedTexts = [
            ["locale" => "notL", "text" => "notification update description"]
        ];

        $request = new Nette\Application\Request(
            "V1:Notifications",
            "POST",
            ["action" => "update", "id" => $notification->getId()],
            [
                "groupsIds" => $groupsIds,
                "visibleFrom" => $visibleFrom,
                "visibleTo" => $visibleTo,
                "role" => $role,
                "type" => $type,
                "localizedTexts" => $localizedTexts,
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result["code"]);

        // check updated notification
        $updatedNotification = $this->presenter->notifications->findOrThrow($notification->getId());
        Assert::equal($groupsIds, $updatedNotification->getGroupsIds());
        Assert::equal($visibleFrom, $updatedNotification->getVisibleFrom()->getTimestamp());
        Assert::equal($visibleTo, $updatedNotification->getVisibleTo()->getTimestamp());
        Assert::equal($role, $updatedNotification->getRole());
        Assert::equal($type, $updatedNotification->getType());

        // check localized texts
        Assert::count(1, $updatedNotification->getLocalizedTexts());
        $localized = current($localizedTexts);
        $updatedLocalized = $updatedNotification->getLocalizedTexts()[0];
        Assert::equal($localized["locale"], $updatedLocalized->getLocale());
        Assert::equal($localized["text"], $updatedLocalized->getText());
    }

    public function testRemove()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $notificationId = current($this->presenter->notifications->findAll())->getId();

        $request = new Nette\Application\Request(
            "V1:Notifications",
            "DELETE",
            ["action" => "remove", "id" => $notificationId]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result["code"]);
        Assert::equal("OK", $result["payload"]);
        Assert::exception(
            function () use ($notificationId) {
                $this->presenter->notifications->findOrThrow($notificationId);
            },
            NotFoundException::class
        );
    }
}

$testCase = new TestNotificationsPresenter();
$testCase->run();
