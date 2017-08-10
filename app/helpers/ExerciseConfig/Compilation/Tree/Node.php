<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;

use App\Helpers\ExerciseConfig\Pipeline\Box\Box;
use App\Helpers\ExerciseConfig\VariablesTable;


/**
 * Class Node
 * @note Box does not have to be assigned. If there is none node is only meant
 * to be some kind of bridge between two pipelines.
 */
class Node {

  /**
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
   * Indexed by port name.
   * @var Node[]
   */
  private $parents = array();

  /**
   * Indexed by port name.
   * @var Node[]
   */
  private $children = array();

  /**
   * Flag regarding tree construction.
   * @var bool
   */
  private $isInTree = false;

  /**
   * Flag regarding topological sorting of tree.
   * @var bool
   */
  private $visited = false;

  /**
   * Flag regarding topological sorting of tree.
   * @var bool
   */
  private $finished = false;


  /**
   * Node constructor.
   * @param Box $box
   */
  public function __construct(Box $box = null) {
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
   * @return Node[]
   */
  public function getParents(): array {
    return $this->parents;
  }

  /**
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
   *
   */
  public function clearParents() {
    $this->parents = array();
  }

  /**
   * @param string $port
   * @param Node $parent
   */
  public function addParent(string $port, Node $parent) {
    $this->parents[$port] = $parent;
  }

  /**
   * @param Node $parent
   */
  public function removeParent(Node $parent) {
    $this->parents = array_diff($this->parents, $parent);
  }

  /**
   * @return Node[]
   */
  public function getChildren(): array {
    return $this->children;
  }

  /**
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
   *
   */
  public function clearChildren() {
    $this->children = array();
  }

  /**
   * @param string $port
   * @param Node $child
   */
  public function addChild(string $port, Node $child) {
    $this->children[$port] = $child;
  }

  /**
   * @param Node $child
   */
  public function removeChild(Node $child) {
    $this->children = array_diff($this->children, $child);
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
