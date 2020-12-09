<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\InvalidArgumentException;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseTag;
use App\Model\Entity\LocalizedExercise;
use App\Model\Entity\Pipeline;
use App\Model\Entity\Group;
use App\Security\AccessManager;
use App\V1Module\Presenters\ExercisesPresenter;
use Tester\Assert;


/**
 * @testCase
 */
class TestExercisesPresenter extends Tester\TestCase
{
    private $adminLogin = "admin@admin.com";
    private $groupSupervisorLogin = "demoGroupSupervisor@example.com";

    /** @var ExercisesPresenter */
    protected $presenter;

    /** @var Kdyby\Doctrine\EntityManager */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var App\Model\Repository\RuntimeEnvironments */
    protected $runtimeEnvironments;

    /** @var App\Model\Repository\HardwareGroups */
    protected $hardwareGroups;

    /** @var App\Model\Repository\SupplementaryExerciseFiles */
    protected $supplementaryFiles;

    /** @var App\Model\Repository\Logins */
    protected $logins;

    /** @var Nette\Security\User */
    private $user;

    /** @var App\Model\Repository\Exercises */
    protected $exercises;

    /** @var App\Model\Repository\Assignments */
    protected $assignments;

    /** @var App\Model\Repository\Pipelines */
    protected $pipelines;

    /** @var App\Model\Repository\AttachmentFiles */
    protected $attachmentFiles;

    /** @var App\Model\Repository\Instances */
    protected $instances;

    /** @var App\Model\Repository\Groups */
    protected $groups;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->runtimeEnvironments = $container->getByType(\App\Model\Repository\RuntimeEnvironments::class);
        $this->hardwareGroups = $container->getByType(\App\Model\Repository\HardwareGroups::class);
        $this->supplementaryFiles = $container->getByType(\App\Model\Repository\SupplementaryExerciseFiles::class);
        $this->logins = $container->getByType(\App\Model\Repository\Logins::class);
        $this->exercises = $container->getByType(App\Model\Repository\Exercises::class);
        $this->assignments = $container->getByType(App\Model\Repository\Assignments::class);
        $this->pipelines = $container->getByType(App\Model\Repository\Pipelines::class);
        $this->attachmentFiles = $container->getByType(\App\Model\Repository\AttachmentFiles::class);
        $this->instances = $container->getByType(\App\Model\Repository\Instances::class);
        $this->groups = $container->getByType(\App\Model\Repository\Groups::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);

        $this->presenter = PresenterTestHelper::createPresenter($this->container, ExercisesPresenter::class);
    }

    protected function tearDown()
    {
        Mockery::close();

        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    public function testListAllExercises()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $request = new Nette\Application\Request('V1:Exercises', 'GET', ['action' => 'default']);
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::same(
            array_map(
                function (Exercise $exercise) {
                    return $exercise->getId();
                },
                $this->presenter->exercises->findAll()
            ),
            array_map(
                function ($item) {
                    return $item["id"];
                },
                $result['payload']['items']
            )
        );
        Assert::count(count($this->presenter->exercises->findAll()), $result['payload']['items']);
        Assert::count($result['payload']['totalCount'], $this->presenter->exercises->findAll());
    }

    public function testListAllExercisesPagination()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $request = new Nette\Application\Request(
            'V1:Exercises',
            'GET',
            ['action' => 'default', 'offset' => 1, 'limit' => 1]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::same($this->presenter->exercises->findAll()[1]->getId(), $result['payload']['items'][0]['id']);
        Assert::count(1, $result['payload']['items']);
        Assert::count($result['payload']['totalCount'], $this->presenter->exercises->findAll());
    }

    public function testAdminListSearchExercises()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $request = new Nette\Application\Request(
            'V1:Exercises',
            'GET',
            ['action' => 'default', 'filters' => ['search' => 'An']]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(5, $result['payload']['items']);
    }

    public function testSupervisorListSearchExercises()
    {
        $token = PresenterTestHelper::login($this->container, $this->groupSupervisorLogin);

        $request = new Nette\Application\Request(
            'V1:Exercises',
            'GET',
            ['action' => 'default', 'filters' => ['search' => 'An']]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(4, $result['payload']['items']);
    }

    public function testAdminListFilterGroupsExercises()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $groups = array_filter(
            $this->presenter->groups->findAll(),
            function (Group $g) {
                $texts = $g->getLocalizedTexts()->getValues();
                return reset($texts)->getName() === 'Demo group';
            }
        );
        Assert::true(count($groups) === 1);
        $group = reset($groups);

        $request = new Nette\Application\Request(
            'V1:Exercises',
            'GET',
            ['action' => 'default', 'filters' => ['groupsIds' => $group->getId()]]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(
            6,
            $result['payload']['items']
        ); // total 7 exercises, but one is in the child group (filtered out)
    }

    public function testAdminListFilterEnvExercises()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'GET',
            ['action' => 'default', 'filters' => ['runtimeEnvironments' => 'mono']]
        );
        Assert::count(1, $payload['items']);
    }

    public function testAdminListFilterTagsExercises1()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'GET',
            ['action' => 'default', 'filters' => ['tags' => ['tag1', 'tag2']]]
        );
        Assert::count(1, $payload['items']);
    }

    public function testAdminListFilterTagsExercises2()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'GET',
            ['action' => 'default', 'filters' => ['tags' => ['tag1', 'tag3']]]
        );
        Assert::count(3, $payload['items']);
    }

    public function testAdminListFilterTagsExercises3()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'GET',
            ['action' => 'default', 'filters' => ['tags' => ['notExistingTag']]]
        );
        Assert::count(0, $payload['items']);
    }

    public function testGetAllExercisesAuthors()
    {
        $instances = $this->instances->findAll();
        $instance = reset($instances);

        $token = PresenterTestHelper::login($this->container, $this->adminLogin);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'GET',
            ['action' => 'authors', 'instanceId' => $instance->getId()]
        );

        Assert::count(2, $payload);
        $emails = ['admin@admin.com', 'demoGroupSupervisor2@example.com'];
        Assert::contains($payload[0]['privateData']['email'], $emails);
        Assert::contains($payload[1]['privateData']['email'], $emails);
    }

    public function testGetGroupExercisesAuthors()
    {
        $instances = $this->instances->findAll();
        $instance = reset($instances);
        $groups = $this->groups->findByName("en", "Demo group", $instance);
        $group = reset($groups);

        $token = PresenterTestHelper::login($this->container, $this->adminLogin);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'GET',
            ['action' => 'authors', 'groupId' => $group->getId()]
        );

        Assert::count(2, $payload);
        $emails = ['admin@admin.com', 'demoGroupSupervisor2@example.com'];
        Assert::contains($payload[0]['privateData']['email'], $emails);
        Assert::contains($payload[1]['privateData']['email'], $emails);
    }

    public function testGetGroupExercisesAuthorsEmpty()
    {
        $instances = $this->instances->findAll();
        $instance = reset($instances);
        $groups = $this->groups->findByName("en", "Private group", $instance);
        $group = reset($groups);

        $token = PresenterTestHelper::login($this->container, $this->adminLogin);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'GET',
            ['action' => 'authors', 'groupId' => $group->getId()]
        );

        Assert::count(0, $payload);
    }

    public function testListExercisesByIds()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);
        $exercises = $this->exercises->findAll();
        $first = $exercises[0];
        $second = $exercises[1];

        $request = new Nette\Application\Request(
            'V1:Exercises',
            'POST',
            ['action' => 'listByIds'],
            ['ids' => [$first->getId(), $second->getId()]]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(2, $result['payload']);
    }

    public function testDetail()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        $allExercises = $this->presenter->exercises->findAll();
        $exercise = array_pop($allExercises);

        $request = new Nette\Application\Request('V1:Exercises', 'GET', ['action' => 'detail', 'id' => $exercise->id]);
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::same($exercise->getId(), $result['payload']['id']);
    }

    public function testUpdateDetail()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);

        $allExercises = $this->presenter->exercises->findAll();
        $exercise = array_pop($allExercises);

        $request = new Nette\Application\Request(
            'V1:Exercises',
            'POST',
            ['action' => 'updateDetail', 'id' => $exercise->id],
            [
                'version' => 1,
                'difficulty' => 'super hard',
                'isPublic' => false,
                'localizedTexts' => [
                    [
                        'locale' => 'cs',
                        'text' => 'new descr',
                        'name' => 'new name',
                        'description' => 'some neaty description'
                    ]
                ],
                'solutionFilesLimit' => 3,
                'solutionSizeLimit' => 42,
                'mergeJudgeLogs' => false,
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal('super hard', $result['payload']['difficulty']);
        Assert::equal(false, $result['payload']['isPublic']);

        $updatedLocalizedTexts = $result['payload']['localizedTexts'];
        Assert::count(count($exercise->localizedTexts), $updatedLocalizedTexts);

        /** @var LocalizedExercise $localized */
        foreach ($exercise->localizedTexts as $localized) {
            Assert::count(
                1,
                array_filter(
                    $updatedLocalizedTexts,
                    function (LocalizedExercise $text) use ($localized) {
                        return $text->getLocale() === $localized->getLocale();
                    }
                )
            );
        }

        Assert::count(
            1,
            array_filter(
                $updatedLocalizedTexts,
                function (LocalizedExercise $text) {
                    return $text->getLocale() === "cs" && $text->getAssignmentText() === "new descr";
                }
            )
        );
        Assert::equal(3, $result['payload']['solutionFilesLimit']);
        Assert::equal(42, $result['payload']['solutionSizeLimit']);
    }

    public function testValidatePipeline()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $exercise = current($this->presenter->exercises->findAll());

        $request = new Nette\Application\Request(
            'V1:Exercises',
            'POST',
            ['action' => 'validate', 'id' => $exercise->getId()],
            ['version' => 2]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);

        Assert::true(is_array($payload));
        Assert::true(array_key_exists("versionIsUpToDate", $payload));
        Assert::false($payload["versionIsUpToDate"]);
    }

    public function testCreate()
    {
        $token = PresenterTestHelper::login($this->container, $this->adminLogin);

        /** @var AccessManager $accessManager */
        $accessManager = $this->container->getByType(AccessManager::class);
        $decodedToken = $accessManager->decodeToken($token);

        $group = current($this->presenter->groups->findAll());

        $request = new Nette\Application\Request(
            'V1:Exercises',
            'POST',
            ['action' => 'create'],
            ['groupId' => $group->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        /** @var Exercise $payload */
        $payload = $result['payload'];

        Assert::equal($decodedToken->getPayload("sub"), $payload["authorId"]);
        Assert::count(1, $payload["localizedTexts"]);
        $firstLocalizedText = $payload["localizedTexts"][0];
        Assert::equal("Exercise by " . $this->user->identity->getUserData()->getName(), $firstLocalizedText->getName());
        Assert::equal($group->getId(), $payload["groupsIds"][0]);
    }

    public function testRemove()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);

        $exercise = current($this->presenter->exercises->findAll());

        $request = new Nette\Application\Request(
            'V1:Exercises',
            'DELETE',
            ['action' => 'remove', 'id' => $exercise->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);

        Assert::exception(
            function () use ($exercise) {
                $this->presenter->exercises->findOrThrow($exercise->getId());
            },
            NotFoundException::class
        );
    }

    public function testGetPipelines()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        // prepare pipelines into exercise
        $user = $this->logins->getUser(PresenterTestHelper::ADMIN_LOGIN, PresenterTestHelper::ADMIN_PASSWORD, new Nette\Security\Passwords());
        $pipeline = current($this->pipelines->findAll());
        $pipeline1 = Pipeline::forkFrom($user, $pipeline);
        $pipeline2 = Pipeline::forkFrom($user, $pipeline);
        $exercise = current($this->exercises->findAll());
        $exercise->addPipeline($pipeline1);
        $exercise->addPipeline($pipeline2);
        $this->pipelines->persist($pipeline1, false);
        $this->pipelines->persist($pipeline2, false);
        $this->pipelines->flush();
        $this->exercises->persist($exercise);

        $request = new Nette\Application\Request(
            "V1:Exercises",
            'GET',
            ['action' => 'getPipelines', 'id' => $exercise->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $payload = $result['payload'];
        Assert::count(2, $payload);

        $expectedIds = [$pipeline1->getId(), $pipeline2->getId()];
        $actualIds = array_map(
            function ($item) {
                return $item["id"];
            },
            $result['payload']
        );
        Assert::equal(sort($expectedIds), sort($actualIds));
    }

    public function testAssignments()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $exercise = null;
        foreach ($this->exercises->findAll() as $e) {
            if ($e->getAssignments()->count() > 0) {
                $exercise = $e;
                break;
            }
        }
        Assert::notEqual(null, $exercise);

        $request = new Nette\Application\Request(
            "V1:Exercises",
            'GET',
            ['action' => 'assignments', 'id' => $exercise->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $payload = $result['payload'];
        Assert::count($exercise->getAssignments()->count(), $payload);

        Assert::same(
            $exercise->getAssignments()->map(
                function ($assignment) {
                    return $assignment->getId();
                }
            )->toArray(),
            array_map(
                function ($item) {
                    return $item["id"];
                },
                $payload
            )
        );
    }

    public function testForkFromToGroup()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);

        $user = $this->logins->getUser(PresenterTestHelper::ADMIN_LOGIN, PresenterTestHelper::ADMIN_PASSWORD, new Nette\Security\Passwords());
        $exercise = current($this->presenter->exercises->findAll());
        $group = current($this->presenter->groups->findAll());

        $request = new Nette\Application\Request(
            'V1:Exercises',
            'POST',
            ['action' => 'forkFrom', 'id' => $exercise->getId()],
            ['groupId' => $group->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        /** @var Exercise $forked */
        $forked = $result['payload'];

        foreach ($forked["localizedTexts"] as $text) {
            Assert::true($exercise->getLocalizedTexts()->contains($text));
        }
        Assert::equal(1, $forked["version"]);
        Assert::equal($user->getId(), $forked["authorId"]);
        Assert::equal(1, count($forked["groupsIds"]));
        Assert::equal($group->getId(), $forked["groupsIds"][0]);
    }

    public function testHardwareGroups()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);

        $exercise = current($this->presenter->exercises->findAll());

        $request = new Nette\Application\Request(
            'V1:Exercises',
            'POST',
            ['action' => 'hardwareGroups', 'id' => $exercise->getId()],
            [
                'hwGroups' => [
                    "group1"
                ]
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $payload = $result["payload"];
        Assert::count(1, $payload["hardwareGroups"]);
        Assert::equal("group1", $payload["hardwareGroups"][0]->getId());
    }

    public function testAttachGroup()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);

        $exercise = current($this->presenter->exercises->findAll());
        $group = current($this->presenter->groups->findAll());

        $request = new Nette\Application\Request(
            'V1:Exercises',
            'POST',
            ['action' => 'attachGroup', 'id' => $exercise->getId(), 'groupId' => $group->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        /** @var Exercise $payload */
        $payload = $result['payload'];
        Assert::count(2, $payload["groupsIds"]);
        Assert::contains($group->getId(), $payload["groupsIds"]);
    }

    public function testLastDetachGroup()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);

        $exercise = current($this->presenter->exercises->findAll());
        $group = $exercise->getGroups()->first();

        Assert::count(1, $exercise->getGroups());

        $request = new Nette\Application\Request(
            'V1:Exercises',
            'DELETE',
            ['action' => 'detachGroup', 'id' => $exercise->getId(), 'groupId' => $group->getId()]
        );
        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            BadRequestException::class
        );
    }

    public function testDetachGroup()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);

        $exercise = current($this->presenter->exercises->findAll());
        $group1 = $this->presenter->groups->findAll()[0];
        $group2 = $exercise->getGroups()->first();

        $exercise->addGroup($group1);
        $this->presenter->exercises->flush();

        $request = new Nette\Application\Request(
            'V1:Exercises',
            'DELETE',
            ['action' => 'detachGroup', 'id' => $exercise->getId(), 'groupId' => $group1->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        /** @var Exercise $payload */
        $payload = $result['payload'];
        Assert::count(1, $payload["groupsIds"]);
    }

    public function testAttachPipeline()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);

        $exercise = current($this->presenter->exercises->findAll());
        $pipeline = current($this->presenter->pipelines->findAll());

        /** @var Exercise $payload */
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'POST',
            ['action' => 'attachPipeline', 'id' => $exercise->getId(), 'pipelineId' => $pipeline->getId()]
        );

        Assert::equal($exercise->getId(), $payload["id"]);
        $this->presenter->exercises->refresh($exercise);
        Assert::true($exercise->getPipelines()->contains($pipeline));
    }

    public function testDetachPipeline()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);

        $exercise = current($this->presenter->exercises->findAll());
        $pipeline = current($this->presenter->pipelines->findAll());
        $exercise->addPipeline($pipeline);
        $this->presenter->exercises->flush();
        Assert::true($exercise->getPipelines()->contains($pipeline));

        /** @var Exercise $payload */
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'POST',
            ['action' => 'detachPipeline', 'id' => $exercise->getId(), 'pipelineId' => $pipeline->getId()]
        );

        Assert::equal($exercise->getId(), $payload["id"]);
        $this->presenter->exercises->refresh($exercise);
        Assert::false($exercise->getPipelines()->contains($pipeline));
    }

    public function testAllTags()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'GET',
            ['action' => 'allTags']
        );

        Assert::equal(["tag1", "tag2", "tag3"], $payload);
    }

    public function testTagsStats()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'GET',
            ['action' => 'tagsStats']
        );
        Assert::equal(["tag1" => '1', "tag2" => '1', "tag3" => '2'], $payload);
    }

    public function testTagsRename()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);
        $tag = 'tag3';
        $renameTo = 'tagX';
        $exercises = array_filter(
            $this->presenter->exercises->findAll(),
            function ($exercise) use ($tag) {
                return $this->presenter->exerciseTags->findByNameAndExercise($tag, $exercise) !== null;
            }
        );
        Assert::true(count($exercises) > 0);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'GET',
            ['action' => 'tagsUpdateGlobal', 'tag' => $tag, 'renameTo' => $renameTo]
        );

        Assert::equal(count($exercises), $payload['count']);
        foreach ($exercises as $exercise) {
            Assert::true($this->presenter->exerciseTags->findByNameAndExercise($renameTo, $exercise) !== null);
        }
    }

    public function testTagsRenameCollide()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);
        Assert::exception(
            function () {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Exercises',
                    'GET',
                    ['action' => 'tagsUpdateGlobal', 'tag' => 'tag3', 'renameTo' => 'tag2']
                );
            },
            InvalidArgumentException::class
        );
    }

    public function testTagsRenameForce()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);
        $tag = 'tag3';
        $renameTo = 'tag2';
        $exercises = array_filter(
            $this->presenter->exercises->findAll(),
            function ($exercise) use ($tag) {
                return $this->presenter->exerciseTags->findByNameAndExercise($tag, $exercise) !== null;
            }
        );
        Assert::true(count($exercises) > 0);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'GET',
            ['action' => 'tagsUpdateGlobal', 'tag' => $tag, 'renameTo' => $renameTo, 'force' => true]
        );

        Assert::equal(count($exercises), $payload['count']);
        $stats = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'GET',
            ['action' => 'tagsStats']
        );
        Assert::equal(["tag1" => '1', "tag2" => '3'], $stats);
    }

    public function testTagsRemove()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);
        $tag = 'tag3';
        $exercises = array_filter(
            $this->presenter->exercises->findAll(),
            function ($exercise) use ($tag) {
                return $this->presenter->exerciseTags->findByNameAndExercise($tag, $exercise) !== null;
            }
        );
        Assert::true(count($exercises) > 0);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'GET',
            ['action' => 'tagsRemoveGlobal', 'tag' => $tag]
        );

        Assert::equal(count($exercises), $payload['count']);
        $stats = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'GET',
            ['action' => 'tagsStats']
        );
        Assert::equal(["tag1" => '1', "tag2" => '1'], $stats);
    }

    public function testAddTag()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);

        $exercise = current($this->presenter->exercises->findAll());
        $newTagName = "newAddTagName";
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'POST',
            ['action' => 'addTag', 'id' => $exercise->getId(), 'name' => $newTagName]
        );

        Assert::contains($newTagName, $payload["tags"]);
    }

    public function testRemoveTag()
    {
        PresenterTestHelper::login($this->container, $this->adminLogin);

        $exercise = current($this->presenter->exercises->findAll());
        $user = current($this->presenter->users->findAll());
        $tagName = "removeTagName";
        $exercise->addTag(new ExerciseTag($tagName, $user, $exercise));
        $this->exercises->flush();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Exercises',
            'DELETE',
            ['action' => 'removeTag', 'id' => $exercise->getId(), 'name' => $tagName]
        );

        Assert::notContains($tagName, $payload["tags"]);
    }
}

$testCase = new TestExercisesPresenter();
$testCase->run();
