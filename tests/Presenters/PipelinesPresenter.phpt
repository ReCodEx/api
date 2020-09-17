<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Helpers\FileStorageManager;
use App\Model\Entity\Pipeline;
use App\Model\Entity\SupplementaryExerciseFile;
use App\Model\Entity\UploadedFile;
use App\V1Module\Presenters\PipelinesPresenter;
use Doctrine\ORM\Id\UuidGenerator;
use Tester\Assert;


/**
 * @testCase
 */
class TestPipelinesPresenter extends Tester\TestCase
{
    /** @var PipelinesPresenter */
    protected $presenter;

    /** @var Kdyby\Doctrine\EntityManager */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Nette\Security\User */
    private $user;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, PipelinesPresenter::class);

        // set autogenerated IDs in pipelines
        $metadata = $this->em->getClassMetadata(Pipeline::class);
        $metadata->setIdGenerator(new UuidGenerator());
    }

    protected function tearDown()
    {
        Mockery::close();

        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }


    public function testGetDefaultBoxes()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);
        $allBoxes = $this->presenter->boxService->getAllBoxes();

        $request = new Nette\Application\Request(
            'V1:Pipelines',
            'GET',
            ['action' => 'getDefaultBoxes']
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal($allBoxes, $result['payload']);
        Assert::count(count($allBoxes), $result['payload']);
    }

    public function testGetAllPipelines()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $request = new Nette\Application\Request(
            'V1:Pipelines',
            'GET',
            ['action' => 'default']
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::same(
            array_map(
                function (Pipeline $pipeline) {
                    return $pipeline->getId();
                },
                $this->presenter->pipelines->findAll()
            ),
            array_map(
                function ($item) {
                    return $item["id"];
                },
                $result['payload']['items']
            )
        );
        Assert::count(count($this->presenter->pipelines->findAll()), $result['payload']['items']);
        Assert::count($result['payload']['totalCount'], $this->presenter->pipelines->findAll());
    }

    public function testGetPipeline()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $pipeline = current($this->presenter->pipelines->findAll());

        $request = new Nette\Application\Request(
            'V1:Pipelines',
            'GET',
            ['action' => 'getPipeline', 'id' => $pipeline->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $payload = $result['payload'];
        Assert::equal($pipeline->getId(), $payload["id"]);
        Assert::equal($pipeline->getName(), $payload["name"]);
    }

    public function testCreatePipeline()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $author = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $pipelines = $this->presenter->pipelines->findAll();
        $pipelinesCount = count($pipelines);

        $request = new Nette\Application\Request('V1:Pipelines', 'POST', ['action' => 'createPipeline']);
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);
        Assert::count($pipelinesCount + 1, $this->presenter->pipelines->findAll());

        Assert::equal($author->getId(), $payload["author"]);
        Assert::equal("Pipeline by " . $author->getName(), $payload["name"]);
        Assert::count(0, $payload["exercisesIds"]);
    }

    public function testForkPipeline()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $author = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $exercise = current($this->presenter->exercises->findAll());
        $pipelines = $this->presenter->pipelines->findAll();
        $pipeline = current($pipelines);
        $pipelinesCount = count($pipelines);

        $request = new Nette\Application\Request(
            'V1:Pipelines', 'POST',
            ['action' => 'forkPipeline', 'id' => $pipeline->getId()],
            ['exerciseId' => $exercise->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);
        Assert::count($pipelinesCount + 1, $this->presenter->pipelines->findAll());

        Assert::equal($author->getId(), $payload["author"]);
        Assert::equal($pipeline->getName(), $payload["name"]);
        Assert::contains($exercise->getId(), $payload["exercisesIds"]);
    }

    public function testRemovePipeline()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $pipeline = current($this->presenter->pipelines->findAll());

        $request = new Nette\Application\Request(
            'V1:Pipelines', 'DELETE',
            ['action' => 'removePipeline', 'id' => $pipeline->id]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);

        Assert::exception(
            function () use ($pipeline) {
                $this->presenter->pipelines->findOrThrow($pipeline->getId());
            },
            NotFoundException::class
        );
    }

    private const PIPELINE_CONFIG = [
        "variables" => [
            [
                "name" => "in_data_file",
                "type" => "file",
                "value" => "/etc/passwd"
            ]
        ],
        "boxes" => [
            [
                'name' => 'infile',
                'type' => 'file-in',
                'portsIn' => [],
                'portsOut' => ['input' => ['type' => 'file', 'value' => 'in_data_file']]
            ],
            [
                'name' => 'judgement',
                'type' => 'judge',
                'portsIn' => [
                    'judge-type' => ['type' => 'string', 'value' => ''],
                    "args" => ['type' => 'string[]', 'value' => ''],
                    "custom-judge" => ['type' => 'file', 'value' => ""],
                    'expected-output' => ['type' => 'file', 'value' => 'in_data_file'],
                    'actual-output' => ['type' => 'file', 'value' => 'in_data_file']
                ],
                'portsOut' => []
            ]
        ]
    ];

    public function testUpdatePipeline()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $pipeline = current($this->presenter->pipelines->findAll());

        $request = new Nette\Application\Request(
            'V1:Pipelines',
            'POST',
            ['action' => 'updatePipeline', 'id' => $pipeline->getId()],
            [
                'name' => 'new pipeline name',
                'version' => 1,
                'description' => 'description of pipeline',
                'pipeline' => static::PIPELINE_CONFIG
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);

        Assert::equal('new pipeline name', $payload["name"]);
        Assert::equal(2, $payload["version"]);
        Assert::equal('description of pipeline', $payload["description"]);
        Assert::equal(static::PIPELINE_CONFIG, $payload["pipeline"]);
    }

    public function testUpdatePipelineWithParameters()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $pipeline = current($this->presenter->pipelines->findAll());

        $request = new Nette\Application\Request(
            'V1:Pipelines',
            'POST',
            ['action' => 'updatePipeline', 'id' => $pipeline->getId()],
            [
                'name' => 'new pipeline name',
                'version' => 1,
                'description' => 'description of pipeline',
                'pipeline' => static::PIPELINE_CONFIG,
                'parameters' => [
                    "isCompilationPipeline" => "true"
                ]
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        $serializedResult = PresenterTestHelper::jsonResponse($payload);

        Assert::true(array_key_exists("isCompilationPipeline", $serializedResult["parameters"]));
        Assert::true($serializedResult["parameters"]["isCompilationPipeline"]);
    }

    public function testUpdatePipelineUnknownParameter()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $pipeline = current($this->presenter->pipelines->findAll());

        $request = new Nette\Application\Request(
            'V1:Pipelines',
            'POST',
            ['action' => 'updatePipeline', 'id' => $pipeline->getId()],
            [
                'name' => 'new pipeline name',
                'version' => 1,
                'description' => 'description of pipeline',
                'pipeline' => static::PIPELINE_CONFIG,
                'parameters' => [
                    "whateverIDontKnow" => "true"
                ]
            ]
        );
        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            InvalidArgumentException::class
        );
    }

    public function testValidatePipeline()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $pipeline = current($this->presenter->pipelines->findAll());

        $request = new Nette\Application\Request(
            'V1:Pipelines', 'POST',
            ['action' => 'validatePipeline', 'id' => $pipeline->getId()],
            ['version' => 2]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        $payload = $result['payload'];
        Assert::equal(200, $result['code']);

        Assert::true(is_array($payload));
        Assert::true(array_key_exists("versionIsUpToDate", $payload));
        Assert::false($payload["versionIsUpToDate"]);
    }

    public function testSupplementaryFilesUpload()
    {
        // Mock file server setup
        $filename1 = "task1.txt";
        $filename2 = "task2.txt";
        $fileServerResponse1 = [
            $filename1 => "https://fs/tasks/hash1",
        ];
        $fileServerResponse2 = [
            $filename2 => "https://fs/tasks/hash2"
        ];
        $fileServerResponseMerged = array_merge($fileServerResponse1, $fileServerResponse2);

        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        $file1 = new UploadedFile($filename1, new \DateTime(), 0, $user, $filename1);
        $file2 = new UploadedFile($filename2, new \DateTime(), 0, $user, $filename2);
        $this->presenter->uploadedFiles->persist($file1);
        $this->presenter->uploadedFiles->persist($file2);
        $this->presenter->uploadedFiles->flush();

        $fileStorage = Mockery::mock(FileStorageManager::class);
        $fileStorage->shouldDeferMissing();
        $fileStorage->shouldReceive("storeUploadedSupplementaryFile")->with($file1)->once();
        $fileStorage->shouldReceive("storeUploadedSupplementaryFile")->with($file2)->once();
        $this->presenter->fileStorage = $fileStorage;

        // Finally, the test itself
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $pipeline = current($this->presenter->pipelines->findAll());
        $files = [$file1->getId(), $file2->getId()];

        /** @var Nette\Application\Responses\JsonResponse $response */
        $response = $this->presenter->run(
            new Nette\Application\Request(
                "V1:Pipelines", "POST", [
                "action" => 'uploadSupplementaryFiles',
                'id' => $pipeline->id
            ], [
                    'files' => $files
                ]
            )
        );

        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $payload = $response->getPayload()['payload'];
        Assert::count(2, $payload);

        foreach ($payload as $item) {
            Assert::type(App\Model\Entity\SupplementaryExerciseFile::class, $item);
        }
    }

    public function testGetSupplementaryFiles()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        // prepare files into exercise
        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $pipeline = current($this->presenter->pipelines->findAll());
        $expectedFile1 = new SupplementaryExerciseFile(
            "name1",
            new DateTime(),
            1,
            "hashName1",
            $user,
            null,
            $pipeline
        );
        $expectedFile2 = new SupplementaryExerciseFile(
            "name2",
            new DateTime(),
            2,
            "hashName2",
            $user,
            null,
            $pipeline
        );
        $this->presenter->uploadedFiles->persist($expectedFile1, false);
        $this->presenter->uploadedFiles->persist($expectedFile2, false);
        $this->presenter->uploadedFiles->flush();

        $request = new Nette\Application\Request(
            "V1:Pipelines", 'GET',
            ['action' => 'getSupplementaryFiles', 'id' => $pipeline->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(2, $result['payload']);
        $expectedFiles = [$expectedFile1, $expectedFile2];

        sort($expectedFiles);
        sort($result['payload']);
        Assert::equal($expectedFiles, $result['payload']);
    }

}

$testCase = new TestPipelinesPresenter();
$testCase->run();
