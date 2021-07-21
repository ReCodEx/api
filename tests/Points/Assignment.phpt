<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use Tester\Assert;
use App\Entity\Assignment;
use App\Model\Repository\Assignments;

/**
 * @testCase
 */
class TestAssignmentPoints extends Tester\TestCase
{
    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Assignments */
    protected $assignments;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->assignments = $container->getByType(Assignments::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
    }

    private static function getRelDateTime(int $dts)
    {
        static $ts = 0;
        if ($ts === 0) {
            $ts = time(); // make sure all relative times will be fixed form one base time
        }
        return (new DateTime())->setTimestamp($dts + $ts);
    }
    private function getAssignment($firstDeadline, int $points1, $secondDeadline = null, int $points2 = 0)
    {
        if (is_int($firstDeadline)) {
            $firstDeadline = self::getRelDateTime($firstDeadline);
        }

        if ($secondDeadline !== null && is_int($secondDeadline)) {
            $secondDeadline = self::getRelDateTime($secondDeadline);
        }

        $assignments = $this->assignments->findAll();
        $assignment = array_pop($assignments);

        $assignment->setFirstDeadline($firstDeadline);
        $assignment->setMaxPointsBeforeFirstDeadline($points1);
        if ($secondDeadline !== null) {
            $assignment->setAllowSecondDeadline(true);
            $assignment->setSecondDeadline($secondDeadline);
            $assignment->setMaxPointsBeforeSecondDeadline($points2);
        } else {
            $assignment->setAllowSecondDeadline(false);
        }
        return $assignment;
    }

    public function testMaxPointsOneDeadline()
    {
        $assignment = $this->getAssignment(0, 42);
        Assert::equal(42, $assignment->getMaxPoints(self::getRelDateTime(-1))); // before deadline
        Assert::equal(0, $assignment->getMaxPoints(self::getRelDateTime(1))); // after deadline
    }

    public function testMaxPointsTwoDeadlines()
    {
        $assignment = $this->getAssignment(0, 42, 10, 5);
        Assert::equal(42, $assignment->getMaxPoints(self::getRelDateTime(-1))); // before first deadline
        Assert::equal(5, $assignment->getMaxPoints(self::getRelDateTime(2))); // after first, before second
        Assert::equal(0, $assignment->getMaxPoints(self::getRelDateTime(15))); // after second deadline
    }

    public function testMaxPointsInterpolation1()
    {
        $assignment = $this->getAssignment(0, 7, 10, 3);
        Assert::false($assignment->getMaxPointsDeadlineInterpolation());
        $assignment->setMaxPointsDeadlineInterpolation();
        Assert::true($assignment->getMaxPointsDeadlineInterpolation());

        Assert::equal(7, $assignment->getMaxPoints(self::getRelDateTime(-1))); // before first deadline
        Assert::equal(6, $assignment->getMaxPoints(self::getRelDateTime(2)));
        Assert::equal(5, $assignment->getMaxPoints(self::getRelDateTime(3)));
        Assert::equal(4, $assignment->getMaxPoints(self::getRelDateTime(6)));
        Assert::equal(3, $assignment->getMaxPoints(self::getRelDateTime(9)));
        Assert::equal(0, $assignment->getMaxPoints(self::getRelDateTime(15))); // after second deadline
    }
}

$testCase = new TestAssignmentPoints();
$testCase->run();
