<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20190114131100 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql(
            'UPDATE pipeline_config SET pipeline_config = :config WHERE id =
          (SELECT p.pipeline_config_id FROM pipeline AS p
          JOIN pipeline_parameter AS pp ON pp.pipeline_id = p.id
          WHERE pp.name = :name AND pp.boolean_value = 1 LIMIT 1)',
            [
                'name' => 'judgeOnlyPipeline',
                'config' => <<<'PIPELINE_LITERAL_ENDS'
---
boxes:
  -
    name: inputs
    portsIn: {}
    portsOut:
      input:
        type: file[]
        value: input-files
    type: files-in
  -
    name: merge
    portsIn:
      in1:
        type: file[]
        value: input-files
      in2:
        type: file[]
        value: source-files
    portsOut:
      out:
        type: file[]
        value: merged-files
    type: merge-files
  -
    name: run
    portsIn:
      runtime-path:
        type: string
        value: runtime-path
      runtime-args:
        type: string[]
        value: runtime-args
      args:
        type: string[]
        value: run-args
      binary-file:
        type: file
        value: custom-judge
      input-files:
        type: file[]
        value: merged-files
      stdin:
        type: file
        value: ""
    portsOut:
      output-file:
        type: file
        value: ""
      stdout:
        type: file
        value: actual-output
    type: wrapped-exec
  -
    name: judge
    portsIn:
      actual-output:
        type: file
        value: actual-output
      args:
        type: string[]
        value: judge-args
      custom-judge:
        type: file
        value: ""
      expected-output:
        type: file
        value: actual-output
      judge-type:
        type: string
        value: judge-type
    portsOut: {}
    type: judge
  -
    name: source-files
    portsIn: {}
    portsOut:
      input:
        type: file[]
        value: source-files
    type: files-in
  -
    name: custom-judge
    portsIn: {}
    portsOut:
      input:
        type: file
        value: custom-judge
    type: file-in
variables:
  -
    name: runtime-path
    type: string
    value: /usr/bin/recodex-data-only-wrapper.sh
  -
    name: runtime-args
    type: string[]
    value: []
  -
    name: run-args
    type: string[]
    value: $run-args
  -
    name: judge-type
    type: string
    value: recodex-judge-passthrough
  -
    name: actual-output
    type: file
    value: ""
  -
    name: input-files
    type: file[]
    value: $actual-inputs
  -
    name: source-files
    type: file[]
    value: []
  -
    name: merged-files
    type: file[]
    value: []
  -
    name: custom-judge
    type: file
    value: ""
  -
    name: judge-args
    type: string[]
    value: []
PIPELINE_LITERAL_ENDS
            ]
        );
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
