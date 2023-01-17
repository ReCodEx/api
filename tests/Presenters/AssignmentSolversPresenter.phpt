<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\BrokerProxy;
use App\V1Module\Presenters\AssignmentSolversPresenter;
use App\Model\Entity\AssignmentSolver;
use App\Model\Repository\Assignments;
use App\Model\Repository\AssignmentSolvers;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;
use Tracy\ILogger;

/**
 * @testCase
 */
class TestAssignmentSolversPresenter extends Tester\TestCase
{
    /** @var AssignmentSolversPresenter */
    protected $presenter;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Nette\Security\User */
    private $user;

    /** @var Assignments */
    protected $assignments;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->assignments = $this->container->getByType(Assignments::class);
        $entityManager = $this->container->getByType(EntityManagerInterface::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, AssignmentSolversPresenter::class);
    }

    protected function tearDown()
    {
        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    public function testFetchAssignment()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $assignments = array_filter($this->assignments->findAll(), function ($a) {
            return $a->getAssignmentSolutions()->count() >= 4;
        });
        Assert::count(1, $assignments);
        $assignment = current($assignments);

        $result = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolvers',
            'GET',
            ['assignmentId' => $assignment->getId()]
        );

        Assert::count(2, $result);
        $indices = [];
        foreach ($result as $r) {
            Assert::equal($assignment->getId(), $r->getAssignment()->getId());
            $indices[] = $r->getLastAttemptIndex();
        }
        sort($indices, SORT_NUMERIC);
        Assert::equal(1, $indices[0]);
        Assert::equal(4, $indices[1]);
    }

    public function testFetchGroup()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $assignments = array_filter($this->assignments->findAll(), function ($a) {
            return $a->getAssignmentSolutions()->count() >= 4;
        });
        Assert::count(1, $assignments);
        $assignment = current($assignments);

        $result = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolvers',
            'GET',
            ['groupId' => $assignment->getGroup()->getId()]
        );

        Assert::count(2, $result);
        $indices = [];
        foreach ($result as $r) {
            Assert::equal($assignment->getId(), $r->getAssignment()->getId());
            $indices[] = $r->getLastAttemptIndex();
        }
        sort($indices, SORT_NUMERIC);
        Assert::equal(1, $indices[0]);
        Assert::equal(4, $indices[1]);
    }

    public function testFetchAssignmentWrongUser()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $assignments = array_filter($this->assignments->findAll(), function ($a) {
            return $a->getAssignmentSolutions()->count() >= 4;
        });
        Assert::count(1, $assignments);
        $assignment = current($assignments);

        $result = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolvers',
            'GET',
            ['assignmentId' => $assignment->getId(), 'userId' => $this->user->getId()]
        );

        Assert::count(0, $result);
    }
}

$testCase = new TestAssignmentSolversPresenter();
$testCase->run();
