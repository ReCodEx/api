<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Helpers\ExerciseConfig\Compilation\Tree\RootedTree;


/**
 * Internal exercise configuration compilation service. Handles tests in
 * execution and their separation into appropriate sub-directories. This mainly
 * means modification of file variables and prefixing them with proper
 * directory. Mkdir tasks will be also constructed and added to resulting tree.
 * @note Should be called after optimisation.
 */
class TestDirectoriesResolver {

  /**
   * Resolve and assign proper directories to particular tests.
   * @param RootedTree $tree
   * @return RootedTree
   */
  public function resolve(RootedTree $tree): RootedTree {
    return $tree; // @todo
  }

}
