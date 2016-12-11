<?php

namespace App\Helpers;

class BlockDiagram {

  // @todo: Unit tests for this helper.

  /**
   * Transform a job configuration into SVG diagram.
   * @param  JobConfig  Job configuration entity
   * @return string  The SVG source code
   */
  public function create(JobConfig $config): string {
      $diagramSrc = $this->createSource($jobConfig);
      return $this->getSvg($diagramSrc);
  }

  /**
   * Transform a job configuration into 'blockdiag' source code.
   * @param  JobConfig  Job configuration entity
   * @return string     The source code
   */
  public function createSource(JobConfig $config): string {
    $diagram = "blockdiag {" .
               "  class init [color = yellow];" .
               "  class eval [color = green];" .
               "  class exec [color = red];" .
               "  class internal [style = dashed];";

    foreach ($config->getTasks() as $task) {
      $type = $task->getType() === NULL ? "internal" : $task->getType();
      $diagram .= "{$task->getId()} [class = \"{$type}\"];";
      foreach ($task->getDependencies() as $dep) {
        $diagram .= "{$dep} -> {$task->getId()};";
      }
    }
    $diagram .= "}";
    return $diagram;
  }

  /**
   * Process 'blockdiag' source code and return SVG source if the diagram description is correct.
   * @param  string  Block diagram source code
   * @return string  The SVG source code
   */
  public function getSvg(string $source): string {
    // @todo: ain't it possible to force blockdiag to output to stdout? shell_exec returns the contents of stdout... - no tmp files would be involved
    $outputFile = tempnam(sys_get_temp_dir(), 'recodex_');
    $cmd = 'echo "' . $source . '" | /usr/bin/blockdiag -T SVG -o "' . $outputFile . '" -';
    // TODO: Ugly undocumented exec call
    shell_exec($cmd);
    $diagram = file_get_contents($outputFile);
    unlink($outputFile);
    return $diagram;
  }

}
