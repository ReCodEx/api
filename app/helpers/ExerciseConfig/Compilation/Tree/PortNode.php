<?php

namespace App\Helpers\ExerciseConfig\Compilation\Tree;

use App\Helpers\ExerciseConfig\Pipeline\Box\Box;
use App\Helpers\ExerciseConfig\VariablesTable;


/**
 * Node representing Box in the compilation of exercise. It can hold additional
 * information regarding box which does not have to be stored there, this can
 * save memory during loading of pipelines and not compiling them.
 * This node contains children and parents indexed by corresponding port.
 * @note Structure used in exercise compilation.
 */
class PortNode {

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
   * @var PortNode[]
   */
  private $parents = array();

  /**
   * Children nodes of this one, indexed by port name.
   * @var PortNode[]
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
   * Identification of test to which this box belongs to.
   * @var string
   */
  private $testId = null;


  /**
   * Node constructor.
   * @param Box $box
   * @param string|null $testId
   */
  public function __construct(Box $box, string $testId = null) {
    $this->box = $box;
    $this->testId = $testId;
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
   * @return PortNode
   */
  public function setExerciseConfigVariables(VariablesTable $variablesTable): PortNode {
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
   * @return PortNode
   */
  public function setEnvironmentConfigVariables(VariablesTable $variablesTable): PortNode {
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
   * @return PortNode
   */
  public function setPipelineVariables(VariablesTable $variablesTable): PortNode {
    $this->pipelineVariables = $variablesTable;
    return $this;
  }


  /**
   * Get parents of this node.
   * @return PortNode[]
   */
  public function getParents(): array {
    return $this->parents;
  }

  /**
   * Get parent of this node which resides on given port.
   * @param string $port
   * @return PortNode|null
   */
  public function getParent(string $port): ?PortNode {
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
   * @param PortNode $parent
   */
  public function addParent(string $port, PortNode $parent) {
    $this->parents[$port] = $parent;
  }

  /**
   * Remove given parent from this node.
   * @param PortNode $parent
   */
  public function removeParent(PortNode $parent) {
    $this->parents = array_diff($this->parents, array($parent));
  }

  /**
   * Get children of this node.
   * @return PortNode[]
   */
  public function getChildren(): array {
    return $this->children;
  }

  /**
   * Get child of this node with given associated port.
   * @param string $port
   * @return PortNode|null
   */
  public function getChild(string $port): ?PortNode {
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
   * @param PortNode $child
   */
  public function addChild(string $port, PortNode $child) {
    $this->children[$port] = $child;
  }

  /**
   * Remove given child from children array.
   * @param PortNode $child
   */
  public function removeChild(PortNode $child) {
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

  /**
   * Test identification for corresponding box.
   * @return string|null
   */
  public function getTestId(): ?string {
    return $this->testId;
  }

  /**
   * Set test identification of box.
   * @param string $testId
   */
  public function setTestId(string $testId) {
    $this->testId = $testId;
  }

}
