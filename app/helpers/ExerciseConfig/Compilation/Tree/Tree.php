<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;


/**
 * Class Tree
 */
class Tree {

  /**
   * @var Node[]
   */
  private $rootNodes = array();

  /**
   * @var Node[]
   */
  private $outputNodes = array();


  /**
   * @return Node[]
   */
  public function getRootNodes(): array {
    return $this->rootNodes;
  }

  /**
   * @param Node $node
   */
  public function addRootNode(Node $node) {
    $this->rootNodes[] = $node;
  }

  /**
   * @return Node[]
   */
  public function getOutputNodes(): array {
    return $this->outputNodes;
  }

  /**
   * @param Node $node
   */
  public function addOutputNode(Node $node) {
    $this->outputNodes[] = $node;
  }

}
