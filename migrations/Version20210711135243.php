<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migrate java related pipelines from using special JavaRunBox and 
 * JavacCompilationBox to more general JvmRunBox and JvmCompilationBox.
 * This requires changes in the Java pipelines.
 * 
 * Note that further action is needed, Java runner has to be compiled to class 
 * file and uploaded to the application manually. Sources for runner resides:
 * https://github.com/ReCodEx/utils/blob/master/runners/java/javarun.java
 */
final class Version20210711135243 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Migrate Java related pipelines to general execution/compilation boxes';
    }

    private function updateCompilationPipeline()
    {
        $pipelines = $this->connection->executeQuery(
            "SELECT DISTINCT p.id, p.pipeline_config_id FROM pipeline p 
             JOIN pipeline_runtime_environment pre ON p.id = pre.pipeline_id
             JOIN pipeline_parameter pp ON p.id = pp.pipeline_id
             WHERE p.author_id IS NULL 
               AND pre.runtime_environment_id = 'java' 
               AND pp.name = 'isCompilationPipeline'"
        )->fetchAllAssociative();
        
        $count = count($pipelines);
        if ($count == 0) {
            return;
        }

        if ($count > 1) {
            throw new Exception("There are multiple system pipelines for Java compilation, cannot continue with migration");
        }

        $this->addSql(
            'UPDATE pipeline SET description = :desc WHERE id = :id',
            [
                'id' => reset($pipelines)['id'],
                'desc' => <<<'PIPELINE_LITERAL_ENDS'
Java compilation which compiles submitted files by user and provided extra files from exercise author. Compiler is `javac` which compiles sources to class files. Pipeline also fetches execution runner and sends it to execution pipeline.

Input variables:

* source-files - files submitted by user which will be compiled
* extra-files - files given by exercise author, will be added to compilation
* extra-file-names - optionally extra files can be renamed during fetching, for which this variable is present
* jar-files - optional jar files which should be used during compilation and execution
* runner-file - mandatory runner class file which will be used as a wrapper executor

Output variables:

* classes-dir - compiled files with bytecode which can be further processed in following pipelines resides in this directory
* jar-files - jar files passed from compilation to execution
* runner-file - runner class file passed from compilation to execution
PIPELINE_LITERAL_ENDS
            ]
        );

        $this->addSql(
            'UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id',
            [
                'id' => reset($pipelines)['pipeline_config_id'],
                'config' => <<<'PIPELINE_LITERAL_ENDS'
---
boxes:
  - name: runner-fetch
    type: fetch-file
    portsIn:
      remote:
        type: remote-file
        value: remote-runner
    portsOut:
      input:
        type: file
        value: runner-file
  - name: sources
    type: files-in
    portsIn: {}
    portsOut:
      input:
        type: 'file[]'
        value: source-files
  - name: compilation
    type: jvm-compilation
    portsIn:
      compiler-exec:
        type: string
        value: compiler-exec
      args:
        type: 'string[]'
        value: ''
      source-files:
        type: 'file[]'
        value: source-files
      extra-files:
        type: 'file[]'
        value: extra-files
      jar-files:
        type: 'file[]'
        value: jar-files
    portsOut:
      class-files-dir:
        type: file
        value: classes-dir
  - name: classes-dir
    type: file-out
    portsIn:
      output:
        type: file
        value: classes-dir
    portsOut: {}
  - name: extras
    type: files-in
    portsIn: {}
    portsOut:
      input:
        type: 'file[]'
        value: extra-files
  - name: jars
    type: files-in
    portsIn: {}
    portsOut:
      input:
        type: 'file[]'
        value: jar-files
  - name: jars-passed
    type: files-out
    portsIn:
      output:
        type: 'file[]'
        value: jar-files
    portsOut: {}
  - name: runner-passed
    type: file-out
    portsIn:
      output:
        type: file
        value: runner-file
    portsOut: {}
variables:
  - name: remote-runner
    type: remote-file
    value: javarun.class
  - name: runner-file
    type: file
    value: javarun.class
  - name: source-files
    type: 'file[]'
    value: {}
  - name: classes-dir
    type: file
    value: ''
  - name: extra-files
    type: 'file[]'
    value: $extra-file-names
  - name: jar-files
    type: 'file[]'
    value: {}
  - name: compiler-exec
    type: string
    value: /usr/bin/javac
PIPELINE_LITERAL_ENDS
            ]
        );
    }

    private function updateExecStdoutPipeline()
    {
        $pipelines = $this->connection->executeQuery(
            "SELECT DISTINCT p.id, p.pipeline_config_id, p.name FROM pipeline p 
             JOIN pipeline_runtime_environment pre ON p.id = pre.pipeline_id
             WHERE p.author_id IS NULL 
               AND pre.runtime_environment_id = 'java' 
               AND EXISTS (SELECT id FROM pipeline_parameter WHERE pipeline_id = p.id AND name = 'isExecutionPipeline')
               AND EXISTS (SELECT id FROM pipeline_parameter WHERE pipeline_id = p.id AND name = 'producesStdout')"
        )->fetchAllAssociative();
        
        $count = count($pipelines);
        if ($count == 0) {
            return;
        }

        if ($count > 1) {
            throw new Exception("There are multiple system pipelines for Java execution (stdout), cannot continue with migration");
        }

        $this->addSql(
            'UPDATE pipeline SET description = :desc WHERE id = :id',
            [
                'id' => reset($pipelines)['id'],
                'desc' => <<<'PIPELINE_LITERAL_ENDS'
Executes Java application and run judge on standard output outputted from execution. Special `javarun.class` files is used as main entry point to the application. This class will try to find main class among submitted ones and execute it.

Input variables:

* runner-file - compiled class file which will serve as wrapper for execution
* classes-dir - directory containing JVM class files which should be executed, main class is automatically found and executed
* run-args - array of string arguments for execution (not for JVM)
* stdin-file - file which will be connected to standard input of execution
* input-files - array of files given by author of exercise, can be used during execution
* actual-inputs - corresponds with `input-files` and can contain array of new names for given input files
* expected-output - file with expected results, will be used during judging
* judge-type - textual representation of any supported judge if author wants to use built-in ones
* judge-args - array of strings which will be used as additional arguments for judge
* custom-judge - author of exercise can provide special file for judging either as binary or executable script
* jar-files - optional jar files which should be used during compilation and execution
PIPELINE_LITERAL_ENDS
            ]
        );

        $this->addSql(
            'UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id',
            [
                'id' => reset($pipelines)['pipeline_config_id'],
                'config' => <<<'PIPELINE_LITERAL_ENDS'
---
boxes:
  - name: input
    type: file-in
    portsIn: {}
    portsOut:
      input:
        type: file
        value: stdin-file
  - name: runner
    type: file-in
    portsIn: {}
    portsOut:
      input:
        type: file
        value: runner-file
  - name: classes-dir
    type: file-in
    portsIn: {}
    portsOut:
      input:
        type: file
        value: classes-dir
  - name: expected
    type: file-in
    portsIn: {}
    portsOut:
      input:
        type: file
        value: expected-output
  - name: judge
    type: judge
    portsIn:
      judge-type:
        type: string
        value: judge-type
      actual-output:
        type: file
        value: actual-output
      expected-output:
        type: file
        value: expected-output
      args:
        type: 'string[]'
        value: judge-args
      custom-judge:
        type: file
        value: custom-judge
    portsOut: {}
  - name: run
    type: jvm-runner
    portsIn:
      runner-exec:
        type: string
        value: runner-exec
      runner:
        type: file
        value: runner-file
      args:
        type: 'string[]'
        value: run-args
      stdin:
        type: file
        value: stdin-file
      input-files:
        type: 'file[]'
        value: input-files
      class-files-dir:
        type: file
        value: classes-dir
      jar-files:
        type: 'file[]'
        value: jar-files
      classpath:
        type: 'string[]'
        value: classpath
    portsOut:
      stdout:
        type: file
        value: actual-output
      output-file:
        type: file
        value: ''
  - name: input-files
    type: files-in
    portsIn: {}
    portsOut:
      input:
        type: 'file[]'
        value: input-files
  - name: custom-judge
    type: file-in
    portsIn: {}
    portsOut:
      input:
        type: file
        value: custom-judge
  - name: jars
    type: files-in
    portsIn: {}
    portsOut:
      input:
        type: 'file[]'
        value: jar-files
variables:
  - name: runner-file
    type: file
    value: javarun.class
  - name: judge-type
    type: string
    value: $judge-type
  - name: actual-output
    type: file
    value: ''
  - name: expected-output
    type: file
    value: expected.out
  - name: run-args
    type: 'string[]'
    value: $run-args
  - name: stdin-file
    type: file
    value: ''
  - name: classes-dir
    type: file
    value: ''
  - name: input-files
    type: 'file[]'
    value: $actual-inputs
  - name: custom-judge
    type: file
    value: ''
  - name: judge-args
    type: 'string[]'
    value: $judge-args
  - name: jar-files
    type: 'file[]'
    value: {}
  - name: runner-exec
    type: string
    value: /usr/bin/java
  - name: classpath
    type: 'string[]'
    value: {}
PIPELINE_LITERAL_ENDS
            ]
        );
    }

    private function updateExecOutfilePipeline()
    {
        $pipelines = $this->connection->executeQuery(
            "SELECT DISTINCT p.id, p.pipeline_config_id, p.name FROM pipeline p 
             JOIN pipeline_runtime_environment pre ON p.id = pre.pipeline_id
             WHERE p.author_id IS NULL 
               AND pre.runtime_environment_id = 'java' 
               AND EXISTS (SELECT id FROM pipeline_parameter WHERE pipeline_id = p.id AND name = 'isExecutionPipeline')
               AND EXISTS (SELECT id FROM pipeline_parameter WHERE pipeline_id = p.id AND name = 'producesFiles')"
        )->fetchAllAssociative();
        
        $count = count($pipelines);
        if ($count == 0) {
            return;
        }

        if ($count > 1) {
            throw new Exception("There are multiple system pipelines for Java execution (outfile), cannot continue with migration");
        }

        $this->addSql(
            'UPDATE pipeline SET description = :desc WHERE id = :id',
            [
                'id' => reset($pipelines)['id'],
                'desc' => <<<'PIPELINE_LITERAL_ENDS'
Executes Java application and run judge on file outputted from execution. Special `javarun.class` files is used as main entry point to the application. This class will try to find main class among submitted ones and execute it.

Input variables:

* runner-file - compiled class file which will serve as wrapper for execution
* classes-dir - directory containing JVM class files which should be executed, main class is automatically found and executed
* run-args - array of string arguments for execution (not for JVM)
* stdin-file - file which will be connected to standard input of execution
* input-files - array of files given by author of exercise, can be used during execution
* actual-inputs - corresponds with `input-files` and can contain array of new names for given input files
* actual-output - file which is outputted by execution has to be somehow identified, this variable should contain its name
* expected-output - file with expected results, will be used during judging
* judge-type - textual representation of any supported judge if author wants to use built-in ones
* judge-args - array of strings which will be used as additional arguments for judge
* custom-judge - author of exercise can provide special file for judging either as binary or executable script
* jar-files - optional jar files which should be used during compilation and execution
PIPELINE_LITERAL_ENDS
            ]
        );

        $this->addSql(
            'UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id',
            [
                'id' => reset($pipelines)['pipeline_config_id'],
                'config' => <<<'PIPELINE_LITERAL_ENDS'
---
boxes:
  - name: input
    type: file-in
    portsIn: {}
    portsOut:
      input:
        type: file
        value: stdin-file
  - name: runner
    type: file-in
    portsIn: {}
    portsOut:
      input:
        type: file
        value: runner-file
  - name: classes-dir
    type: file-in
    portsIn: {}
    portsOut:
      input:
        type: file
        value: classes-dir
  - name: expected
    type: file-in
    portsIn: {}
    portsOut:
      input:
        type: file
        value: expected-output
  - name: judge
    type: judge
    portsIn:
      judge-type:
        type: string
        value: judge-type
      actual-output:
        type: file
        value: actual-output
      expected-output:
        type: file
        value: expected-output
      args:
        type: 'string[]'
        value: judge-args
      custom-judge:
        type: file
        value: custom-judge
    portsOut: {}
  - name: run
    type: jvm-runner
    portsIn:
      runner-exec:
        type: string
        value: runner-exec
      runner:
        type: file
        value: runner-file
      args:
        type: 'string[]'
        value: run-args
      stdin:
        type: file
        value: stdin-file
      input-files:
        type: 'file[]'
        value: input-files
      class-files-dir:
        type: file
        value: classes-dir
      jar-files:
        type: 'file[]'
        value: jar-files
      classpath:
        type: 'string[]'
        value: classpath
    portsOut:
      stdout:
        type: file
        value: ''
      output-file:
        type: file
        value: actual-output
  - name: input-files
    type: files-in
    portsIn: {}
    portsOut:
      input:
        type: 'file[]'
        value: input-files
  - name: custom-judge
    type: file-in
    portsIn: {}
    portsOut:
      input:
        type: file
        value: custom-judge
  - name: jars
    type: files-in
    portsIn: {}
    portsOut:
      input:
        type: 'file[]'
        value: jar-files
variables:
  - name: judge-type
    type: string
    value: $judge-type
  - name: actual-output
    type: file
    value: $actual-output
  - name: expected-output
    type: file
    value: expected.out
  - name: runner-file
    type: file
    value: javarun.class
  - name: run-args
    type: 'string[]'
    value: $run-args
  - name: stdin-file
    type: file
    value: ''
  - name: classes-dir
    type: file
    value: ''
  - name: input-files
    type: 'file[]'
    value: $actual-inputs
  - name: custom-judge
    type: file
    value: ''
  - name: judge-args
    type: 'string[]'
    value: $judge-args
  - name: jar-files
    type: 'file[]'
    value: {}
  - name: runner-exec
    type: string
    value: /usr/bin/java
  - name: classpath
    type: 'string[]'
    value: {}
PIPELINE_LITERAL_ENDS
            ]
        );
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );
        
        $this->connection->beginTransaction();
        $this->updateCompilationPipeline();
        $this->updateExecStdoutPipeline();
        $this->updateExecOutfilePipeline();
        $this->connection->commit();
    }

    public function down(Schema $schema) : void
    {
        $this->throwIrreversibleMigrationException();
    }
}
