<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\SisHelper;
use App\Model\Entity\ExternalLogin;
use App\Model\Entity\SisGroupBinding;
use App\Model\Entity\SisValidTerm;
use App\Model\Entity\User;
use App\Model\Repository\Groups;
use App\Model\Repository\SisGroupBindings;
use App\Model\Repository\SisValidTerms;
use App\Model\View\GroupViewFactory;
use App\V1Module\Presenters\SisPresenter;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Tester\Assert;
use Tester\TestCase;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * @testCase
 */
class TestSisPresenter extends TestCase
{
    private const DATA_DIR = __DIR__ . '/sis_data';

    /** @var MockHandler */
    private $httpHandler;

    /** @var SisPresenter */
    private $presenter;

    private $container;

    private $em;

    /** @var Nette\Security\User */
    private $user;

    /** @var SisGroupBindings */
    private $bindings;

    /** @var SisValidTerms */
    private $terms;

    /** @var Groups */
    private $groups;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(Nette\Security\User::class);
        $this->bindings = $container->getByType(SisGroupBindings::class);
        $this->terms = $container->getByType(SisValidTerms::class);
        $this->groups = $container->getByType(Groups::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, SisPresenter::class);
        $this->httpHandler = new MockHandler();
        $this->presenter->sisHelper = new SisHelper(
            'https://api.sis.tld/rest.php',
            'faculty',
            'secret',
            HandlerStack::create($this->httpHandler)
        );
    }

    public function testGetSupervisedCourses()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var User $user */
        $user = $this->user->getIdentity()->getUserData();
        $login = new ExternalLogin($user, "cas-uk", "12345678");
        $this->em->persist($login);
        $this->em->flush();

        $this->httpHandler->append(
            new Response(200, [], file_get_contents(self::DATA_DIR . '/teacher_simple.json'))
        );

        /** @var JsonResponse $response */
        $response = $this->presenter->run(
            new Request(
                'V1:Sis',
                'GET',
                [
                'action' => 'supervisedCourses',
                'userId' => $user->getId(),
                'year' => 2016,
                'term' => 2
                ]
            )
        );
        Assert::type(JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::same(200, $result['code']);

        $payload = $result['payload'];
        Assert::count(2, $payload);
        Assert::count(2, $payload['courses']);
    }

    public function testGetSupervisedCoursesHybrid()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var User $user */
        $user = $this->user->getIdentity()->getUserData();
        $login = new ExternalLogin($user, "cas-uk", "12345678");
        $this->em->persist($login);
        $this->em->flush();

        $this->httpHandler->append(
            new Response(200, [], file_get_contents(self::DATA_DIR . '/hybrid_simple.json'))
        );

        /** @var JsonResponse $response */
        $response = $this->presenter->run(
            new Request(
                'V1:Sis',
                'GET',
                [
                'action' => 'supervisedCourses',
                'userId' => $user->getId(),
                'year' => 2016,
                'term' => 2
                ]
            )
        );
        Assert::type(JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::same(200, $result['code']);

        $payload = $result['payload'];
        Assert::count(2, $payload);
    }

    public function testCreateGroup()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var User $user */
        $user = $this->user->getIdentity()->getUserData();
        $login = new ExternalLogin($user, "cas-uk", "12345678");
        $this->em->persist($login);
        $term = new SisValidTerm(2016, 2);
        $this->em->persist($term);
        $this->em->flush();

        $this->httpHandler->append(
            new Response(200, [], file_get_contents(self::DATA_DIR . '/teacher_simple.json'))
        );

        $courseId = '16bNSWI153x01';

        /** @var JsonResponse $response */
        $response = $this->presenter->run(
            new Request(
                'V1:Sis',
                'POST',
                [
                'action' => 'createGroup',
                'courseId' => $courseId
                ],
                [
                    'parentGroupId' => $this->groups->findAll()[0]->getId()
                ]
            )
        );
        Assert::type(JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::same(200, $result['code']);

        $group = $result['payload'];
        Assert::same("NSWI153", $group["externalId"]);
        Assert::count(2, $group["localizedTexts"]);

        $en = $group["localizedTexts"][0];
        Assert::notSame(null, $en);
        Assert::same("Advanced Technologies for Web Applications (Wed, 15:40, Even weeks, SW1)", $en->getName());
        Assert::same("The Lorem ipsum", $en->getDescription());

        $cs = $group["localizedTexts"][1];
        Assert::notSame(null, $cs);
        Assert::same("Pokrocile technologie webovych aplikaci (St, 15:40, Sudé týdny, SW1)", $cs->getName());
        Assert::same("Lorem ipsum", $cs->getDescription());

        $groupEntity = $this->presenter->groups->findOrThrow($group["id"]);
        Assert::notSame(null, $this->bindings->findByGroupAndCode($groupEntity, $courseId));
    }

    public function testStatus()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var User $user */
        $user = $this->user->getIdentity()->getUserData();
        $login = new ExternalLogin($user, "cas-uk", "12345678");
        $this->em->persist($login);

        $term_1 = new SisValidTerm(2016, 1);
        $term_2 = new SisValidTerm(2016, 2);

        $this->em->persist($term_1);
        $this->em->persist($term_2);
        $this->em->flush();

        /** @var JsonResponse $response */
        $response = $this->presenter->run(
            new Request(
                'V1:Sis',
                'GET',
                [
                'action' => 'status'
                ]
            )
        );

        $result = $response->getPayload();

        Assert::type(JsonResponse::class, $response);
        Assert::same(200, $result['code']);
        $payload = $result['payload'];

        Assert::true($payload['accessible']);
        Assert::count(2, $payload['terms']);
    }

    public function testStatusNoCasAccount()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var User $user */
        $user = $this->user->getIdentity()->getUserData();

        /** @var JsonResponse $response */
        $response = $this->presenter->run(
            new Request(
                'V1:Sis',
                'GET',
                [
                'action' => 'status'
                ]
            )
        );

        $result = $response->getPayload();

        Assert::type(JsonResponse::class, $response);
        Assert::same(200, $result['code']);
        $payload = $result['payload'];

        Assert::false($payload['accessible']);
    }

    public function testStudentGroups()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var User $user */
        $user = $this->user->getIdentity()->getUserData();
        $login = new ExternalLogin($user, "cas-uk", "12345678");
        $this->em->persist($login);

        $term_1 = new SisValidTerm(2016, 1);
        $term_2 = new SisValidTerm(2016, 2);

        $this->em->persist($term_1);
        $this->em->persist($term_2);

        $groups = $this->groups->findAll();
        $group_1 = $groups[0];
        $binding_1 = new SisGroupBinding($group_1, "16bNPRG042p1");
        $this->em->persist($binding_1);

        $group_2 = $groups[1];
        $binding_2 = new SisGroupBinding($group_2, "16bNSWI153x01");
        $this->em->persist($binding_2);

        $this->em->flush();

        $this->httpHandler->append(
            new Response(200, [], file_get_contents(self::DATA_DIR . '/student_simple.json'))
        );

        /** @var JsonResponse $response */
        $response = $this->presenter->run(
            new Request(
                'V1:Sis',
                'GET',
                [
                'action' => 'subscribedCourses',
                'userId' => $user->getId(),
                'year' => 3054,
                'term' => 1
                ]
            )
        );

        $result = $response->getPayload();

        Assert::type(JsonResponse::class, $response);
        Assert::same(200, $result['code']);

        $payload = $result['payload'];

        $returnedGroups = array_map(
            function (array $data) {
                return $data["id"];
            },
            $payload['groups']
        );
        $expected = [$group_2->getId(), $group_1->getId()];
        sort($expected);
        sort($returnedGroups);
        Assert::equal($expected, $returnedGroups);

        $returnedCourses = array_map(
            function (array $course) {
                return $course['course']->getCode();
            },
            $payload['courses']
        );
        sort($returnedCourses);
        Assert::equal(['16bNPRG042p1', '16bNSWI153x01'], $returnedCourses);
    }

    public function testBindingsAreReturned()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var User $user */
        $user = $this->user->getIdentity()->getUserData();
        $login = new ExternalLogin($user, "cas-uk", "12345678");
        $this->em->persist($login);

        $term_1 = new SisValidTerm(2016, 1);
        $term_2 = new SisValidTerm(2016, 2);

        $this->em->persist($term_1);
        $this->em->persist($term_2);

        $groups = $this->groups->findAll();
        $group = $groups[0];
        $binding_1 = new SisGroupBinding($group, "code1");
        $this->em->persist($binding_1);
        $binding_2 = new SisGroupBinding($group, "code2");
        $this->em->persist($binding_2);
        $this->em->flush();

        /** @var GroupViewFactory $groupViewFactory */
        $groupViewFactory = $this->container->getByType(GroupViewFactory::class);

        $view = $groupViewFactory->getGroup($group);
        Assert::count(2, $view["privateData"]["bindings"]["sis"]);

        sort($view["privateData"]["bindings"]["sis"]);
        Assert::equal("code1", $view["privateData"]["bindings"]["sis"][0]);
        Assert::equal("code2", $view["privateData"]["bindings"]["sis"][1]);
    }

    public function testBindGroup()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var User $user */
        $user = $this->user->getIdentity()->getUserData();
        $login = new ExternalLogin($user, "cas-uk", "12345678");
        $this->em->persist($login);
        $term = new SisValidTerm(2016, 2);
        $this->em->persist($term);
        $this->em->flush();

        $this->httpHandler->append(
            new Response(200, [], file_get_contents(self::DATA_DIR . '/teacher_simple.json'))
        );

        $courseId = '16bNSWI153x01';
        $group = $this->groups->findAll()[0];

        /** @var JsonResponse $response */
        $response = $this->presenter->run(
            new Request(
                'V1:Sis',
                'POST',
                [
                'action' => 'bindGroup',
                'courseId' => $courseId
                ],
                [
                    'groupId' => $group->getId()
                ]
            )
        );
        Assert::type(JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::same(200, $result['code']);

        Assert::contains($courseId, $result['payload']['privateData']['bindings']['sis']);
    }

    public function testUnbindGroup()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var User $user */
        $user = $this->user->getIdentity()->getUserData();
        $login = new ExternalLogin($user, "cas-uk", "12345678");
        $this->em->persist($login);
        $term = new SisValidTerm(2016, 2);
        $this->em->persist($term);
        $this->em->flush();

        $this->httpHandler->append(
            new Response(200, [], file_get_contents(self::DATA_DIR . '/teacher_simple.json'))
        );

        $courseId = '16bNSWI153x01';
        $group = $this->groups->findAll()[0];

        $binding = new SisGroupBinding($group, $courseId);
        $this->bindings->persist($binding);

        /** @var JsonResponse $response */
        $response = $this->presenter->run(
            new Request(
                'V1:Sis',
                'POST',
                [
                'action' => 'unbindGroup',
                'courseId' => $courseId,
                'groupId' => $group->getId()
                ]
            )
        );

        Assert::type(JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::same(200, $result['code']);
        Assert::null($this->bindings->findByGroupAndCode($group, $courseId));
    }
}

(new TestSisPresenter())->run();
