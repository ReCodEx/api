<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\ParseException;
use App\Exceptions\FrontendErrorMappings;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\User;
use App\Model\Entity\PlagiarismDetectionBatch;
use App\Model\Entity\PlagiarismDetectedSimilarity;
use App\Model\Entity\PlagiarismDetectedSimilarFile;
use App\Model\Entity\SolutionFile;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\PlagiarismDetectionBatches;
use App\Model\Repository\PlagiarismDetectedSimilarities;
use App\Model\Repository\PlagiarismDetectedSimilarFiles;
use App\Model\Repository\UploadedFiles;
use App\Security\ACL\IPlagiarismPermissions;
use App\Security\ACL\IAssignmentSolutionPermissions;
use App\Model\View\PlagiarismViewFactory;
use DateTime;

/**
 * Presenter handling plagiarism-related stuff (similarity records and their presentation)
 */
class PlagiarismPresenter extends BasePresenter
{
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
    public $plagiarismViewFatory;

    public function noncheckListBatches(?string $detectionTool, ?string $solutionId): void
    {
        if (!$this->plagiarismAcl->canViewBatches()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of all batches, optionally filtered by query params.
     * @GET
     * @Param(type="query", name="detectionTool", required=false, validation="string:1..255",
     *        description="Requests only batches created by a particular detection tool.")
     * @Param(type="query", name="solutionId", required=false, validation="string:36",
     *        description="Requests only batches where particular solution has detected similarities.")
     */
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
     * @Param(type="post", name="detectionTool", validation="string:1..255",
     *   description="Identifier of the external tool used to detect similarities.")
     * @Param(type="post", name="detectionToolParams", validation="string:0..255", required="false"
     *   description="Tool-specific parameters (e.g., CLI args) used for this particular batch.")
     */
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
     * Update dectection bath record. At the momeny, only the uploadCompletedAt can be changed.
     * @POST
     * @Param(type="post", name="uploadCompleted", validation="bool",
     *   description="Whether the upload of the batch data is completed or not.")
     */
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
     * into a detected batch. This division was selected to make the appends relatively small and managable.
     * @POST
     * @Param(type="post", name="solutionFileId", validation="string:36",
     *   description="Id of the uploaded solution file.")
     * @Param(type="post", name="fileEntry", validation="string:0..255", required=false,
     *   description="Entry (relative path) within a ZIP package (if the uploaded file is a ZIP).")
     * @Param(type="post", name="authorId", validation="string:36",
     *   description="Id of the author of the similar solutions/files.")
     * @Param(type="post", name="similarity", validation="numeric",
     *   description="Relative similarity of the records associated with selected author [0-1].")
     * @Param(type="post", name="files", validation="array",
     *   description="List of similar files and their records.")
     */
    public function actionAddSimilarities(string $id, string $solutionId): void
    {
        $this->sendSuccessResponse("OK");
    }
}
