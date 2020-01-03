<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxMeta;
use App\Helpers\ExerciseConfig\Pipeline\Box\FetchFileBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\FileInBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\FileOutBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\GccCompilationBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\JudgeBox;
use App\Helpers\ExerciseConfig\Pipeline\Ports\Port;
use App\Helpers\ExerciseConfig\Pipeline\Ports\PortMeta;
use App\Helpers\ExerciseConfig\Validation\PipelineValidator;
use App\Helpers\ExerciseConfig\Variable;
use App\Model\Entity\Instance;
use App\Model\Entity\Pipeline as PipelineEntity;
use App\Model\Entity\SupplementaryExerciseFile;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\User;
use Tester\Assert;


/**
 * @testCase
 */
class TestPipelineValidator extends Tester\TestCase
{
    /** @var PipelineValidator */
    private $validator;

    protected function setUp()
    {
        $this->validator = new PipelineValidator();
    }

    public function testCorrect()
    {
        $pipelineEntity = $this->createPipelineEntity();
        $pipeline = new Pipeline();
        $pipeline->getVariablesTable()->set(new Variable("file", "input", ""));
        $pipeline->getVariablesTable()->set(new Variable("remote-file", "remote-input", "input.name"));
        $pipeline->getVariablesTable()->set(new Variable("file", "output", ""));

        $dataInBoxMeta = new BoxMeta();
        $dataInBoxMeta->setName("input");
        $dataInBoxMeta->addInputPort(
            new Port(PortMeta::create(FetchFileBox::$REMOTE_PORT_KEY, "remote-file", "remote-input"))
        );
        $dataInBoxMeta->addOutputPort(new Port(PortMeta::create(FetchFileBox::$INPUT_PORT_KEY, "file", "input")));
        $pipeline->set(new FetchFileBox($dataInBoxMeta));

        $compileBoxMeta = new BoxMeta();
        $compileBoxMeta->setName("compile");
        $compileBoxMeta->addInputPort(
            new Port(
                PortMeta::create(
                    GccCompilationBox::$SOURCE_FILES_PORT_KEY,
                    "file",
                    "input"
                )
            )
        );
        $compileBoxMeta->addOutputPort(
            new Port(
                PortMeta::create(
                    GccCompilationBox::$BINARY_FILE_PORT_KEY,
                    "file",
                    "output"
                )
            )
        );
        $pipeline->set(new GccCompilationBox($compileBoxMeta));

        $dataOutBoxMeta = new BoxMeta();
        $dataOutBoxMeta->setName("output");
        $dataOutBoxMeta->addInputPort(new Port(PortMeta::create(FileOutBox::$FILE_OUT_PORT_KEY, "file", "output")));
        $pipeline->set(new FileOutBox($dataOutBoxMeta));

        Assert::noError(
            function () use ($pipelineEntity, $pipeline) {
                $this->validator->validate($pipelineEntity, $pipeline);
            }
        );
    }

    public function testEmpty()
    {
        $pipelineEntity = $this->createPipelineEntity();
        $pipeline = new Pipeline();

        Assert::noError(
            function () use ($pipelineEntity, $pipeline) {
                $this->validator->validate($pipelineEntity, $pipeline);
            }
        );
    }

    public function testUnusedVariable()
    {
        $pipelineEntity = $this->createPipelineEntity();
        $pipeline = new Pipeline();
        $pipeline->getVariablesTable()->set(new Variable("file", "varA", "a.txt"));
        $pipeline->getVariablesTable()->set(new Variable("file", "varB", "b.txt"));

        $varAInMeta = new BoxMeta();
        $varAInMeta->setName("varA_in");
        $varAInMeta->addOutputPort(new Port(PortMeta::create("varA_in", "file", "varA")));
        $pipeline->set(new FileInBox($varAInMeta));

        $varBInMeta = new BoxMeta();
        $varBInMeta->setName("varB_in");
        $varBInMeta->addOutputPort(new Port(PortMeta::create("varB_in", "file", "varB")));
        $pipeline->set(new FileInBox($varBInMeta));

        $judgeBoxMeta = new BoxMeta();
        $pipeline->set(new JudgeBox($judgeBoxMeta));
        $judgeBoxMeta->setName("judge");
        $judgeBoxMeta->addInputPort(new Port(PortMeta::create(JudgeBox::$ACTUAL_OUTPUT_PORT_KEY, "file", "varA")));
        $judgeBoxMeta->addInputPort(new Port(PortMeta::create(JudgeBox::$EXPECTED_OUTPUT_PORT_KEY, "file", "varA")));

        Assert::exception(
            function () use ($pipelineEntity, $pipeline) {
                $this->validator->validate($pipelineEntity, $pipeline);
            },
            ExerciseConfigException::class,
            '#(No port uses variable|Unused variable)#i'
        );
    }

    public function testUndefinedVariable()
    {
        $pipelineEntity = $this->createPipelineEntity();
        $pipeline = new Pipeline();
        $pipeline->getVariablesTable()->set(new Variable("file", "varA", "a.txt"));
        $judgeBoxMeta = new BoxMeta();
        $pipeline->set(new JudgeBox($judgeBoxMeta));
        $judgeBoxMeta->setName("judge");
        $judgeBoxMeta->addInputPort(new Port(PortMeta::create(JudgeBox::$ACTUAL_OUTPUT_PORT_KEY, "file", "varA")));
        $judgeBoxMeta->addInputPort(new Port(PortMeta::create(JudgeBox::$EXPECTED_OUTPUT_PORT_KEY, "file", "varB")));

        Assert::exception(
            function () use ($pipelineEntity, $pipeline) {
                $this->validator->validate($pipelineEntity, $pipeline);
            },
            ExerciseConfigException::class,
            '#(undefined|not present)#i'
        );
    }

    public function testMultipleOutputsOfVariable()
    {
        $pipelineEntity = $this->createPipelineEntity();
        $pipeline = new Pipeline();
        $pipeline->getVariablesTable()->set(new Variable("file", "varA", "a.txt"));

        $boxAMeta = new BoxMeta();
        $boxAMeta->setName("gcc_a");
        $boxAMeta->addOutputPort(new Port(PortMeta::create(GccCompilationBox::$BINARY_FILE_PORT_KEY, "file", "varA")));
        $pipeline->set(new GccCompilationBox($boxAMeta));

        $boxBMeta = new BoxMeta();
        $boxBMeta->setName("gcc_b");
        $boxBMeta->addOutputPort(new Port(PortMeta::create(GccCompilationBox::$BINARY_FILE_PORT_KEY, "file", "varA")));
        $pipeline->set(new GccCompilationBox($boxBMeta));

        Assert::exception(
            function () use ($pipelineEntity, $pipeline) {
                $this->validator->validate($pipelineEntity, $pipeline);
            },
            ExerciseConfigException::class,
            '#multiple ports output#i'
        );
    }

    public function testNoOutputOfVariable()
    {
        $pipelineEntity = $this->createPipelineEntity();
        $pipeline = new Pipeline();
        $pipeline->getVariablesTable()->set(new Variable("file", "varA", ""));

        $dataOutBoxMeta = new BoxMeta();
        $dataOutBoxMeta->setName("output");
        $dataOutBoxMeta->addInputPort(new Port(PortMeta::create(FileOutBox::$FILE_OUT_PORT_KEY, "file", "varA")));
        $pipeline->set(new FileOutBox($dataOutBoxMeta));

        Assert::exception(
            function () use ($pipelineEntity, $pipeline) {
                $this->validator->validate($pipelineEntity, $pipeline);
            },
            ExerciseConfigException::class,
            '#no port outputs#i'
        );
    }

    public function testNoOutputOfVariableWithDefault()
    {
        $pipelineEntity = $this->createPipelineEntity();
        $pipeline = new Pipeline();
        $pipeline->getVariablesTable()->set(new Variable("file", "varA", "a.txt"));

        $dataOutBoxMeta = new BoxMeta();
        $dataOutBoxMeta->setName("output");
        $dataOutBoxMeta->addInputPort(new Port(PortMeta::create(FileOutBox::$FILE_OUT_PORT_KEY, "file", "varA")));
        $pipeline->set(new FileOutBox($dataOutBoxMeta));

        Assert::noError(
            function () use ($pipelineEntity, $pipeline) {
                $this->validator->validate($pipelineEntity, $pipeline);
            }
        );
    }

    public function testTypeMismatch()
    {
        $pipelineEntity = $this->createPipelineEntity();
        $pipeline = new Pipeline();
        $pipeline->getVariablesTable()->set(new Variable("string", "input", "a.txt"));

        $compileBoxMeta = new BoxMeta();
        $compileBoxMeta->setName("compile");
        $compileBoxMeta->addInputPort(
            new Port(
                PortMeta::create(
                    GccCompilationBox::$SOURCE_FILES_PORT_KEY,
                    "file",
                    "input"
                )
            )
        );
        $pipeline->set(new GccCompilationBox($compileBoxMeta));

        Assert::exception(
            function () use ($pipelineEntity, $pipeline) {
                $this->validator->validate($pipelineEntity, $pipeline);
            },
            ExerciseConfigException::class,
            '#type#i'
        );
    }

    public function testCannotFindPipelineFile()
    {
        $user = $this->getDummyUser();
        $pipelineEntity = PipelineEntity::create($user);

        $pipeline = new Pipeline();
        $pipeline->getVariablesTable()->set(new Variable("file", "input", ""));
        $pipeline->getVariablesTable()->set(new Variable("remote-file", "remote-input", "input.name"));

        $dataInBoxMeta = new BoxMeta();
        $dataInBoxMeta->setName("input");
        $dataInBoxMeta->addInputPort(
            new Port(PortMeta::create(FetchFileBox::$REMOTE_PORT_KEY, "remote-file", "remote-input"))
        );
        $dataInBoxMeta->addOutputPort(new Port(PortMeta::create(FetchFileBox::$INPUT_PORT_KEY, "file", "input")));
        $pipeline->set(new FetchFileBox($dataInBoxMeta));

        $compileBoxMeta = new BoxMeta();
        $compileBoxMeta->setName("compile");
        $compileBoxMeta->addInputPort(
            new Port(PortMeta::create(GccCompilationBox::$SOURCE_FILES_PORT_KEY, "file", "input"))
        );
        $pipeline->set(new GccCompilationBox($compileBoxMeta));

        Assert::exception(
            function () use ($pipelineEntity, $pipeline) {
                $this->validator->validate($pipelineEntity, $pipeline);
            },
            ExerciseConfigException::class,
            "#not found in pipeline files#i"
        );
    }


    /**
     * @return PipelineEntity
     */
    private function createPipelineEntity(): PipelineEntity
    {
        $user = $this->getDummyUser();
        $pipeline = PipelineEntity::create($user);

        $uploadedFile = new UploadedFile("input.name", new DateTime(), 234, $user);
        SupplementaryExerciseFile::fromUploadedFileAndPipeline(
            $uploadedFile,
            $pipeline,
            "input.hash",
            "fileserver.path"
        );

        return $pipeline;
    }

    /**
     * @return User
     */
    private function getDummyUser(): User
    {
        return new User("", "", "", "", "", "", new Instance());
    }
}

# Testing methods run
$testCase = new TestPipelineValidator();
$testCase->run();
