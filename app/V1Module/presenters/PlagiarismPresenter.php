<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VDouble;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\ParseException;
use App\Exceptions\FrontendErrorMappings;
use App\Model\Entity\PlagiarismDetectionBatch;
use App\Model\Entity\PlagiarismDetectedSimilarity;
use App\Model\Entity\PlagiarismDetectedSimilarFile;
use App\Model\Entity\SolutionFile;
use App\Model\Repository\Assignments;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\PlagiarismDetectionBatches;
use App\Model\Repository\PlagiarismDetectedSimilarities;
use App\Model\Repository\PlagiarismDetectedSimilarFiles;
use App\Model\Repository\UploadedFiles;
use App\Security\ACL\IPlagiarismPermissions;
use App\Security\ACL\IAssignmentSolutionPermissions;
use App\Model\View\PlagiarismViewFactory;

/**
 * Presenter handling plagiarism-related stuff (similarity records and their presentation)
 */
class PlagiarismPresenter extends BasePresenter
{
    /**
     * @var Assignments
     * @inject
     */
    public $assignments;

    /**
     * @var AssignmentSolutions
     * @inject
     */
    public $assignmentSolutions;

    /**
     * @var PlagiarismDetectionBatches
     * @inject
     */
    public $detectionBatches;

    /**
     * @var PlagiarismDetectedSimilarities
     * @inject
     */
    public $detectedSimilarities;

    /**
     * @var PlagiarismDetectedSimilarFiles
     * @inject
     */
    public $detectedSimilarFiles;

    /**
     * @var UploadedFiles
     * @inject
     */
    public $uploadedFiles;

    /**
     * @var IPlagiarismPermissions
     * @inject
     */
    public $plagiarismAcl;

    /**
     * @var IAssignmentSolutionPermissions
     * @inject
     */
    public $assignmentSolutionAcl;

    /**
     * @var PlagiarismViewFactory
     * @inject
     */
    public $plagiarismViewFactory;

    public function checkListBatches(?string $detectionTool, ?string $solutionId): void
    {
        if (!$this->plagiarismAcl->canViewBatches()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of all batches, optionally filtered by query params.
     * @GET
     */
    #[Query(
        "detectionTool",
        new VString(1, 255),
        "Requests only batches created by a particular detection tool.",
        required: false,
    )]
    #[Query(
        "solutionId",
        new VUuid(),
        "Requests only batches where particular solution has detected similarities.",
        required: false,
    )]
    public function actionListBatches(?string $detectionTool, ?string $solutionId): void
    {
        $solution = $solutionId ? $this->assignmentSolutions->findOrThrow($solutionId) : null;
        $batches = $this->detectionBatches->findByToolAndSolution($detectionTool, $solution);
        $this->sendSuccessResponse($batches);
    }

    public function checkBatchDetail(string $id): void
    {
        if (!$this->plagiarismAcl->canViewBatches()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Fetch a detail of a particular batch record.
     * @GET
     */
    #[Path("id", new VUuid(), "Identification of the detection batch", required: true)]
    public function actionBatchDetail(string $id): void
    {
        $batch = $this->detectionBatches->findOrThrow($id);
        $this->sendSuccessResponse($batch);
    }

    public function checkCreateBatch(): void
    {
        if (!$this->plagiarismAcl->canCreateBatch()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Create new detection batch record
     * @POST
     */
    #[Post("detectionTool", new VString(1, 255), "Identifier of the external tool used to detect similarities.")]
    #[Post(
        "detectionToolParams",
        new VString(0, 255),
        "Tool-specific parameters (e.g., CLI args) used for this particular batch.",
        required: false,
    )]
    public function actionCreateBatch(): void
    {
        $req = $this->getRequest();
        $detectionTool = $req->getPost("detectionTool") ?? '';
        $toolParams = $req->getPost("detectionToolParams") ?? '';
        $batch = new PlagiarismDetectionBatch($detectionTool, $toolParams, $this->getCurrentUser());
        $this->detectionBatches->persist($batch);
        $this->sendSuccessResponse($batch);
    }

    public function checkUpdateBatch(string $id): void
    {
        $batch = $this->detectionBatches->findOrThrow($id);
        if (!$this->plagiarismAcl->canUpdateBatch($batch)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Update detection bath record. At the moment, only the uploadCompletedAt can be changed.
     * @POST
     */
    #[Post(
        "uploadCompleted",
        new VBool(),
        "Whether the upload of the batch data is completed or not.",
        required: false
    )]
    #[Post(
        "assignments",
        new VArray(new VUuid()),
        "List of assignment IDs to be marked as 'checked' by this batch.",
        required: false
    )]
    #[Path("id", new VUuid(), "Identification of the detection batch", required: true)]
    public function actionUpdateBatch(string $id): void
    {
        $batch = $this->detectionBatches->findOrThrow($id);
        $req = $this->getRequest();

        $uploadCompleted = $req->getPost("uploadCompleted");
        if ($uploadCompleted !== null) {
            $uploadCompleted = filter_var($uploadCompleted, FILTER_VALIDATE_BOOLEAN);
            $batch->setUploadCompleted($uploadCompleted);
        }

        $assignments = $req->getPost("assignments") ?? [];
        foreach ($assignments as $assignmentId) {
            $assignment = $this->assignments->findOrThrow($assignmentId);
            $assignment->setPlagiarismBatch($batch);
            $this->assignments->persist($assignment, false); // no flush
        }

        $this->detectionBatches->persist($batch); // and flush
        $this->sendSuccessResponse($batch);
    }

    public function checkGetSimilarities(string $id, string $solutionId): void
    {
        $solution = $this->assignmentSolutions->findOrThrow($solutionId);
        if (!$this->assignmentSolutionAcl->canViewDetectedPlagiarisms($solution)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Retrieve detected plagiarism records from a specific batch related to one solution.
     * Returns a list of detected similarities entities (similar file records are nested within).
     * @GET
     */
    #[Path("id", new VUuid(), "Identification of the detection batch", required: true)]
    #[Path("solutionId", new VUuid(), required: true)]
    public function actionGetSimilarities(string $id, string $solutionId): void
    {
        $batch = $this->detectionBatches->findOrThrow($id);
        $solution = $this->assignmentSolutions->findOrThrow($solutionId);
        $similarities = array_map(
            [$this->plagiarismViewFactory, 'getPlagiarismSimilarityData'],
            $this->detectedSimilarities->getSolutionSimilarities($batch, $solution)
        );
        $this->sendSuccessResponse($similarities);
    }

    public function checkAddSimilarities(string $id, string $solutionId): void
    {
        $batch = $this->detectionBatches->findOrThrow($id);
        if (!$this->plagiarismAcl->canUpdateBatch($batch)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Appends one detected similarity record (similarities associated with one file and one other author)
     * into a detected batch. This division was selected to make the appends relatively small and manageable.
     * @POST
     */
    #[Post("solutionFileId", new VUuid(), "Id of the uploaded solution file.")]
    #[Post(
        "fileEntry",
        new VString(0, 255),
        "Entry (relative path) within a ZIP package (if the uploaded file is a ZIP).",
        required: false,
    )]
    #[Post("authorId", new VUuid(), "Id of the author of the similar solutions/files.")]
    #[Post("similarity", new VDouble(), "Relative similarity of the records associated with selected author [0-1].")]
    #[Post("files", new VArray(), "List of similar files and their records.")]
    #[Path("id", new VUuid(), "Identification of the detection batch", required: true)]
    #[Path("solutionId", new VUuid(), required: true)]
    public function actionAddSimilarities(string $id, string $solutionId): void
    {
        $batch = $this->detectionBatches->findOrThrow($id);
        $solution = $this->assignmentSolutions->findOrThrow($solutionId);

        $req = $this->getRequest();
        $testedFile = $this->uploadedFiles->findOrThrow($req->getPost("solutionFileId"));
        if (
            !($testedFile instanceof SolutionFile) ||
            $testedFile->getSolution()->getId() !== $solution->getSolution()->getId()
        ) {
            throw new BadRequestException("Given solutionFileId must refer to a file related to selected solution.");
        }

        $testedFileEntry = $req->getPost("fileEntry") ?? '';
        $author = $this->users->findOrThrow($req->getPost('authorId'));
        $similarity = min(1.0, max(0.0, (float)$req->getPost("similarity")));

        $detectedSimilarity = new PlagiarismDetectedSimilarity(
            $batch,
            $author,
            $solution,
            $testedFile,
            $testedFileEntry,
            $similarity
        );

        foreach ($req->getPost("files") as $file) {
            $similarSolution = array_key_exists('solutionId', $file)
                ? $this->assignmentSolutions->findOrThrow($file['solutionId']) : null;
            $similarFile = array_key_exists('solutionFileId', $file)
                ? $this->uploadedFiles->findOrThrow($file['solutionFileId']) : null;

            // correctness checks
            if ($similarSolution && $similarSolution->getSolution()->getAuthor()->getId() !== $author->getId()) {
                throw new BadRequestException(
                    "All similar solutions referred in 'files' must be of the given author."
                );
            }
            if (
                $similarFile && (!($similarFile instanceof SolutionFile) || !$similarSolution
                    || $similarFile->getSolution()->getId() !== $similarSolution->getSolution()->getId())
            ) {
                throw new BadRequestException(
                    "In the similar files record, every solutionFileId must refer "
                        . "to a file related to the corresponding selected solution."
                );
            }

            try {
                new PlagiarismDetectedSimilarFile(
                    $detectedSimilarity,
                    $similarSolution,
                    $similarFile,
                    $file['fileEntry'] ?? '',
                    $file['fragments'] ?? []
                );
                // actually, nothing else to do with detected file (it is automatically added to detected similarity)
            } catch (ParseException $e) {
                throw new BadRequestException(
                    "File fragments structure is not correct. " . $e->getMessage(),
                    FrontendErrorMappings::E400_000__BAD_REQUEST,
                    null,
                    $e
                );
            }
        }

        $this->detectedSimilarities->persist($detectedSimilarity, false);
        $solution->setPlagiarismBatch($batch);
        $this->assignmentSolutions->persist($solution);
        $this->sendSuccessResponse($this->plagiarismViewFactory->getPlagiarismSimilarityData($detectedSimilarity));
    }
}
