<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\Notifications\AssignmentCommentsEmailsSender;
use App\Helpers\Notifications\SolutionCommentsEmailsSender;
use App\Model\Entity\Comment;
use App\V1Module\Presenters\CommentsPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * @testCase
 */
class TestCommentsPresenter extends Tester\TestCase
{
    private $userLogin = "user1@example.com";
    private $userPassword = "password1";

    /** @var CommentsPresenter */
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
        $this->presenter = PresenterTestHelper::createPresenter($this->container, CommentsPresenter::class);
    }

    protected function tearDown()
    {
        Mockery::close();

        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    public function testGetCommentsInNormalThread()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        $request = new Nette\Application\Request('V1:Comments', 'GET', ['action' => 'default',
            'id' => '6b89a6df-f7e8-4c2c-a216-1b7cb4391647']); // mainThread
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal(
            $this->presenter->comments->getThread('6b89a6df-f7e8-4c2c-a216-1b7cb4391647'),
            $result['payload']
        );
        $comments = $result['payload']->jsonSerialize()['comments'];

        // Two comments for this user
        Assert::equal(2, count($comments));
        // But only one public comment
        Assert::equal(
            1,
            count(
                array_filter(
                    $comments,
                    function (Comment $comment) {
                        return $comment->isPrivate();
                    }
                )
            )
        );
    }

    public function testGetCommentsInEmptyThread()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        $request = new Nette\Application\Request('V1:Comments', 'GET', ['action' => 'default',
            'id' => '8308df60-8da5-4ef7-be1f-9a0160409b64']); // emptyThread
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        $comments = $result['payload']->jsonSerialize()['comments'];
        Assert::equal(0, count($comments));
    }

    public function testAddCommentIntoExistingThread()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        $request = new Nette\Application\Request(
            'V1:Comments',
            'POST',
            ['action' => 'addComment', 'id' => '6b89a6df-f7e8-4c2c-a216-1b7cb4391647'], // mainThread
            ['text' => 'some comment text', 'isPrivate' => false]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        /** @var Comment $comment */
        $comment = $result['payload'];
        Assert::false($comment->isPrivate());
        Assert::equal("some comment text", $comment->getText());
        Assert::equal("6b89a6df-f7e8-4c2c-a216-1b7cb4391647", $comment->getCommentThread()->getId());

        // Make sure the assignment was persisted
        Assert::same($this->presenter->comments->findOneBy(['id' => $comment->getId()]), $result['payload']);
    }

    public function testAddCommentIntoExistingAssignmentSolutionThread()
    {
        PresenterTestHelper::login($this->container, $this->userLogin);
        $assignmentSolution = current($this->presenter->assignmentSolutions->findAll());

        // mock emails sender
        $mockSolutionCommentsEmailsSender = Mockery::mock(SolutionCommentsEmailsSender::class);
        $mockSolutionCommentsEmailsSender->shouldReceive("assignmentSolutionComment")->once();
        $this->presenter->solutionCommentsEmailsSender = $mockSolutionCommentsEmailsSender;

        $request = new Nette\Application\Request(
            'V1:Comments',
            'POST',
            ['action' => 'addComment', 'id' => $assignmentSolution->getId()],
            ['text' => 'some comment text', 'isPrivate' => false]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        /** @var Comment $comment */
        $comment = $result['payload'];
        Assert::false($comment->isPrivate());
        Assert::equal("some comment text", $comment->getText());
        Assert::equal($assignmentSolution->getId(), $comment->getCommentThread()->getId());

        // Make sure the assignment was persisted
        Assert::same($this->presenter->comments->findOneBy(['id' => $comment->getId()]), $result['payload']);
    }

    public function testAddCommentIntoExistingReferenceSolutionThread()
    {
        PresenterTestHelper::login($this->container, $this->userLogin);
        $referenceSolution = current($this->presenter->referenceExerciseSolutions->findAll());

        // mock emails sender
        $mockSolutionCommentsEmailsSender = Mockery::mock(SolutionCommentsEmailsSender::class);
        $mockSolutionCommentsEmailsSender->shouldReceive("referenceSolutionComment")->once();
        $this->presenter->solutionCommentsEmailsSender = $mockSolutionCommentsEmailsSender;

        $request = new Nette\Application\Request(
            'V1:Comments',
            'POST',
            ['action' => 'addComment', 'id' => $referenceSolution->getId()],
            ['text' => 'some comment text', 'isPrivate' => false]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        $comment = $result['payload'];
        Assert::false($comment->isPrivate());
        Assert::equal("some comment text", $comment->getText());
        Assert::equal($referenceSolution->getId(), $comment->getCommentThread()->getId());

        // Make sure the assignment was persisted
        Assert::same($this->presenter->comments->findOneBy(['id' => $comment->getId()]), $result['payload']);
    }

    public function testAddAssignmentCommentAndCreateThread()
    {
        PresenterTestHelper::login($this->container, $this->userLogin);
        $assignment = current($this->presenter->assignments->findAll());

        // mock emails sender
        $mockAssigmentCommentsEmailsSender = Mockery::mock(AssignmentCommentsEmailsSender::class);
        $mockAssigmentCommentsEmailsSender->shouldReceive("assignmentComment")->once();
        $this->presenter->assignmentCommentsEmailsSender = $mockAssigmentCommentsEmailsSender;

        $request = new Nette\Application\Request(
            'V1:Comments',
            'POST',
            ['action' => 'addComment', 'id' => $assignment->getId()],
            ['text' => 'some comment text', 'isPrivate' => false]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        $comment = $result['payload'];
        Assert::false($comment->isPrivate());
        Assert::equal("some comment text", $comment->getText());
        Assert::equal($assignment->getId(), $comment->getCommentThread()->getId());

        // Make sure the assignment was persisted
        Assert::same($this->presenter->comments->findOneBy(['id' => $comment->getId()]), $result['payload']);
    }

    public function testAddCommentIntoNonexistingThread()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        $request = new Nette\Application\Request(
            'V1:Comments',
            'POST',
            ['action' => 'addComment', 'id' => '5d45dcd0-50e7-4b2a-a291-cfe4b5fb5cbb'], // dummy thread (nonexist)
            ['text' => 'some comment text', 'isPrivate' => false]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        $comment = $result['payload'];
        Assert::equal("5d45dcd0-50e7-4b2a-a291-cfe4b5fb5cbb", $comment->getCommentThread()->getId());
    }

    public function testSetPrivate()
    {
        PresenterTestHelper::login($this->container, $this->userLogin);

        $comments = $this->presenter->comments->findAll();
        $exampleComment = array_pop($comments);
        $newPrivate = !$exampleComment->isPrivate();
        $id = $exampleComment->getId();

        $request = new Nette\Application\Request(
            'V1:Comments',
            'POST',
            ['action' => 'setPrivate', 'threadId' => '6b89a6df-f7e8-4c2c-a216-1b7cb4391647', 'commentId' => $id],
            ['isPrivate' => $newPrivate]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        $comment = $result['payload'];
        Assert::equal($comment->getId(), $id);
        Assert::true($comment->isPrivate() === $newPrivate);
    }
}

$testCase = new TestCommentsPresenter();
$testCase->run();
