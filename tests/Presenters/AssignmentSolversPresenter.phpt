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
        $assignment = $this->assignments->findAll()[0];

        $result = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolvers',
            'GET',
            ['assignmentId' => $assignment->getId()]
        );

        Assert::count(1, $result);
        Assert::equal($assignment->getId(), $result[0]->getAssignment()->getId());
        Assert::equal(4, $result[0]->getLastAttemptIndex());
    }

    public function testFetchGroup()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $assignment = $this->assignments->findAll()[0];

        $result = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolvers',
            'GET',
            ['groupId' => $assignment->getGroup()->getId()]
        );

        Assert::count(1, $result);
        Assert::equal($assignment->getId(), $result[0]->getAssignment()->getId());
        Assert::equal(4, $result[0]->getLastAttemptIndex());
    }

    public function testFetchAssignmentWrongUser()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $assignment = $this->assignments->findAll()[0];

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
