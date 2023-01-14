<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\User;
use App\Model\Entity\PlagiarismDetectionBatch;
use App\Model\Entity\PlagiarismDetectedSimilarity;
use App\Model\Entity\PlagiarismDetectedSimilarFile;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\PlagiarismDetectionBatches;
use App\Model\Repository\PlagiarismDetectedSimilarities;
use App\Model\Repository\PlagiarismDetectedSimilarFiles;
use App\Security\ACL\IPlagiarismPermissions;
use App\Security\ACL\IAssignmentSolutionPermissions;
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
     * @var IPlagiarismPermissions
     * @inject
     */
    public $plagiarismAcl;

    /**
     * @var IAssignmentSolutionPermissions
     * @inject
     */
    public $assignmentSolutinoAcl;

    public function checkCreateBatch(): void
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
     * Update dectection bath record. At the momeny, only the uploadCompletedAt can be changed.
     * @POST
     * @Param(type="post", name="uploadCompleted", validation="bool",
     *   description="Whether the upload of the batch data is completed or not.")
     */
    public function actionUpdateBatch(string $id): void
    {
        $req = $this->getRequest();
        $uploadCompleted = filter_var($req->getPost("uploadCompleted"), FILTER_VALIDATE_BOOLEAN);
        $batch = $this->detectionBatches->findOrThrow($id);
        $batch->setUploadCompleted($uploadCompleted);
        $this->detectionBatches->persist($batch);
        $this->sendSuccessResponse($batch);
    }

    public function checkGetSimilarities(string $id, string $solutionId): void
    {
        $solution = $this->assignmentSolutions->findOrThrow($solutionId);
        if (!$this->assignmentSolutinoAcl->canViewDetectedPlagiarisms($solution)) {
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
        $batch = $this->detectionBatches->findOrThrow($id);
        $solution = $this->assignmentSolutions->findOrThrow($solutionId);
        $similarities = $this->detectedSimilarities->getSolutionSimilarities($batch, $solution);
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
     * into a detected batch. This division was selected to make the appends relatively small and managable.
     * @POST
     * @Param(type="post", name="file", validation="string:1..255",
     *   description="Name of the file of the solution to which this similarities apply.")
     * @Param(type="post", name="authorId", validation="string:36",
     *   description="Id of the author of the similar solutions/files.")
     * @Param(type="post", name="similarity", validation="numeric",
     *   description="Relative similarity of the records associated with selected author [0-1].")
     * @Param(type="post", name="files", validation="array",
     *   description="List of similar files and their records.")
     */
    public function actionAddSimilarities(string $id, string $solutionId): void
    {
        $batch = $this->detectionBatches->findOrThrow($id);
        $solution = $this->assignmentSolutions->findOrThrow($solutionId);

        $req = $this->getRequest();
        $testedFile = $req->getPost("file");
        $author = $this->users->findOrThrow($req->getPost('authorId'));
        $similarity = min(1.0, max(0.0, (float)$req->getPost("similarity")));

        $detectedSimilarity = new PlagiarismDetectedSimilarity($batch, $author, $solution, $testedFile, $similarity);
        foreach ($req->getPost("files") as $file) {
            $similarSolution = array_key_exists('solution', $file)
                ? $this->assignmentSolutions->findOrThrow($file['solution']) : null;
            $similarFile = new PlagiarismDetectedSimilarFile(
                $detectedSimilarity,
                $similarSolution,
                $file['file'] ?? '',
                $file['fragments'] ?? []
            );
        }

        $this->detectedSimilarities->persist($detectedSimilarity);
        $this->sendSuccessResponse($detectedSimilarity);
    }
}
