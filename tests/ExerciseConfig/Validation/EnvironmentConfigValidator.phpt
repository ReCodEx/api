<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\Validation\EnvironmentConfigValidator;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Model\Entity\Exercise;
use App\Model\Entity\Group;
use App\Model\Entity\Instance;
use App\Model\Entity\SupplementaryExerciseFile;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\User;
use Tester\Assert;


/**
 * @testCase
 */
class TestEnvironmentConfigValidator extends Tester\TestCase
{

    private static $table = [
        [
            "name" => "input",
            "type" => "remote-file",
            "value" => "input.name"
        ],
        [
            "name" => "output",
            "type" => "file",
            "value" => "output.name"
        ]
    ];

    /** @var Loader */
    private $loader;
    /** @var EnvironmentConfigValidator */
    private $validator;

    protected function setUp()
    {
        $this->loader = new Loader(new BoxService());
        $this->validator = new EnvironmentConfigValidator();
    }


    public function testEmpty()
    {
        $exercise = $this->createExercise();
        $table = new VariablesTable();

        Assert::noError(
            function () use ($exercise, $table) {
                $this->validator->validate($exercise, $table);
            }
        );
    }

    public function testCannotFindExerciseFile()
    {
        $exercise = $this->createExercise();
        $table = $this->loader->loadVariablesTable(
            [
                [
                    "name" => "input",
                    "type" => "remote-file",
                    "value" => "input.wrong.name"
                ]
            ]
        );

        Assert::exception(
            function () use ($exercise, $table) {
                $this->validator->validate($exercise, $table);
            },
            ExerciseConfigException::class,
            "Exercise configuration error - Remote file 'input.wrong.name' not found in exercise files."
        );
    }

    public function testCorrect()
    {
        $exercise = $this->createExercise();
        $table = $this->loader->loadVariablesTable(self::$table);

        Assert::noError(
            function () use ($exercise, $table) {
                $this->validator->validate($exercise, $table);
            }
        );
    }

    /**
     * @return Exercise
     */
    private function createExercise(): Exercise
    {
        $user = new User("", "", "", "", "", "", new Instance());
        $exercise = Exercise::create($user, new Group("ext", new Instance()));

        $uploadedFile = new UploadedFile("input.name", new DateTime(), 234, $user);
        SupplementaryExerciseFile::fromUploadedFileAndExercise(
            $uploadedFile,
            $exercise,
            "input.hash",
            "fileserver.path"
        );

        return $exercise;
    }

}

$testCase = new TestEnvironmentConfigValidator();
$testCase->run();
