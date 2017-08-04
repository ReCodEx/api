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
   * @param array $rootNodes
   * @return Tree
   */
  public function setRootNodes(array $rootNodes): Tree {
    $this->rootNodes = $rootNodes;
    return $this;
  }

  /**
   * @param Node $node
   * @return Tree
   */
  public function addRootNode(Node $node): Tree {
    $this->rootNodes[] = $node;
    return $this;
  }

  /**
   * @return Node[]
   */
  public function getOutputNodes(): array {
    return $this->outputNodes;
  }

  /**
   * @param array $outputNodes
   * @return Tree
   */
  public function setOutputNodes(array $outputNodes): Tree {
    $this->outputNodes = $outputNodes;
    return $this;
  }

  /**
   * @param Node $node
   * @return Tree
   */
  public function addOutputNode(Node $node): Tree {
    $this->outputNodes[] = $node;
    return $this;
  }

}
