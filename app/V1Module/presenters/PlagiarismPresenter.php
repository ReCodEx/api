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

    public function noncheckListBatches(?string $detectionTool, ?string $solutionId): void
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckBatchDetail(string $id): void
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckCreateBatch(): void
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
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdateBatch(string $id): void
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
        "List of assignment IDs to be marked as 'nonchecked' by this batch.",
        required: false
    )]
    #[Path("id", new VUuid(), "Identification of the detection batch", required: true)]
    public function actionUpdateBatch(string $id): void
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckGetSimilarities(string $id, string $solutionId): void
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
    #[Path("solutionId", new VString(), required: true)]
    public function actionGetSimilarities(string $id, string $solutionId): void
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckAddSimilarities(string $id, string $solutionId): void
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
    #[Path("solutionId", new VString(), required: true)]
    public function actionAddSimilarities(string $id, string $solutionId): void
    {
        $this->sendSuccessResponse("OK");
    }
}
