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
   * Identification of pipeline to which this box belongs to.
   * @var string
   */
  private $pipelineId = null;

  /**
   * Identification of tasks which was compiled from corresponding box.
   * @var string[]
   */
  private $taskIds = [];

  /**
   * Nodes which identify themselves as parent of this node.
   * @var Node[]
   */
  private $parents = array();

  /**
   * Children nodes of this one.
   * @var Node[]
   */
  private $children = array();


  /**
   * Node constructor.
   * @param PortNode $node
   */
  public function __construct(PortNode $node) {
    $this->box = $node->getBox();
    $this->pipelineId = $node->getPipelineId();
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
   * Pipeline identification for corresponding box.
   * @return string|null
   */
  public function getPipelineId(): ?string {
    return $this->pipelineId;
  }

  /**
   * Set pipeline identification of box.
   * @param string|null $pipelineId
   */
  public function setPipelineId(?string $pipelineId) {
    $this->pipelineId = $pipelineId;
  }

  /**
   * Return task identifications associated with this node.
   * If there is none, ask parents for their task identifications.
   * @return string[]
   */
  public function getTaskIds(): array {
    if (empty($this->taskIds)) {
      $taskIds = array();
      foreach ($this->parents as $parent) {
        $taskIds = array_merge($taskIds, $parent->getTaskIds());
      }
      return $taskIds;
    }

    return $this->taskIds;
  }

  /**
   * Add task identification to internal array.
   * @param string $taskId
   */
  public function addTaskId(string $taskId) {
    $this->taskIds[] = $taskId;
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
    if(($key = array_search($parent, $this->parents)) !== false){
      unset($this->parents[$key]);
    }
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
    if(($key = array_search($child, $this->children)) !== false){
      unset($this->children[$key]);
    }
  }

}
