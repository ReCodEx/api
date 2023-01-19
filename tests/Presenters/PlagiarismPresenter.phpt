<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\Notifications\PointsChangedEmailsSender;
use App\Model\Entity\Assignment;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\User;
use App\V1Module\Presenters\PlagiarismPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;


/**
 * @testCase
 */
class TestPlagiarismPresenter extends Tester\TestCase
{
    /** @var PlagiarismPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
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
        $this->presenter = PresenterTestHelper::createPresenter($this->container, PlagiarismPresenter::class);
    }

    protected function tearDown()
    {
        Mockery::close();

        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    public function testListBatches()
    {
        $admin = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:PlagiarismPresenter',
            'GET',
            ['action' => 'listBatches']
        );
        Assert::count(2, $payload);
        list($batch1, $batch2) = $payload;
        Assert::equal('demoTool', $batch1->getDetectionTool());
        Assert::equal($admin->getId(), $batch1->getSupervisor()->getId());
        Assert::equal('demoTool', $batch2->getDetectionTool());
        Assert::equal($admin->getId(), $batch2->getSupervisor()->getId());
        Assert::true($batch1->getUploadCompletedAt() === null || $batch2->getUploadCompletedAt() === null);
        Assert::true($batch1->getUploadCompletedAt() !== null || $batch2->getUploadCompletedAt() !== null);
    }

    public function testGetBatch()
    {
        $admin = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $batch = current(array_filter($this->presenter->detectionBatches->findAll(), function ($b) {
            return $b->getUploadCompletedAt() !== null;
        }));
        Assert::notNull($batch);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:PlagiarismPresenter',
            'GET',
            ['action' => 'batchDetail', 'id' => $batch->getId()]
        );
        Assert::equal($batch->getId(), $payload->getId());
        Assert::equal($admin->getId(), $payload->getSupervisor()->getId());
        Assert::notNull($payload->getUploadCompletedAt());
    }

    public function testCreateBatch()
    {
        $admin = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:PlagiarismPresenter',
            'POST',
            ['action' => 'createBatch'],
            ['detectionTool' => 'theTool', 'detectionToolParams' => '--args']
        );
        Assert::equal($admin->getId(), $payload->getSupervisor()->getId());
        Assert::equal('theTool', $payload->getDetectionTool());
        Assert::equal('--args', $payload->getDetectionToolParameters());
        Assert::true($payload->getUploadCompletedAt() === null);
        Assert::count(3, $this->presenter->detectionBatches->findAll());
    }

    public function testBatchSetCompleted()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $batch = current(array_filter($this->presenter->detectionBatches->findAll(), function ($b) {
            return $b->getUploadCompletedAt() === null;
        }));
        Assert::notNull($batch);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:PlagiarismPresenter',
            'POST',
            ['action' => 'updateBatch', 'id' => $batch->getId()],
            ['uploadCompleted' => true]
        );
        Assert::equal($batch->getId(), $payload->getId());
        Assert::true($payload->getUploadCompletedAt() !== null);
        $this->presenter->detectionBatches->refresh($batch);
        Assert::true($batch->getUploadCompletedAt() !== null);
    }

    public function testBatchUnsetCompleted()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $batch = current(array_filter($this->presenter->detectionBatches->findAll(), function ($b) {
            return $b->getUploadCompletedAt() !== null;
        }));
        Assert::notNull($batch);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:PlagiarismPresenter',
            'POST',
            ['action' => 'updateBatch', 'id' => $batch->getId()],
            ['uploadCompleted' => false]
        );
        Assert::equal($batch->getId(), $payload->getId());
        Assert::true($payload->getUploadCompletedAt() === null);
        $this->presenter->detectionBatches->refresh($batch);
        Assert::true($batch->getUploadCompletedAt() === null);
    }

    public function testGetSimilarities()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $batch = current(array_filter($this->presenter->detectionBatches->findAll(), function ($b) {
            return $b->getUploadCompletedAt() !== null;
        }));
        Assert::notNull($batch);

        $similarity = current($this->presenter->detectedSimilarities->findAll());
        Assert::notNull($similarity);

        $similarFile = current($this->presenter->detectedSimilarFiles->findAll());
        Assert::notNull($similarFile);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:PlagiarismPresenter',
            'GET',
            [
                'action' => 'getSimilarities',
                'id' => $batch->getId(),
                'solutionId' => $similarity->getTestedSolution()->getId()
            ]
        );

        $payload = json_decode(json_encode($payload), true); // let the serializer do its tricks
        Assert::count(1, $payload);
        $sim = $payload[0];
        Assert::equal($similarity->getId(), $sim['id']);
        Assert::equal($similarity->getTestedSolution()->getId(), $sim['testedSolutionId']);
        Assert::equal($similarity->getSolutionFile()->getId(), $sim['solutionFileId']);
        Assert::equal('', $sim['fileEntry']);
        Assert::count(1, $sim['files']);

        $file = $sim['files'][0];
        Assert::equal($similarFile->getSolution()->getId(), $file['solutionId']);
        Assert::equal($similarFile->getSolutionFile()->getId(), $file['solutionFileId']);
        Assert::equal('', $file['fileEntry']);
        Assert::count(2, $file['fragments']);
    }

    public function testAddSimilarity()
    {
        // we use existing similarity relation (from fixtures) and try to reverse it
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $batch = current(array_filter($this->presenter->detectionBatches->findAll(), function ($b) {
            return $b->getUploadCompletedAt() === null;
        }));
        Assert::notNull($batch);

        $similarity = current($this->presenter->detectedSimilarities->findAll());
        Assert::notNull($similarity);
        $similarSolution = $similarity->getTestedSolution();

        $similarFile = current($this->presenter->detectedSimilarFiles->findAll());
        Assert::notNull($similarFile);
        $testedSolution = $similarFile->getSolution();
        Assert::equal(null, $testedSolution->getPlagiarismBatch());

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:PlagiarismPresenter',
            'POST',
            ['action' => 'addSimilarities', 'id' => $batch->getId(), 'solutionId' => $testedSolution->getId()],
            [
                'solutionFileId' => $similarFile->getSolutionFile()->getId(),
                'fileEntry' => $similarFile->getFileEntry(),
                'authorId' => $similarSolution->getSolution()->getAuthor()->getId(),
                'similarity' => 0.42,
                'files' => [[
                    'solutionId' => $similarSolution->getId(),
                    'solutionFileId' => $similarity->getSolutionFile()->getId(),
                    'fileEntry' => $similarity->getFileEntry(),
                    'fragments' => [
                        [
                            [ 'offset' => 42, 'length' => 54 ],
                            [ 'offset' => 42, 'length' => 54 ],
                        ],
                        [
                            [ 'offset' => 420, 'length' => 540 ],
                            [ 'offset' => 420, 'length' => 540 ],
                        ],
                        [
                            [ 'offset' => 4200, 'length' => 1024 ],
                            [ 'offset' => 4200, 'length' => 1024 ],
                        ],
                    ]
                ]],
            ]
        );

        $payload = json_decode(json_encode($payload), true); // let the serializer do its tricks
        Assert::equal($batch->getId(), $payload['batchId']);
        Assert::equal($testedSolution->getId(), $payload['testedSolutionId']);
        Assert::equal($similarFile->getSolutionFile()->getId(), $payload['solutionFileId']);
        Assert::equal('', $payload['fileEntry']);
        Assert::equal($similarSolution->getSolution()->getAuthor()->getId(), $payload['authorId']);
        Assert::equal(0.42, $payload['similarity']);
        Assert::count(1, $payload['files']);

        $file = $payload['files'][0];
        Assert::equal($similarSolution->getId(), $file['solutionId']);
        Assert::equal($similarity->getSolutionFile()->getId(), $file['solutionFileId']);
        Assert::equal('', $file['fileEntry']);
        Assert::count(3, $file['fragments']);

        $this->presenter->assignmentSolutions->refresh($testedSolution);
        Assert::equal($batch->getId(), $testedSolution->getPlagiarismBatch()->getId());
    }

    public function testAddSimilarityNoFile()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $batch = current(array_filter($this->presenter->detectionBatches->findAll(), function ($b) {
            return $b->getUploadCompletedAt() === null;
        }));
        Assert::notNull($batch);

        $similarity = current($this->presenter->detectedSimilarities->findAll());
        Assert::notNull($similarity);
        $similarSolution = $similarity->getTestedSolution();

        $similarFile = current($this->presenter->detectedSimilarFiles->findAll());
        Assert::notNull($similarFile);
        $testedSolution = $similarFile->getSolution();
        Assert::equal(null, $testedSolution->getPlagiarismBatch());

        Assert::exception(
            function () use ($batch, $testedSolution, $similarSolution, $similarity, $similarFile) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:PlagiarismPresenter',
                    'POST',
                    ['action' => 'addSimilarities', 'id' => $batch->getId(), 'solutionId' => $testedSolution->getId()],
                    [
                        'authorId' => $similarSolution->getSolution()->getAuthor()->getId(),
                        'similarity' => 0.42,
                        'files' => [[
                            'solutionId' => $similarSolution->getId(),
                            'solutionFileId' => $similarity->getSolutionFile()->getId(),
                            'fileEntry' => $similarity->getFileEntry(),
                            'fragments' => []
                        ]],
                    ]
                );
            },
            BadRequestException::class
        );
    }

    public function testAddSimilarityNoAuthor()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $batch = current(array_filter($this->presenter->detectionBatches->findAll(), function ($b) {
            return $b->getUploadCompletedAt() === null;
        }));
        Assert::notNull($batch);

        $similarity = current($this->presenter->detectedSimilarities->findAll());
        Assert::notNull($similarity);
        $similarSolution = $similarity->getTestedSolution();

        $similarFile = current($this->presenter->detectedSimilarFiles->findAll());
        Assert::notNull($similarFile);
        $testedSolution = $similarFile->getSolution();
        Assert::equal(null, $testedSolution->getPlagiarismBatch());

        Assert::exception(
            function () use ($batch, $testedSolution, $similarSolution, $similarity, $similarFile) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:PlagiarismPresenter',
                    'POST',
                    ['action' => 'addSimilarities', 'id' => $batch->getId(), 'solutionId' => $testedSolution->getId()],
                    [
                        'solutionFileId' => $similarFile->getSolutionFile()->getId(),
                        'fileEntry' => $similarFile->getFileEntry(),
                        'similarity' => 0.42,
                        'files' => [[
                            'solutionId' => $similarSolution->getId(),
                            'solutionFileId' => $similarity->getSolutionFile()->getId(),
                            'fileEntry' => $similarity->getFileEntry(),
                            'fragments' => []
                        ]],
                    ]
                );
            },
            BadRequestException::class
        );
    }

    public function testAddSimilarityNoSimilarity()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $batch = current(array_filter($this->presenter->detectionBatches->findAll(), function ($b) {
            return $b->getUploadCompletedAt() === null;
        }));
        Assert::notNull($batch);

        $similarity = current($this->presenter->detectedSimilarities->findAll());
        Assert::notNull($similarity);
        $similarSolution = $similarity->getTestedSolution();

        $similarFile = current($this->presenter->detectedSimilarFiles->findAll());
        Assert::notNull($similarFile);
        $testedSolution = $similarFile->getSolution();
        Assert::equal(null, $testedSolution->getPlagiarismBatch());

        Assert::exception(
            function () use ($batch, $testedSolution, $similarSolution, $similarity, $similarFile) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:PlagiarismPresenter',
                    'POST',
                    ['action' => 'addSimilarities', 'id' => $batch->getId(), 'solutionId' => $testedSolution->getId()],
                    [
                        'solutionFileId' => $similarFile->getSolutionFile()->getId(),
                        'fileEntry' => $similarFile->getFileEntry(),
                        'authorId' => $similarSolution->getSolution()->getAuthor()->getId(),
                        'files' => [[
                            'solutionId' => $similarSolution->getId(),
                            'solutionFileId' => $similarity->getSolutionFile()->getId(),
                            'fileEntry' => $similarity->getFileEntry(),
                            'fragments' => []
                        ]],
                    ]
                );
            },
            BadRequestException::class
        );
    }

    public function testAddSimilarityBadFiles()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $batch = current(array_filter($this->presenter->detectionBatches->findAll(), function ($b) {
            return $b->getUploadCompletedAt() === null;
        }));
        Assert::notNull($batch);

        $similarity = current($this->presenter->detectedSimilarities->findAll());
        Assert::notNull($similarity);
        $similarSolution = $similarity->getTestedSolution();

        $similarFile = current($this->presenter->detectedSimilarFiles->findAll());
        Assert::notNull($similarFile);
        $testedSolution = $similarFile->getSolution();
        Assert::equal(null, $testedSolution->getPlagiarismBatch());

        Assert::exception(
            function () use ($batch, $testedSolution, $similarSolution, $similarity, $similarFile) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:PlagiarismPresenter',
                    'POST',
                    ['action' => 'addSimilarities', 'id' => $batch->getId(), 'solutionId' => $testedSolution->getId()],
                    [
                        'solutionFileId' => $similarFile->getSolutionFile()->getId(),
                        'fileEntry' => $similarFile->getFileEntry(),
                        'authorId' => $similarSolution->getSolution()->getAuthor()->getId(),
                        'similarity' => 0.42,
                        'files' => [[
                            'solutionId' => 'aad56233-8b78-473b-9008-f85d16354336',
                            'solutionFileId' => $similarity->getSolutionFile()->getId(),
                            'fileEntry' => $similarity->getFileEntry(),
                            'fragments' => []
                        ]],
                    ]
                );
            },
            NotFoundException::class
        );
    }

    public function testAddSimilarityBadFragments()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $batch = current(array_filter($this->presenter->detectionBatches->findAll(), function ($b) {
            return $b->getUploadCompletedAt() === null;
        }));
        Assert::notNull($batch);

        $similarity = current($this->presenter->detectedSimilarities->findAll());
        Assert::notNull($similarity);
        $similarSolution = $similarity->getTestedSolution();

        $similarFile = current($this->presenter->detectedSimilarFiles->findAll());
        Assert::notNull($similarFile);
        $testedSolution = $similarFile->getSolution();
        Assert::equal(null, $testedSolution->getPlagiarismBatch());

        Assert::exception(
            function () use ($batch, $testedSolution, $similarSolution, $similarity, $similarFile) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:PlagiarismPresenter',
                    'POST',
                    ['action' => 'addSimilarities', 'id' => $batch->getId(), 'solutionId' => $testedSolution->getId()],
                    [
                        'solutionFileId' => $similarFile->getSolutionFile()->getId(),
                        'fileEntry' => $similarFile->getFileEntry(),
                        'authorId' => $similarSolution->getSolution()->getAuthor()->getId(),
                        'similarity' => 0.42,
                        'files' => [[
                            'solution' => $similarSolution->getId(),
                            'solutionFileId' => $similarity->getSolutionFile()->getId(),
                            'fileEntry' => $similarity->getFileEntry(),
                            'fragments' => [
                                [
                                    [ 'off' => 42, 'length' => 54 ],
                                    [ 'offset' => 42, 'len' => 54 ],
                                ],
                            ]
                        ]],
                    ]
                );
            },
            BadRequestException::class
        );
    }

    public function testAddSimilarityFileOfDifferentSolution()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $batch = current(array_filter($this->presenter->detectionBatches->findAll(), function ($b) {
            return $b->getUploadCompletedAt() === null;
        }));
        Assert::notNull($batch);

        $similarity = current($this->presenter->detectedSimilarities->findAll());
        Assert::notNull($similarity);
        $similarSolution = $similarity->getTestedSolution();

        $similarFile = current($this->presenter->detectedSimilarFiles->findAll());
        Assert::notNull($similarFile);
        $testedSolution = $similarFile->getSolution();
        Assert::equal(null, $testedSolution->getPlagiarismBatch());

        Assert::exception(
            function () use ($batch, $testedSolution, $similarSolution, $similarity, $similarFile) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:PlagiarismPresenter',
                    'POST',
                    ['action' => 'addSimilarities', 'id' => $batch->getId(), 'solutionId' => $similarSolution->getId()],
                    [
                        'authorId' => $similarSolution->getSolution()->getAuthor()->getId(),
                        'similarity' => 0.42,
                        'files' => [[
                            'solutionId' => $similarSolution->getId(),
                            'solutionFileId' => $similarity->getSolutionFile()->getId(),
                            'fileEntry' => $similarity->getFileEntry(),
                            'fragments' => []
                        ]],
                    ]
                );
            },
            BadRequestException::class
        );
    }

    public function testAddSimilaritySimilarFileOfDifferentSolution()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $batch = current(array_filter($this->presenter->detectionBatches->findAll(), function ($b) {
            return $b->getUploadCompletedAt() === null;
        }));
        Assert::notNull($batch);

        $similarity = current($this->presenter->detectedSimilarities->findAll());
        Assert::notNull($similarity);
        $similarSolution = $similarity->getTestedSolution();

        $similarFile = current($this->presenter->detectedSimilarFiles->findAll());
        Assert::notNull($similarFile);
        $testedSolution = $similarFile->getSolution();
        Assert::equal(null, $testedSolution->getPlagiarismBatch());

        Assert::exception(
            function () use ($batch, $testedSolution, $similarSolution, $similarity, $similarFile) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:PlagiarismPresenter',
                    'POST',
                    ['action' => 'addSimilarities', 'id' => $batch->getId(), 'solutionId' => $testedSolution->getId()],
                    [
                        'authorId' => $similarSolution->getSolution()->getAuthor()->getId(),
                        'similarity' => 0.42,
                        'files' => [[
                            'solutionId' => $testedSolution->getId(),
                            'solutionFileId' => $similarity->getSolutionFile()->getId(),
                            'fileEntry' => $similarity->getFileEntry(),
                            'fragments' => []
                        ]],
                    ]
                );
            },
            BadRequestException::class
        );
    }

    public function testAddSimilaritySolutionOfDifferentAuthor()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $batch = current(array_filter($this->presenter->detectionBatches->findAll(), function ($b) {
            return $b->getUploadCompletedAt() === null;
        }));
        Assert::notNull($batch);

        $similarity = current($this->presenter->detectedSimilarities->findAll());
        Assert::notNull($similarity);
        $similarSolution = $similarity->getTestedSolution();

        $similarFile = current($this->presenter->detectedSimilarFiles->findAll());
        Assert::notNull($similarFile);
        $testedSolution = $similarFile->getSolution();
        Assert::equal(null, $testedSolution->getPlagiarismBatch());

        Assert::exception(
            function () use ($batch, $testedSolution, $similarSolution, $similarity, $similarFile) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:PlagiarismPresenter',
                    'POST',
                    ['action' => 'addSimilarities', 'id' => $batch->getId(), 'solutionId' => $testedSolution->getId()],
                    [
                        'authorId' => $testedSolution->getSolution()->getAuthor()->getId(),
                        'similarity' => 0.42,
                        'files' => [[
                            'solutionId' => $similarSolution->getId(),
                            'solutionFileId' => $similarity->getSolutionFile()->getId(),
                            'fileEntry' => $similarity->getFileEntry(),
                            'fragments' => []
                        ]],
                    ]
                );
            },
            BadRequestException::class
        );
    }
}

(new TestPlagiarismPresenter())->run();
