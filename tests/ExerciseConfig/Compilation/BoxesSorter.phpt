<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\BoxesSorter;
use Tester\Assert;


class TestBoxesSorter extends Tester\TestCase
{
  /** @var BoxesSorter */
  private $sorter;

  public function __construct() {
    $this->sorter = new BoxesSorter();
  }


  public function testEmptyMergeTrees() {
    Assert::noError(function () {
      $this->sorter->sort(array());
    });
  }

  /**
   *  A -> B
   */
  public function testSimpleA() {
    // @todo
  }

  /**
   *  A -> B -> C
   */
  public function testSimpleB() {
    // @todo
  }

  /**
   *  A   B
   *   \ /
   *    C
   */
  public function testSimpleI() {
    // @todo
  }

  /**
   *    A
   *   / \
   *  B   C
   */
  public function testSimpleC() {
    // @todo
  }

  /**
   *    A
   *   / \
   *  B   C
   *       \
   *        D
   */
  public function testSimpleD() {
    // @todo
  }

  /**
   *    A
   *   / \
   *  B   C
   *     / \
   *    D   E
   */
  public function testSimpleE() {
    // @todo
  }

  /**
   *  A
   *   \
   *    B
   *   /|\
   *  C D E
   */
  public function testSimpleF() {
    // @todo
  }

  /**
   *    A
   *   / \
   *  B   C
   *   \ /
   *    D
   */
  public function testSimpleG() {
    // @todo
  }

  /**
   *    A
   *   / \
   *  B   C
   *   \ / \
   *    D   E
   */
  public function testSimpleH() {
    // @todo
  }

  /**
   *         A
   *        / \
   *  ---> B   D
   *  |     \ /
   *  |      C
   *  |     /
   *  ---- E
   */
  public function testCycleA() {
    // @todo
  }

  /**
   *  A -> B
   *  ^    |
   *  |    |
   *  D <- C
   */
  public function testCycleB() {
    // @todo
  }

}

# Testing methods run
$testCase = new TestBoxesSorter();
$testCase->run();
