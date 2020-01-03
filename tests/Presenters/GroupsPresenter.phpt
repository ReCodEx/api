<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\BadRequestException;
use App\Model\Entity\Group;
use App\Model\Entity\Instance;
use App\Model\Entity\User;
use App\V1Module\Presenters\GroupsPresenter;
use Tester\Assert;


/**
 * @testCase
 */
class TestGroupsPresenter extends Tester\TestCase
{
    private $userLogin = "user2@example.com";
    private $userPassword = "password2";

    private $adminLogin = "admin@admin.com";
    private $adminPassword = "admin";

    private $groupSupervisorLogin = "demoGroupSupervisor@example.com";
    private $groupSupervisorPassword = "password";

    /** @var GroupsPresenter */
    protected $presenter;

    /** @var Kdyby\Doctrine\EntityManager */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Nette\Security\User */
    private $user;

    /** @var \App\Security\AccessManager */
    private $accessManager;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->accessManager = $container->getByType(\App\Security\AccessManager::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, GroupsPresenter::class);
    }

    protected function tearDown()
    {
        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }


    /**
     * Helper which returns group with non-zero number of students
     * @return Group
     */
    private function getGroupWithStudents(): Group
    {
        $groups = $this->presenter->groups->findAll();
        $group = null;
        foreach ($groups as $grp) {
            if ($grp->getStudents()->count() > 0) {
                $group = $grp;
                break;
            }
        }
        Assert::notEqual(null, $group);
        return $group;
    }

    private function getAllGroupsInDepth($depth, $filter = null, $root = null)
    {
        if (!$root) {
            $rootCandidates = array_filter(
                $this->presenter->groups->findAll(),
                function (Group $g) {
                    return $g->getParentGroup() === null;
                }
            );
            Assert::count(1, $rootCandidates);
            $root = reset($rootCandidates);
        }

        if ($depth === 0) {
            if ($filter) {
                return $filter($root) ? [$root] : [];
            } else {
                return [$root];
            }
        }

        $res = [];
        foreach ($root->getChildGroups() as $child) {
            $res = array_merge($res, $this->getAllGroupsInDepth($depth - 1, $filter, $child));
        }
        return $res;
    }

    public function testListAllGroupsBySupervisor()
    {
        $token = PresenterTestHelper::login(
            $this->container,
            $this->groupSupervisorLogin,
            $this->groupSupervisorPassword
        );
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'GET',
            ['action' => 'default']
        );
        Assert::equal(2, count($payload));
    }

    public function testListAllGroupsByAdmin()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'GET',
            ['action' => 'default']
        );
        Assert::equal(4, count($payload));
    }

    public function testSearchGroupByName()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'GET',
            ['action' => 'default', 'search' => 'child']
        );
        Assert::equal(1, count($payload));
    }

    public function testSearchGroupByNameIncludingAncestors()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'GET',
            ['action' => 'default', 'search' => 'child', 'ancestors' => true]
        );
        Assert::equal(3, count($payload));
    }

    public function testListGroupIncludingArchived()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'GET',
            ['action' => 'default', 'archived' => true]
        );
        Assert::equal(6, count($payload));
    }

    public function testListGroupOnlyArchived()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'GET',
            ['action' => 'default', 'onlyArchived' => true]
        );
        Assert::equal(2, count($payload));
        foreach ($payload as $group) {
            Assert::truthy($group["archived"]);
        }
    }

    public function testListGroupOnlyArchivedButNotLongAgo()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'GET',
            ['action' => 'default', 'onlyArchived' => true, 'archivedAgeLimit' => 365]
        ); // no longer than year ago
        Assert::equal(1, count($payload));
        foreach ($payload as $group) {
            Assert::truthy($group["archived"]);
        }
    }

    public function testUserCannotJoinPrivateGroup()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);

        $user = $this->accessManager->getUser($this->accessManager->decodeToken($token));
        $group = $user->getInstances()->first()->getGroups()->filter(
            function (Group $group) {
                return !$group->isPublic;
            }
        )->first();

        $request = new Nette\Application\Request(
            'V1:Groups', 'POST', [
            'action' => 'addStudent',
            'id' => $group->id,
            'userId' => $user->id
        ]
        );

        /** @var \Nette\Application\Responses\JsonResponse $response */
        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testUserCanJoinPublicGroup()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        $user = $this->accessManager->getUser($this->accessManager->decodeToken($token));

        /** @var Group $group */
        $group = $user->getInstances()->first()->getGroups()->filter(
            function (Group $group) use ($user) {
                return $group->isPublic() && !$group->isMemberOf($user);
            }
        )->first();

        $request = new Nette\Application\Request(
            'V1:Groups', 'POST', [
            'action' => 'addStudent',
            'id' => $group->getId(),
            'userId' => $user->getId()
        ]
        );

        /** @var \Nette\Application\Responses\JsonResponse $response */
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result["code"]);
    }

    public function testRemoveStudent()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $group = $this->presenter->groups->findAll()[0];
        $user = $this->presenter->users->getByEmail($this->userLogin);
        $user->makeStudentOf($group); // ! necessary
        $this->presenter->users->flush();

        // initial checks
        Assert::equal(true, $group->isStudentOf($user));

        $request = new Nette\Application\Request(
            'V1:Groups', 'DELETE', [
            'action' => 'removeStudent',
            'id' => $group->id,
            'userId' => $user->id
        ]
        );

        /** @var \Nette\Application\Responses\JsonResponse $response */
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result["payload"];
        Assert::equal(200, $result["code"]);

        Assert::false(in_array($user->getId(), $payload["privateData"]["students"]));
    }

    public function testAddGroup()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        /** @var Instance $instance */
        $instance = $this->presenter->instances->findAll()[0];
        $allGroupsCount = count($this->presenter->groups->findAll());

        $request = new Nette\Application\Request(
            'V1:Groups',
            'POST',
            ['action' => 'addGroup'],
            [
                'localizedTexts' => [
                    [
                        'locale' => 'en',
                        'name' => 'new name',
                        'description' => 'some neaty description'
                    ]
                ],
                'instanceId' => $instance->getId(),
                'externalId' => 'external identification of exercise',
                'parentGroupId' => null,
                'publicStats' => true,
                'isPublic' => true,
                'hasThreshold' => false,
                'isOrganizational' => false,
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        /** @var Group $payload */
        $payload = $result['payload'];

        Assert::count(1, $payload["localizedTexts"]);
        $localizedGroup = current($payload["localizedTexts"]);
        Assert::notSame(null, $localizedGroup);

        Assert::equal(200, $result['code']);
        Assert::count($allGroupsCount + 1, $this->presenter->groups->findAll());
        Assert::equal('new name', $localizedGroup->getName());
        Assert::equal('some neaty description', $localizedGroup->getDescription());
        Assert::equal($instance->getId(), $payload["privateData"]["instanceId"]);
        Assert::equal('external identification of exercise', $payload["externalId"]);
        Assert::equal($instance->getRootGroup()->getId(), $payload["parentGroupId"]);
        Assert::equal(true, $payload["privateData"]["publicStats"]);
        Assert::equal(true, $payload["public"]);
    }

    public function testValidateAddGroupData()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $instance = $this->presenter->instances->findAll()[0];

        $request = new Nette\Application\Request(
            'V1:Groups',
            'POST',
            ['action' => 'validateAddGroupData'],
            [
                'name' => 'new name',
                'locale' => 'en',
                'instanceId' => $instance->getId(),
                'parentGroupId' => null,
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);
        Assert::equal(true, $payload['groupNameIsFree']);
    }

    public function testValidateAddGroupDataNameExists()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);

        /** @var Instance $instance */
        $instance = null;

        /** @var Group $group */
        $group = null;

        foreach ($this->presenter->instances->findAll() as $instance) {
            /** @var Group $candidate */
            foreach ($instance->getGroups() as $candidate) {
                if ($candidate->getParentGroup() === $instance->getRootGroup()) {
                    $group = $candidate;
                    break;
                }
            }
        }

        Assert::notSame(null, $instance);
        Assert::notSame(null, $group);

        $request = new Nette\Application\Request(
            'V1:Groups',
            'POST',
            ['action' => 'validateAddGroupData'],
            [
                'name' => $group->getLocalizedTextByLocale("en")->getName(),
                'locale' => 'en',
                'instanceId' => $instance->getId(),
                'parentGroupId' => null,
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);
        Assert::equal(false, $payload['groupNameIsFree']);
    }

    public function testUpdateGroup()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $allGroups = $this->presenter->groups->findAll();
        $group = array_pop($allGroups);

        $request = new Nette\Application\Request(
            'V1:Groups',
            'POST',
            ['action' => 'updateGroup', 'id' => $group->getId()],
            [
                'localizedTexts' => [
                    [
                        'locale' => 'en',
                        'name' => 'new name',
                        'description' => 'some neaty description',
                    ]
                ],
                'externalId' => 'external identification of exercise',
                'publicStats' => true,
                'isPublic' => true,
                'hasThreshold' => true,
                'threshold' => 80
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);

        Assert::equal($group->getId(), $payload["id"]);
        Assert::count(1, $payload["localizedTexts"]);
        $localizedGroup = current($payload["localizedTexts"]);
        Assert::equal('new name', $localizedGroup->getName());
        Assert::equal('some neaty description', $localizedGroup->getDescription());
        Assert::equal('external identification of exercise', $payload["externalId"]);
        Assert::equal(true, $payload["privateData"]["publicStats"]);
        Assert::equal(true, $payload["public"]);
        Assert::equal(0.8, $payload["privateData"]["threshold"]);
    }

    public function testRemoveGroup()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $instance = $this->presenter->instances->findAll()[0];
        $groups = $this->presenter->groups->findAll();
        $allGroupsCount = count($groups);
        $group = array_pop($groups);

        $request = new Nette\Application\Request(
            'V1:Groups',
            'DELETE',
            ['action' => 'removeGroup', 'id' => $group->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $payload);
        Assert::count($allGroupsCount - 1, $this->presenter->groups->findAll());
    }

    public function testDetail()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $groups = $this->presenter->groups->findAll();
        $group = array_pop($groups);

        $request = new Nette\Application\Request(
            'V1:Groups',
            'GET',
            ['action' => 'detail', 'id' => $group->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);
        Assert::equal($group->getId(), $payload["id"]);
    }

    public function testSubgroups()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $groups = $this->presenter->groups->findAll();
        $group = array_pop($groups);

        $request = new Nette\Application\Request(
            'V1:Groups',
            'GET',
            ['action' => 'subgroups', 'id' => $group->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);
        Assert::equal($group->getAllSubgroups(), $payload); // admin can access everything
    }

    public function testMembers()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $groups = $this->presenter->groups->findAll();
        $group = array_pop($groups);

        $request = new Nette\Application\Request(
            'V1:Groups',
            'GET',
            ['action' => 'members', 'id' => $group->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);

        Assert::equal(
            $this->presenter->userViewFactory->getUsers($group->getSupervisors()->getValues()),
            $payload["supervisors"]
        );
        Assert::equal(
            $this->presenter->userViewFactory->getUsers($group->getStudents()->getValues()),
            $payload["students"]
        );
    }

    public function testSupervisors()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $groups = $this->presenter->groups->findAll();
        $group = array_pop($groups);

        $request = new Nette\Application\Request(
            'V1:Groups',
            'GET',
            ['action' => 'supervisors', 'id' => $group->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);

        Assert::equal($this->presenter->userViewFactory->getUsers($group->getSupervisors()->getValues()), $payload);
    }

    public function testStudents()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $groups = $this->presenter->groups->findAll();
        $group = array_pop($groups);

        $request = new Nette\Application\Request(
            'V1:Groups',
            'GET',
            ['action' => 'students', 'id' => $group->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);

        Assert::equal($group->getStudents()->getValues(), $payload);
    }

    public function testAssignments()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $groups = $this->presenter->groups->findAll();
        foreach ($groups as $group) {
            $payload = PresenterTestHelper::performPresenterRequest(
                $this->presenter,
                'V1:Groups',
                'GET',
                ['action' => 'assignments', 'id' => $group->getId()]
            );

            $correctIds = PresenterTestHelper::extractIdsMap($group->getAssignments()->toArray());
            $payloadIds = PresenterTestHelper::extractIdsMap($payload);

            Assert::equal($correctIds, $payloadIds);
        }
    }

    public function testShadowAssignments()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $groups = $this->presenter->groups->findAll();
        foreach ($groups as $group) {
            $payload = PresenterTestHelper::performPresenterRequest(
                $this->presenter,
                'V1:Groups',
                'GET',
                ['action' => 'shadowAssignments', 'id' => $group->getId()]
            );

            $correctIds = PresenterTestHelper::extractIdsMap($group->getShadowAssignments()->toArray());
            $payloadIds = PresenterTestHelper::extractIdsMap($payload);
            Assert::equal($correctIds, $payloadIds);
        }
    }

    public function testExercises()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        // Find the only group with exercises....
        $group = null;
        foreach ($this->presenter->groups->findAll() as $g) {
            if (count($g->getExercises()) > 0) {
                $group = $g;
                break;
            }
        }

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'GET',
            ['action' => 'exercises', 'id' => $group->getId()]
        );

        $correctIds = PresenterTestHelper::extractIdsMap($group->getExercises()->toArray());
        $payloadIds = PresenterTestHelper::extractIdsMap($payload);
        Assert::equal($correctIds, $payloadIds);
    }

    public function testStats()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);
        $group = $this->getGroupWithStudents();

        $request = new Nette\Application\Request(
            'V1:Groups',
            'GET',
            ['action' => 'stats', 'id' => $group->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);
        Assert::count(11, $payload);
    }

    public function testStudentsStats()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);

        $group = $this->getGroupWithStudents();
        $user = $group->getStudents()->first();

        $request = new Nette\Application\Request(
            'V1:Groups',
            'GET',
            ['action' => 'studentsStats', 'id' => $group->getId(), 'userId' => $user->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);

        Assert::true(array_key_exists("userId", $payload));
        Assert::true(array_key_exists("groupId", $payload));
        Assert::true(array_key_exists("assignments", $payload));
        Assert::true(array_key_exists("points", $payload));
        Assert::true(array_key_exists("hasLimit", $payload));
        Assert::true(array_key_exists("passesLimit", $payload));
    }

    public function testAddSupervisor()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $group = $this->presenter->groups->findAll()[0];
        $user = $this->presenter->users->getByEmail($this->userLogin);
        $user->setRole("supervisor");

        // initial checks
        Assert::equal(false, $group->isSupervisorOf($user));

        $request = new Nette\Application\Request(
            'V1:Groups', 'POST', [
            'action' => 'addSupervisor',
            'id' => $group->id,
            'userId' => $user->id
        ]
        );

        /** @var \Nette\Application\Responses\JsonResponse $response */
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result["payload"];
        Assert::equal(200, $result["code"]);

        Assert::true(in_array($user->getId(), $payload["privateData"]["supervisors"]));
    }

    public function testAddStudentAsSupervisor()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $group = $this->presenter->groups->findAll()[0];
        $user = $this->presenter->users->getByEmail($this->userLogin);
        $user->setRole("student");

        // initial checks
        Assert::equal(false, $group->isSupervisorOf($user));

        $request = new Nette\Application\Request(
            'V1:Groups', 'POST', [
            'action' => 'addSupervisor',
            'id' => $group->id,
            'userId' => $user->id
        ]
        );

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            \App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testRemoveSupervisor()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $group = $this->presenter->groups->findAll()[0];
        $user = $this->presenter->users->getByEmail($this->userLogin);
        $user->makeSupervisorOf($group); // ! necessary
        $this->presenter->users->flush();

        // initial checks
        Assert::equal(true, $group->isSupervisorOf($user));

        $request = new Nette\Application\Request(
            'V1:Groups', 'DELETE', [
            'action' => 'removeSupervisor',
            'id' => $group->id,
            'userId' => $user->id
        ]
        );

        /** @var \Nette\Application\Responses\JsonResponse $response */
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result["payload"];
        Assert::equal(200, $result["code"]);

        Assert::false(in_array($user->getId(), $payload["privateData"]["supervisors"]));
    }

    public function testGetAdmins()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $groups = $this->presenter->groups->findAll();
        $group = array_pop($groups);

        $request = new Nette\Application\Request(
            'V1:Groups',
            'GET',
            ['action' => 'admins', 'id' => $group->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);

        Assert::equal($group->getAdminsIds(), $payload);
    }

    public function testAddAdmin()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $group = $this->presenter->groups->findAll()[0];
        $user = $this->presenter->users->getByEmail($this->userLogin);

        // initial checks
        Assert::equal(false, $group->isAdminOf($user));

        // initial setup
        $user->makeSupervisorOf($group);
        $this->presenter->groups->flush();

        $request = new Nette\Application\Request(
            'V1:Groups',
            'POST',
            ['action' => 'addAdmin', 'id' => $group->getId()],
            ['userId' => $user->getId()]
        );

        /** @var \Nette\Application\Responses\JsonResponse $response */
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result["payload"];
        Assert::equal(200, $result["code"]);

        Assert::equal([$user->getId()], $payload["primaryAdminsIds"]);
    }

    public function testSetOrganizational()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var Group $group */
        $group = $this->presenter->groups->findAll()[0];

        // initial setup
        $group->getAssignments()->clear();

        /** @var User $student */
        foreach ($group->getStudents() as $student) {
            $group->removeMembership($student->findMembershipAsStudent($group));
        }

        $this->presenter->groups->flush();

        $request = new Nette\Application\Request(
            'V1:Groups',
            'POST',
            ['action' => 'setOrganizational', 'id' => $group->getId()],
            ['value' => true]
        );

        /** @var \Nette\Application\Responses\JsonResponse $response */
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result["code"]);
    }

    public function testSetOrganizationalWithStudents()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var Group $group */
        $group = null;
        foreach ($this->presenter->groups->findAll() as $groupCandidate) {
            if ($groupCandidate->getStudents()->count() > 0) {
                $group = $groupCandidate;
            }
        }

        // initial setup
        Assert::true($group !== null);
        $group->getAssignments()->clear();

        $this->presenter->groups->flush();

        $request = new Nette\Application\Request(
            'V1:Groups',
            'POST',
            ['action' => 'setOrganizational', 'id' => $group->getId()],
            ['value' => true]
        );

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            BadRequestException::class
        );
    }

    public function testSetOrganizationalWithAssignments()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var Group $group */
        $group = null;
        foreach ($this->presenter->groups->findAll() as $groupCandidate) {
            if ($groupCandidate->getAssignments()->count() > 0) {
                $group = $groupCandidate;
            }
        }

        Assert::true($group !== null);

        // initial setup
        /** @var User $student */
        foreach ($group->getStudents() as $student) {
            $membership = $student->findMembershipAsStudent($group);
            $group->removeMembership($membership);
        }

        $this->presenter->groups->flush();

        $request = new Nette\Application\Request(
            'V1:Groups',
            'POST',
            ['action' => 'setOrganizational', 'id' => $group->getId()],
            ['value' => true]
        );

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            BadRequestException::class
        );
    }

    public function testSetArchived()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        // Prepare data
        $groupsLvl2 = $this->getAllGroupsInDepth(
            2,
            function (Group $g) {
                return !$g->isArchived();
            }
        );
        Assert::true(count($groupsLvl2) > 0);

        $group = reset($groupsLvl2);
        $parent = $group->getParentGroup();

        PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'POST',
            ['action' => 'setArchived', 'id' => $parent->getId()],
            ['value' => true]
        );

        $this->presenter->groups->refresh($parent);
        $this->presenter->groups->refresh($group);

        Assert::true($parent->isArchived());
        Assert::true($group->isArchived());
    }

    public function testUnsetArchived()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        // Prepare data
        $archivedGroups = array_filter(
            $this->presenter->groups->findAll(),
            function (Group $g) {
                return $g->isDirectlyArchived();
            }
        );
        Assert::true(count($archivedGroups) > 0);
        $group = reset($archivedGroups);
        Assert::true($group->isArchived());

        PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'POST',
            ['action' => 'setArchived', 'id' => $group->getId()],
            ['value' => false]
        );

        $this->presenter->groups->refresh($group);
        Assert::false($group->isArchived());
    }

    public function testRelocate()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        // Prepare data
        $groupsLvl2 = $this->getAllGroupsInDepth(
            2,
            function (Group $g) {
                return !$g->isArchived();
            }
        );
        Assert::true(count($groupsLvl2) > 0);

        $group = reset($groupsLvl2);
        $parent = $group->getParentGroup();
        $root = $parent->getParentGroup();

        PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Groups',
            'POST',
            ['action' => 'relocate', 'id' => $group->getId(), 'newParentId' => $root->getId()]
        );

        $this->presenter->groups->refresh($root);
        $this->presenter->groups->refresh($parent);
        $this->presenter->groups->refresh($group);

        Assert::equal($group->getParentGroup()->getId(), $root->getId());
    }

    public function testRelocateCreateLoop()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        // Prepare data
        $groupsLvl2 = $this->getAllGroupsInDepth(
            2,
            function (Group $g) {
                return !$g->isArchived();
            }
        );
        Assert::true(count($groupsLvl2) > 0);

        $group = reset($groupsLvl2);
        $parent = $group->getParentGroup();

        Assert::exception(
            function () use ($group, $parent) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Groups',
                    'POST',
                    ['action' => 'relocate', 'id' => $parent->getId(), 'newParentId' => $group->getId()]
                );
            },
            App\Exceptions\BadRequestException::class
        );
    }

    public function testRelocateSelfAsParent()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        // Prepare data
        $groupsLvl2 = $this->getAllGroupsInDepth(
            2,
            function (Group $g) {
                return !$g->isArchived();
            }
        );
        Assert::true(count($groupsLvl2) > 0);
        $group = reset($groupsLvl2);

        Assert::exception(
            function () use ($group) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Groups',
                    'POST',
                    ['action' => 'relocate', 'id' => $group->getId(), 'newParentId' => $group->getId()]
                );
            },
            App\Exceptions\BadRequestException::class
        );
    }

    public function testRelocateToArchived()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        // Prepare data
        $groupsLvl2 = $this->getAllGroupsInDepth(
            2,
            function (Group $g) {
                return !$g->isArchived();
            }
        );
        Assert::true(count($groupsLvl2) > 0);
        $group = reset($groupsLvl2);

        $archivedGroups = array_filter(
            $this->presenter->groups->findAll(),
            function (Group $g) {
                return $g->isArchived();
            }
        );
        Assert::true(count($archivedGroups) > 0);
        $archived = reset($archivedGroups);

        Assert::exception(
            function () use ($group, $archived) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Groups',
                    'POST',
                    ['action' => 'relocate', 'id' => $group->getId(), 'newParentId' => $archived->getId()]
                );
            },
            App\Exceptions\BadRequestException::class
        );
    }

    public function testRemoveAdmin()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $group = $this->presenter->groups->findAll()[1];
        $user = $group->getPrimaryAdmins()->first();

        $request = new Nette\Application\Request(
            'V1:Groups',
            'DELETE',
            ['action' => 'removeAdmin', 'id' => $group->id, 'userId' => $user->getId()]
        );

        /** @var \Nette\Application\Responses\JsonResponse $response */
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result["payload"];
        Assert::equal(200, $result["code"]);

        Assert::equal(false, $group->isAdminOf($user));
    }

}

$testCase = new TestGroupsPresenter();
$testCase->run();
