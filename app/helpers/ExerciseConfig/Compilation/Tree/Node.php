<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;

use App\Helpers\ExerciseConfig\Pipeline\Box\Box;
use App\Helpers\ExerciseConfig\VariablesTable;


/**
 * Node representing Box in the compilation of exercise. It can hold additional
 * information regarding box which does not have to be stored there, this can
 * save memory during loading of pipelines and not compiling them.
 * @note Structure used in exercise compilation.
 */
class Node {

  /**
   * Box connected to this node.
   * @var Box
   */
  private $box;

  /**
   * Identification of test to which this box belongs to.
   * @var string
   */
  private $testId = null;

  /**
   * Nodes which identify themselves as parent of this node, ndexed by port
   * name.
   * @var Node[]
   */
  private $parents = array();

  /**
   * Children nodes of this one, indexed by port name.
   * @var Node[]
   */
  private $children = array();


  /**
   * Node constructor.
   * @param PortNode $node
   */
  public function __construct(PortNode $node) {
    $this->box = $node->getBox();
    $this->testId = $node->getTestId();
  }

  /**
   * Get box associated with this node.
   * @return Box
   */
  public function getBox(): Box {
    return $this->box;
  }

  /**
   * Test identification for corresponding box.
   * @return string|null
   */
  public function getTestId(): ?string {
    return $this->testId;
  }

  /**
   * Set test identification of box.
   * @param string|null $testId
   */
  public function setTestId(?string $testId) {
    $this->testId = $testId;
  }

  /**
   * Get parents of this node.
   * @return Node[]
   */
  public function getParents(): array {
    return $this->parents;
  }

  /**
   * Add parent of this node.
   * @param Node $parent
   */
  public function addParent(Node $parent) {
    $this->parents[] = $parent;
  }

  /**
   * Remove given parent from this node.
   * @param Node $parent
   */
  public function removeParent(Node $parent) {
    $this->parents = array_diff($this->parents, array($parent));
  }

  /**
   * Get children of this node.
   * @return Node[]
   */
  public function getChildren(): array {
    return $this->children;
  }

  /**
   * Add child to this node with specified node.
   * @param Node $child
   */
  public function addChild(Node $child) {
    $this->children[] = $child;
  }

  /**
   * Remove given child from children array.
   * @param Node $child
   */
  public function removeChild(Node $child) {
    $this->children = array_diff($this->children, array($child));
  }

}
