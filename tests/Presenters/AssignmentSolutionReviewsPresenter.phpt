<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\NotFoundException;
use App\Helpers\Notifications\PointsChangedEmailsSender;
use App\Model\Entity\Assignment;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\ReviewComment;
use App\Model\Entity\User;
use App\V1Module\Presenters\AssignmentSolutionReviewsPresenter;
use App\Helpers\Notifications\ReviewsEmailsSender;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * @testCase
 */
class TestAssignmentSolutionReviewsPresenter extends Tester\TestCase
{
    /** @var AssignmentSolutionReviewsPresenter */
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
        $this->presenter = PresenterTestHelper::createPresenter(
            $this->container,
            AssignmentSolutionReviewsPresenter::class
        );
        $mockEmailsSender = Mockery::mock(ReviewsEmailsSender::class);
        $this->presenter->reviewsEmailSender = $mockEmailsSender;
    }

    protected function tearDown()
    {
        Mockery::close();

        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    protected function getSolutionWithoutReview()
    {
        foreach ($this->presenter->assignmentSolutions->findAll() as $solution) {
            if ($solution->getReviewStartedAt() === null && $solution->getReviewedAt() === null) {
                return $solution;
            }
        }
        Assert::fail("No solution without review found.");
    }

    protected function getPendingSolution()
    {
        foreach ($this->presenter->assignmentSolutions->findAll() as $solution) {
            if ($solution->getReviewStartedAt() !== null && $solution->getReviewedAt() === null) {
                return $solution;
            }
        }
        Assert::fail("No pending solution found.");
    }

    protected function getReviewedSolution()
    {
        foreach ($this->presenter->assignmentSolutions->findAll() as $solution) {
            if ($solution->getReviewedAt() !== null) {
                return $solution;
            }
        }
        Assert::fail("No reviewed solution found.");
    }

    public function testGetSolutionReview()
    {
        PresenterTestHelper::login($this->container, "submitUser1@example.com");
        $solution = $this->getReviewedSolution();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'GET',
            ['action' => 'default', 'id' => $solution->getId()]
        );

        Assert::same($solution->getId(), $payload['solution']['id']);
        Assert::count(2, $payload['reviewComments']);
        $issues = array_filter($payload['reviewComments'], function ($comment) {
            return $comment->isIssue();
        });
        Assert::count(1, $issues);
        foreach ($payload['reviewComments'] as $comment) {
            Assert::same($solution->getId(), $comment->getSolution()->getId());
        }
    }

    public function testStartReview()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $solution = $this->getSolutionWithoutReview();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'POST',
            ['action' => 'update', 'id' => $solution->getId()],
            ['close' => false]
        );

        Assert::same($solution->getId(), $payload['solution']['id']);
        Assert::notNull($payload['solution']['review']['startedAt']);
        Assert::count(0, $payload['reviewComments']);

        $this->presenter->assignmentSolutions->refresh($solution);
        Assert::notNull($solution->getReviewStartedAt());
        Assert::null($solution->getReviewedAt());
    }

    public function testStartReviewByNewComment()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $solution = $this->getSolutionWithoutReview();
        $fileName = $solution->getSolution()->getFiles()->first()->getName();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'POST',
            ['action' => 'newComment', 'id' => $solution->getId()],
            [ 'text' => 'Blabla', 'file' => $fileName, 'line' => 42 ]
        );

        Assert::same($solution->getId(), $payload->getSolution()->getId());
        Assert::same('Blabla', $payload->getText());
        Assert::same($fileName, $payload->getFile());
        Assert::same(42, $payload->getLine());
        Assert::false($payload->isIssue());
    }

    public function testStartReviewByNewIssue()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $solution = $this->getSolutionWithoutReview();
        $fileName = $solution->getSolution()->getFiles()->first()->getName();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'POST',
            ['action' => 'newComment', 'id' => $solution->getId()],
            [ 'text' => 'Blabla', 'file' => $fileName, 'line' => 42, 'issue' => true ]
        );

        Assert::same($solution->getId(), $payload->getSolution()->getId());
        Assert::same('Blabla', $payload->getText());
        Assert::same($fileName, $payload->getFile());
        Assert::same(42, $payload->getLine());
        Assert::true($payload->isIssue());
    }

    public function testCreateEmptyReview()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $solution = $this->getSolutionWithoutReview();

        $this->presenter->reviewsEmailSender->shouldReceive("solutionReviewClosed")->with($solution)->andReturn(true)->once();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'POST',
            ['action' => 'update', 'id' => $solution->getId()],
            ['close' => true]
        );

        Assert::same($solution->getId(), $payload['solution']['id']);
        Assert::notNull($payload['solution']['review']['startedAt']);
        Assert::notNull($payload['solution']['review']['closedAt']);
        Assert::count(0, $payload['reviewComments']);

        $this->presenter->assignmentSolutions->refresh($solution);
        Assert::notNull($solution->getReviewStartedAt());
        Assert::notNull($solution->getReviewedAt());
    }

    public function testReopenReview()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $solution = $this->getReviewedSolution();

        $this->presenter->reviewsEmailSender->shouldReceive("solutionReviewReopened")
            ->with($solution, $solution->getReviewedAt())->andReturn(true)->once();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'POST',
            ['action' => 'update', 'id' => $solution->getId()],
            ['close' => false]
        );

        Assert::same($solution->getId(), $payload['solution']['id']);
        Assert::notNull($payload['solution']['review']['startedAt']);
        Assert::null($payload['solution']['review']['closedAt']);
        Assert::count(2, $payload['reviewComments']);

        $this->presenter->assignmentSolutions->refresh($solution);
        Assert::notNull($solution->getReviewStartedAt());
        Assert::null($solution->getReviewedAt());
    }

    public function testCloseReview()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $solution = $this->getPendingSolution();

        $this->presenter->reviewsEmailSender->shouldReceive("solutionReviewClosed")->with($solution)->andReturn(true)->once();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'POST',
            ['action' => 'update', 'id' => $solution->getId()],
            ['close' => true]
        );

        Assert::same($solution->getId(), $payload['solution']['id']);
        Assert::notNull($payload['solution']['review']['startedAt']);
        Assert::notNull($payload['solution']['review']['closedAt']);
        Assert::count(0, $payload['reviewComments']);

        $this->presenter->assignmentSolutions->refresh($solution);
        Assert::notNull($solution->getReviewStartedAt());
        Assert::notNull($solution->getReviewedAt());
        Assert::same(0, $solution->getIssuesCount());
    }

    public function testCloseReviewWithIssue()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $admin = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        $solution = $this->getPendingSolution();
        $issue = new ReviewComment($solution, $admin, 'filename.ext', 19, "Blabla", true);
        $this->presenter->reviewComments->persist($issue);

        $this->presenter->reviewsEmailSender->shouldReceive("solutionReviewClosed")->with($solution)->andReturn(true)->once();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'POST',
            ['action' => 'update', 'id' => $solution->getId()],
            ['close' => true]
        );

        Assert::same($solution->getId(), $payload['solution']['id']);
        Assert::notNull($payload['solution']['review']['startedAt']);
        Assert::notNull($payload['solution']['review']['closedAt']);
        Assert::count(1, $payload['reviewComments']);
        Assert::same($issue->getId(), $payload['reviewComments'][0]->getId());


        $this->presenter->assignmentSolutions->refresh($solution);
        Assert::notNull($solution->getReviewStartedAt());
        Assert::notNull($solution->getReviewedAt());
        Assert::same(1, $solution->getIssuesCount());
    }

    public function testRemoveReview()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $admin = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        $solution = $this->getReviewedSolution();

        $this->presenter->reviewsEmailSender->shouldReceive("solutionReviewRemoved")
            ->with($solution, $solution->getReviewedAt())->andReturn(true)->once();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'DELETE',
            ['action' => 'remove', 'id' => $solution->getId()],
        );

        Assert::same("OK", $payload);

        $this->presenter->assignmentSolutions->refresh($solution);
        Assert::null($solution->getReviewStartedAt());
        Assert::null($solution->getReviewedAt());
        Assert::same(0, $solution->getIssuesCount());
        Assert::count(0, $solution->getReviewComments());
    }

    public function testRemovePendingReview()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $admin = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        $solution = $this->getPendingSolution();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'DELETE',
            ['action' => 'remove', 'id' => $solution->getId()],
        );

        Assert::same("OK", $payload);

        $this->presenter->assignmentSolutions->refresh($solution);
        Assert::null($solution->getReviewStartedAt());
        Assert::null($solution->getReviewedAt());
        Assert::same(0, $solution->getIssuesCount());
        Assert::count(0, $solution->getReviewComments());
    }

    public function testAddReviewComment()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $admin = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        $solution = $this->getPendingSolution();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'POST',
            ['action' => 'newComment', 'id' => $solution->getId()],
            [ 'text' => 'Blabla', 'file' => 'filename.ext', 'line' => 42 ]
        );

        Assert::same($solution->getId(), $payload->getSolution()->getId());
        Assert::same('Blabla', $payload->getText());
        Assert::same('filename.ext', $payload->getFile());
        Assert::same(42, $payload->getLine());
        Assert::false($payload->isIssue());

        $this->presenter->assignmentSolutions->refresh($solution);
        Assert::count(1, $solution->getReviewComments());
    }

    public function testAddCommentToClosedReview()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $admin = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        $solution = $this->getReviewedSolution();
        Assert::same(1, $solution->getIssuesCount());

        $this->presenter->reviewsEmailSender->shouldReceive("newReviewComment")
            ->with($solution, Mockery::any())->andReturn(true)->once();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'POST',
            ['action' => 'newComment', 'id' => $solution->getId()],
            [ 'text' => 'New comment', 'file' => 'filename.ext', 'line' => 12, 'issue' => true ]
        );

        Assert::same($solution->getId(), $payload->getSolution()->getId());
        Assert::same('New comment', $payload->getText());
        Assert::same('filename.ext', $payload->getFile());
        Assert::same(12, $payload->getLine());
        Assert::true($payload->isIssue());

        $this->presenter->assignmentSolutions->refresh($solution);
        Assert::count(3, $solution->getReviewComments());
        Assert::same(2, $solution->getIssuesCount());
    }

    public function testAddCommentToClosedReviewSuppressNotification()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $admin = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        $solution = $this->getReviewedSolution();
        Assert::same(1, $solution->getIssuesCount());

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'POST',
            ['action' => 'newComment', 'id' => $solution->getId()],
            [ 'text' => 'New comment', 'file' => 'filename.ext', 'line' => 12, 'issue' => true, 'suppressNotification' => true ]
        );

        Assert::same($solution->getId(), $payload->getSolution()->getId());
        Assert::same('New comment', $payload->getText());
        Assert::same('filename.ext', $payload->getFile());
        Assert::same(12, $payload->getLine());
        Assert::true($payload->isIssue());

        $this->presenter->assignmentSolutions->refresh($solution);
        Assert::count(3, $solution->getReviewComments());
        Assert::same(2, $solution->getIssuesCount());
    }

    public function testEditReviewComment()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $admin = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        $solution = $this->getPendingSolution();
        $comment = new ReviewComment($solution, $admin, 'filename.ext', 19, "Blabla", true);
        $this->presenter->reviewComments->persist($comment);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'POST',
            ['action' => 'editComment', 'id' => $solution->getId(), 'commentId' => $comment->getId() ],
            [ 'text' => 'New blabla', 'issue' => false ]
        );

        Assert::same($comment->getId(), $payload->getId());
        Assert::same($solution->getId(), $payload->getSolution()->getId());
        Assert::same('New blabla', $payload->getText());
        Assert::false($payload->isIssue());

        $this->presenter->reviewComments->refresh($comment);
        Assert::same('New blabla', $comment->getText());
        Assert::false($comment->isIssue());
    }

    public function testEditCommentOfClosedReview()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $admin = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        $solution = $this->getReviewedSolution();
        $comment = $solution->getReviewComments()->filter(function ($c) {
            return !$c->isIssue();
        })->first();
        Assert::notNull($comment);
        Assert::false($comment->isIssue());

        $this->presenter->reviewsEmailSender->shouldReceive("changedReviewComment")
            ->with($solution, $comment, 'Good job!', true)->andReturn(true)->once();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'POST',
            ['action' => 'editComment', 'id' => $solution->getId(), 'commentId' => $comment->getId() ],
            [ 'text' => 'New blabla', 'issue' => true ]
        );

        Assert::same($comment->getId(), $payload->getId());
        Assert::same($solution->getId(), $payload->getSolution()->getId());
        Assert::same('New blabla', $payload->getText());
        Assert::true($payload->isIssue());

        $this->presenter->reviewComments->refresh($comment);
        Assert::same('New blabla', $comment->getText());
        Assert::true($comment->isIssue());

        $this->presenter->assignmentSolutions->refresh($solution);
        Assert::same(2, $solution->getIssuesCount());
    }

    public function testEditCommentOfClosedReviewSuppressNotification()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $admin = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        $solution = $this->getReviewedSolution();
        $comment = $solution->getReviewComments()->filter(function ($c) {
            return !$c->isIssue();
        })->first();
        Assert::notNull($comment);
        Assert::false($comment->isIssue());

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'POST',
            ['action' => 'editComment', 'id' => $solution->getId(), 'commentId' => $comment->getId() ],
            [ 'text' => 'New blabla', 'issue' => true, 'suppressNotification' => true ]
        );

        Assert::same($comment->getId(), $payload->getId());
        Assert::same($solution->getId(), $payload->getSolution()->getId());
        Assert::same('New blabla', $payload->getText());
        Assert::true($payload->isIssue());

        $this->presenter->reviewComments->refresh($comment);
        Assert::same('New blabla', $comment->getText());
        Assert::true($comment->isIssue());

        $this->presenter->assignmentSolutions->refresh($solution);
        Assert::same(2, $solution->getIssuesCount());
    }

    public function testDeleteReviewComment()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $admin = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        $solution = $this->getPendingSolution();
        $comment = new ReviewComment($solution, $admin, 'filename.ext', 19, "Blabla", true);
        $this->presenter->reviewComments->persist($comment);
        $this->presenter->reviewComments->flush();

        $this->presenter->assignmentSolutions->refresh($solution);
        Assert::count(1, $solution->getReviewComments());

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'DELETE',
            ['action' => 'deleteComment', 'id' => $solution->getId(), 'commentId' => $comment->getId() ],
        );

        Assert::same("OK", $payload);
        $this->presenter->assignmentSolutions->refresh($solution);
        Assert::count(0, $solution->getReviewComments());
    }

    public function testDeleteCommentInClosedReview()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $admin = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        $solution = $this->getReviewedSolution();
        $comment = $solution->getReviewComments()->filter(function ($c) {
            return $c->isIssue();
        })->first();
        Assert::notNull($comment);
        Assert::true($comment->isIssue());

        $this->presenter->reviewsEmailSender->shouldReceive("removedReviewComment")
            ->with($solution, $comment)->andReturn(true)->once();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'DELETE',
            ['action' => 'deleteComment', 'id' => $solution->getId(), 'commentId' => $comment->getId() ],
        );

        Assert::same("OK", $payload);
        $this->presenter->assignmentSolutions->refresh($solution);
        Assert::count(1, $solution->getReviewComments());
        Assert::same(0, $solution->getIssuesCount());
    }

    public function testGetPendingReviews()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        $solution = $this->getPendingSolution();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutionReviews',
            'GET',
            ['action' => 'pending', 'id' => $user->getId() ],
        );

        Assert::count(1, $payload['solutions']);
        Assert::count(1, $payload['assignments']);
        Assert::same($solution->getId(), $payload['solutions'][0]['id']);
        Assert::same($solution->getAssignment()->getId(), $payload['assignments'][0]['id']);
    }
}

(new TestAssignmentSolutionReviewsPresenter())->run();
