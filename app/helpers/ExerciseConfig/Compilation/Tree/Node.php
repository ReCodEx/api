<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;

use App\Helpers\ExerciseConfig\Pipeline\Box\Box;
use App\Helpers\ExerciseConfig\VariablesTable;


/**
 * Node representing Box in the compilation of exercise. It can hold additional
 * information regarding box which does not have to be stored there, this can
 * save memory during loading of pipelines and not compiling them.
 * @note Box does not have to be assigned. If there is none node is only meant
 * to be some kind of bridge between two pipelines.
 * @note Structure used in exercise compilation.
 */
class Node {

  /**
   * Box connected to this node.
   * @var Box
   */
  private $box;

  /**
   * Pipeline variables from exercise configuration which will be used during compilation.
   * @note Needs to be first set before usage.
   * @var VariablesTable
   */
  protected $exerciseConfigVariables;

  /**
   * Variables from environment configuration which will be used during compilation.
   * @note Needs to be first set before usage.
   * @var VariablesTable
   */
  protected $environmentConfigVariables;

  /**
   * Variables from pipeline to which this box belong to, which will be used during compilation.
   * @note Needs to be first set before usage.
   * @var VariablesTable
   */
  protected $pipelineVariables;


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
   * Is this node contained in created tree.
   * Flag regarding tree construction.
   * @var bool
   */
  private $isInTree = false;

  /**
   * Tree was visited during topological sorting.
   * Flag regarding topological sorting of tree.
   * @var bool
   */
  private $visited = false;

  /**
   * Tree was finished and does not have to be processed again.
   * Flag regarding topological sorting of tree.
   * @var bool
   */
  private $finished = false;


  /**
   * Node constructor.
   * @param Box $box
   */
  public function __construct(Box $box) {
    $this->box = $box;
  }

  /**
   * Get box associated with this node.
   * @return Box
   */
  public function getBox(): Box {
    return $this->box;
  }

  /**
   * Get pipeline variables from exercise configuration.
   * @note Needs to be first set before usage.
   * @return VariablesTable|null
   */
  public function getExerciseConfigVariables(): ?VariablesTable {
    return $this->exerciseConfigVariables;
  }

  /**
   * Set pipeline variables from exercise configuration.
   * @param VariablesTable $variablesTable
   * @return Node
   */
  public function setExerciseConfigVariables(VariablesTable $variablesTable): Node {
    $this->exerciseConfigVariables = $variablesTable;
    return $this;
  }

  /**
   * Get variables from environment configuration.
   * @note Needs to be first set before usage.
   * @return VariablesTable|null
   */
  public function getEnvironmentConfigVariables(): ?VariablesTable {
    return $this->environmentConfigVariables;
  }

  /**
   * Set variables from environment configuration.
   * @param VariablesTable $variablesTable
   * @return Node
   */
  public function setEnvironmentConfigVariables(VariablesTable $variablesTable): Node {
    $this->environmentConfigVariables = $variablesTable;
    return $this;
  }

  /**
   * Get variables from pipeline.
   * @note Needs to be first set before usage.
   * @return VariablesTable|null
   */
  public function getPipelineVariables(): ?VariablesTable {
    return $this->pipelineVariables;
  }

  /**
   * Set variables from pipeline.
   * @param VariablesTable $variablesTable
   * @return Node
   */
  public function setPipelineVariables(VariablesTable $variablesTable): Node {
    $this->pipelineVariables = $variablesTable;
    return $this;
  }


  /**
   * Get parents of this node.
   * @return Node[]
   */
  public function getParents(): array {
    return $this->parents;
  }

  /**
   * Get parent of this node which resides on given port.
   * @param string $port
   * @return Node|null
   */
  public function getParent(string $port): ?Node {
    if (!array_key_exists($port, $this->parents)) {
      return null;
    }
    return $this->parents[$port];
  }

  /**
   * Clear parents of this node.
   */
  public function clearParents() {
    $this->parents = array();
  }

  /**
   * Add parent of this node.
   * @param string $port
   * @param Node $parent
   */
  public function addParent(string $port, Node $parent) {
    $this->parents[$port] = $parent;
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
   * Get child of this node with given associated port.
   * @param string $port
   * @return Node|null
   */
  public function getChild(string $port): ?Node {
    if (!array_key_exists($port, $this->children)) {
      return null;
    }
    return $this->children[$port];
  }

  /**
   * Clear children array.
   */
  public function clearChildren() {
    $this->children = array();
  }

  /**
   * Add child to this node with specified node.
   * @param string $port
   * @param Node $child
   */
  public function addChild(string $port, Node $child) {
    $this->children[$port] = $child;
  }

  /**
   * Remove given child from children array.
   * @param Node $child
   */
  public function removeChild(Node $child) {
    $this->children = array_diff($this->children, array($child));
  }

  /**
   * Is this box in tree.
   * @return bool
   */
  public function isInTree(): bool {
    return $this->isInTree;
  }

  /**
   * Set is in tree flag.
   * @param bool $flag
   */
  public function setInTree(bool $flag) {
    $this->isInTree = $flag;
  }

  /**
   * Was this box visited in topological sort.
   * @return bool
   */
  public function isVisited(): bool {
    return $this->visited;
  }

  /**
   * Set visited flag.
   * @param bool $flag
   */
  public function setVisited(bool $flag) {
    $this->visited = $flag;
  }

  /**
   * Was this box finished in topological sort.
   * @return bool
   */
  public function isFinished(): bool {
    return $this->finished;
  }

  /**
   * Set finished flag.
   * @param bool $flag
   */
  public function setFinished(bool $flag) {
    $this->finished = $flag;
  }

}
