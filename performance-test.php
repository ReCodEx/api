<?php

use Nette\Application\Request;
use App\Exceptions\InternalServerException;
use App\Helpers\Mocks\MockHelper;
use App\V1Module\Presenters\BasePresenter;
use Tester\Assert;

require __DIR__ . '/tests/bootstrap.php';

class PerformanceTest
{
    protected static $defaultName = 'test:performance';

    private static $warmupIterations = 10_000;
    private static $measureIterations = 100_000;
    private $tests = [];
    public $output = [];

    public function __construct()
    {
        $this->tests = getData();
    }

    public function printResults()
    {
        foreach ($this->output as $line) {
            echo $line . "\n";
        }
    }

    private function warmup(BasePresenter $presenter, Request $request)
    {
        // test whether the request works
        for ($i = 0; $i < self::$warmupIterations; $i++) {
            $response = $presenter->run($request);
            if ($response->getPayload()["payload"] != "OK") {
                throw new InternalServerException("The endpoint did not run correctly.");
            }
        }
    }

    private function measure(BasePresenter $presenter, Request $request): float
    {
        $startTime = microtime(true);
        for ($i = 0; $i < self::$measureIterations; $i++) {
            $response = $presenter->run($request);
        }
        $endTime = microtime(true);

        return $endTime - $startTime;
    }

    public function execute()
    {
        try {
            foreach ($this->tests as $name => $request) {
                // $presenterName = substr($name, 0, strpos($name, "."));
                $presenterName = substr($name, 0, strpos($name, "."));
                $presenter = new $presenterName;
                MockHelper::initPresenter($presenter);
    
                $this->output[] = "Executing test: " . $name;
                
                $this->warmup($presenter, $request);
                $elapsed = $this->measure($presenter, $request);
        
                $this->output[] = "Time elapsed: " . $elapsed;
            }
        } catch (Exception $e) {
            $this->printResults();
            throw $e;
        }
    }
}

$test = new PerformanceTest();
$test->execute();
$test->printResults();
Assert::true(true);


function getData(): array
{
    return [
        "App\V1Module\Presenters\SecurityPresenter.actionCheck" => new Request(
            "name",
            method: "POST",
            params: ["action" => "check",],
            post: [ "url" => "string", "method" => "string",],
        ),
        "App\V1Module\Presenters\LoginPresenter.actionDefault" => new Request(
            "name",
            method: "POST",
            params: ["action" => "default",],
            post: [ "username" => "name@domain.tld", "password" => "text",],
        ),
        "App\V1Module\Presenters\LoginPresenter.actionRefresh" => new Request(
            "name",
            method: "POST",
            params: ["action" => "refresh",],
            post: [],
        ),
        "App\V1Module\Presenters\LoginPresenter.actionIssueRestrictedToken" => new Request(
            "name",
            method: "POST",
            params: ["action" => "issueRestrictedToken",],
            post: [ "effectiveRole" => "text", "scopes" => [], "expiration" => 0,],
        ),
        "App\V1Module\Presenters\LoginPresenter.actionTakeOver" => new Request(
            "name",
            method: "POST",
            params: ["action" => "takeOver", "userId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\LoginPresenter.actionExternal" => new Request(
            "name",
            method: "POST",
            params: ["action" => "external", "authenticatorName" => "text",],
            post: [ "token" => "text",],
        ),
        "App\V1Module\Presenters\BrokerPresenter.actionStats" => new Request(
            "name",
            method: "GET",
            params: ["action" => "stats",],
            post: [],
        ),
        "App\V1Module\Presenters\BrokerPresenter.actionFreeze" => new Request(
            "name",
            method: "POST",
            params: ["action" => "freeze",],
            post: [],
        ),
        "App\V1Module\Presenters\BrokerPresenter.actionUnfreeze" => new Request(
            "name",
            method: "POST",
            params: ["action" => "unfreeze",],
            post: [],
        ),
        "App\V1Module\Presenters\BrokerReportsPresenter.actionError" => new Request(
            "name",
            method: "POST",
            params: ["action" => "error",],
            post: [ "message" => "string",],
        ),
        "App\V1Module\Presenters\BrokerReportsPresenter.actionJobStatus" => new Request(
            "name",
            method: "POST",
            params: ["action" => "jobStatus", "jobId" => "text",],
            post: [ "status" => "string", "message" => "string",],
        ),
        "App\V1Module\Presenters\CommentsPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\CommentsPresenter.actionAddComment" => new Request(
            "name",
            method: "POST",
            params: ["action" => "addComment", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "text" => "text", "isPrivate" => true,],
        ),
        "App\V1Module\Presenters\CommentsPresenter.actionTogglePrivate" => new Request(
            "name",
            method: "POST",
            params: ["action" => "togglePrivate", "threadId" => "text", "commentId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\CommentsPresenter.actionSetPrivate" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setPrivate", "threadId" => "text", "commentId" => "text",],
            post: [ "isPrivate" => true,],
        ),
        "App\V1Module\Presenters\CommentsPresenter.actionDelete" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "delete", "threadId" => "text", "commentId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default", "offset" => "0", "limit" => "0", "orderBy" => "text", "filters" => [], "locale" => "en",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionCreate" => new Request(
            "name",
            method: "POST",
            params: ["action" => "create",],
            post: [ "groupId" => "string",],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionListByIds" => new Request(
            "name",
            method: "POST",
            params: ["action" => "listByIds",],
            post: [ "ids" => [],],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionAuthors" => new Request(
            "name",
            method: "GET",
            params: ["action" => "authors", "instanceId" => "text", "groupId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionAllTags" => new Request(
            "name",
            method: "GET",
            params: ["action" => "allTags",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionTagsStats" => new Request(
            "name",
            method: "GET",
            params: ["action" => "tagsStats",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionTagsUpdateGlobal" => new Request(
            "name",
            method: "POST",
            params: ["action" => "tagsUpdateGlobal", "tag" => "text", "renameTo" => "text", "force" => true,],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionTagsRemoveGlobal" => new Request(
            "name",
            method: "POST",
            params: ["action" => "tagsRemoveGlobal", "tag" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionDetail" => new Request(
            "name",
            method: "GET",
            params: ["action" => "detail", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionRemove" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "remove", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionUpdateDetail" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updateDetail", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "version" => 0, "difficulty" => "string", "localizedTexts" => [], "isPublic" => true, "isLocked" => true, "configurationType" => "text", "solutionFilesLimit" => 0, "solutionSizeLimit" => 0, "mergeJudgeLogs" => true,],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionValidate" => new Request(
            "name",
            method: "POST",
            params: ["action" => "validate", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "version" => 0,],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionForkFrom" => new Request(
            "name",
            method: "POST",
            params: ["action" => "forkFrom", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "groupId" => "string",],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionAssignments" => new Request(
            "name",
            method: "GET",
            params: ["action" => "assignments", "id" => "10000000-2000-4000-8000-160000000000", "archived" => true,],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionHardwareGroups" => new Request(
            "name",
            method: "POST",
            params: ["action" => "hardwareGroups", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "hwGroups" => [],],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionAttachGroup" => new Request(
            "name",
            method: "POST",
            params: ["action" => "attachGroup", "id" => "10000000-2000-4000-8000-160000000000", "groupId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionDetachGroup" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "detachGroup", "id" => "10000000-2000-4000-8000-160000000000", "groupId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionAddTag" => new Request(
            "name",
            method: "POST",
            params: ["action" => "addTag", "id" => "10000000-2000-4000-8000-160000000000", "name" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionRemoveTag" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "removeTag", "id" => "10000000-2000-4000-8000-160000000000", "name" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionSetArchived" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setArchived", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "archived" => true,],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionSetAuthor" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setAuthor", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "author" => "10000000-2000-4000-8000-160000000000",],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionSetAdmins" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setAdmins", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "admins" => [],],
        ),
        "App\V1Module\Presenters\ExercisesPresenter.actionSendNotification" => new Request(
            "name",
            method: "POST",
            params: ["action" => "sendNotification", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "message" => "text",],
        ),
        "App\V1Module\Presenters\ExerciseFilesPresenter.actionGetSupplementaryFiles" => new Request(
            "name",
            method: "GET",
            params: ["action" => "getSupplementaryFiles", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\ExerciseFilesPresenter.actionUploadSupplementaryFiles" => new Request(
            "name",
            method: "POST",
            params: ["action" => "uploadSupplementaryFiles", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "files" => "string",],
        ),
        "App\V1Module\Presenters\ExerciseFilesPresenter.actionDeleteSupplementaryFile" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "deleteSupplementaryFile", "id" => "10000000-2000-4000-8000-160000000000", "fileId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ExerciseFilesPresenter.actionDownloadSupplementaryFilesArchive" => new Request(
            "name",
            method: "GET",
            params: ["action" => "downloadSupplementaryFilesArchive", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\ExerciseFilesPresenter.actionGetAttachmentFiles" => new Request(
            "name",
            method: "GET",
            params: ["action" => "getAttachmentFiles", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\ExerciseFilesPresenter.actionUploadAttachmentFiles" => new Request(
            "name",
            method: "POST",
            params: ["action" => "uploadAttachmentFiles", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "files" => "string",],
        ),
        "App\V1Module\Presenters\ExerciseFilesPresenter.actionDeleteAttachmentFile" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "deleteAttachmentFile", "id" => "10000000-2000-4000-8000-160000000000", "fileId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ExerciseFilesPresenter.actionDownloadAttachmentFilesArchive" => new Request(
            "name",
            method: "GET",
            params: ["action" => "downloadAttachmentFilesArchive", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesConfigPresenter.actionGetTests" => new Request(
            "name",
            method: "GET",
            params: ["action" => "getTests", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesConfigPresenter.actionSetTests" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setTests", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "tests" => [],],
        ),
        "App\V1Module\Presenters\ExercisesConfigPresenter.actionGetEnvironmentConfigs" => new Request(
            "name",
            method: "GET",
            params: ["action" => "getEnvironmentConfigs", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesConfigPresenter.actionUpdateEnvironmentConfigs" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updateEnvironmentConfigs", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "environmentConfigs" => [],],
        ),
        "App\V1Module\Presenters\ExercisesConfigPresenter.actionGetConfiguration" => new Request(
            "name",
            method: "GET",
            params: ["action" => "getConfiguration", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesConfigPresenter.actionSetConfiguration" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setConfiguration", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "config" => [],],
        ),
        "App\V1Module\Presenters\ExercisesConfigPresenter.actionGetVariablesForExerciseConfig" => new Request(
            "name",
            method: "POST",
            params: ["action" => "getVariablesForExerciseConfig", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "runtimeEnvironmentId" => "text", "pipelinesIds" => [],],
        ),
        "App\V1Module\Presenters\ExercisesConfigPresenter.actionGetHardwareGroupLimits" => new Request(
            "name",
            method: "GET",
            params: ["action" => "getHardwareGroupLimits", "id" => "10000000-2000-4000-8000-160000000000", "runtimeEnvironmentId" => "text", "hwGroupId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesConfigPresenter.actionSetHardwareGroupLimits" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setHardwareGroupLimits", "id" => "10000000-2000-4000-8000-160000000000", "runtimeEnvironmentId" => "text", "hwGroupId" => "text",],
            post: [ "limits" => [],],
        ),
        "App\V1Module\Presenters\ExercisesConfigPresenter.actionRemoveHardwareGroupLimits" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "removeHardwareGroupLimits", "id" => "10000000-2000-4000-8000-160000000000", "runtimeEnvironmentId" => "text", "hwGroupId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesConfigPresenter.actionGetLimits" => new Request(
            "name",
            method: "GET",
            params: ["action" => "getLimits", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesConfigPresenter.actionSetLimits" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setLimits", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "limits" => [],],
        ),
        "App\V1Module\Presenters\ExercisesConfigPresenter.actionGetScoreConfig" => new Request(
            "name",
            method: "GET",
            params: ["action" => "getScoreConfig", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\ExercisesConfigPresenter.actionSetScoreConfig" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setScoreConfig", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "scoreCalculator" => "text", "scoreConfig" => "string",],
        ),
        "App\V1Module\Presenters\AssignmentsPresenter.actionCreate" => new Request(
            "name",
            method: "POST",
            params: ["action" => "create",],
            post: [ "exerciseId" => "string", "groupId" => "string",],
        ),
        "App\V1Module\Presenters\AssignmentsPresenter.actionDetail" => new Request(
            "name",
            method: "GET",
            params: ["action" => "detail", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentsPresenter.actionUpdateDetail" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updateDetail", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "version" => 0, "isPublic" => true, "localizedTexts" => [], "firstDeadline" => 1740135333, "maxPointsBeforeFirstDeadline" => 0, "submissionsCountLimit" => 0, "solutionFilesLimit" => 0, "solutionSizeLimit" => 0, "allowSecondDeadline" => true, "visibleFrom" => 1740135333, "canViewLimitRatios" => true, "canViewMeasuredValues" => true, "canViewJudgeStdout" => true, "canViewJudgeStderr" => true, "secondDeadline" => 1740135333, "maxPointsBeforeSecondDeadline" => 0, "maxPointsDeadlineInterpolation" => true, "isBonus" => true, "pointsPercentualThreshold" => 0.1, "disabledRuntimeEnvironmentIds" => [], "sendNotification" => true, "isExam" => true,],
        ),
        "App\V1Module\Presenters\AssignmentsPresenter.actionRemove" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "remove", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentsPresenter.actionSolutions" => new Request(
            "name",
            method: "GET",
            params: ["action" => "solutions", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentsPresenter.actionBestSolutions" => new Request(
            "name",
            method: "GET",
            params: ["action" => "bestSolutions", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentsPresenter.actionDownloadBestSolutionsArchive" => new Request(
            "name",
            method: "GET",
            params: ["action" => "downloadBestSolutionsArchive", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentsPresenter.actionUserSolutions" => new Request(
            "name",
            method: "GET",
            params: ["action" => "userSolutions", "id" => "10000000-2000-4000-8000-160000000000", "userId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentsPresenter.actionBestSolution" => new Request(
            "name",
            method: "GET",
            params: ["action" => "bestSolution", "id" => "10000000-2000-4000-8000-160000000000", "userId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentsPresenter.actionValidate" => new Request(
            "name",
            method: "POST",
            params: ["action" => "validate", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "version" => 0,],
        ),
        "App\V1Module\Presenters\AssignmentsPresenter.actionSyncWithExercise" => new Request(
            "name",
            method: "POST",
            params: ["action" => "syncWithExercise", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\SubmitPresenter.actionCanSubmit" => new Request(
            "name",
            method: "GET",
            params: ["action" => "canSubmit", "id" => "10000000-2000-4000-8000-160000000000", "userId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\SubmitPresenter.actionSubmit" => new Request(
            "name",
            method: "POST",
            params: ["action" => "submit", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "note" => "text", "userId" => "string", "files" => "string", "runtimeEnvironmentId" => "string", "solutionParams" => "string",],
        ),
        "App\V1Module\Presenters\SubmitPresenter.actionResubmitAllAsyncJobStatus" => new Request(
            "name",
            method: "GET",
            params: ["action" => "resubmitAllAsyncJobStatus", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\SubmitPresenter.actionResubmitAll" => new Request(
            "name",
            method: "POST",
            params: ["action" => "resubmitAll", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\SubmitPresenter.actionPreSubmit" => new Request(
            "name",
            method: "POST",
            params: ["action" => "preSubmit", "id" => "10000000-2000-4000-8000-160000000000", "userId" => "text",],
            post: [ "files" => [],],
        ),
        "App\V1Module\Presenters\AsyncJobsPresenter.actionAssignmentJobs" => new Request(
            "name",
            method: "GET",
            params: ["action" => "assignmentJobs", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default", "instanceId" => "text", "ancestors" => true, "search" => "text", "archived" => true, "onlyArchived" => true,],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionAddGroup" => new Request(
            "name",
            method: "POST",
            params: ["action" => "addGroup",],
            post: [ "instanceId" => "10000000-2000-4000-8000-160000000000", "externalId" => "string", "parentGroupId" => "10000000-2000-4000-8000-160000000000", "publicStats" => true, "detaining" => true, "isPublic" => true, "isOrganizational" => true, "isExam" => true, "localizedTexts" => [], "threshold" => 0, "pointsLimit" => 0, "noAdmin" => true,],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionValidateAddGroupData" => new Request(
            "name",
            method: "POST",
            params: ["action" => "validateAddGroupData",],
            post: [ "name" => "string", "locale" => "en", "instanceId" => "string", "parentGroupId" => "string",],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionDetail" => new Request(
            "name",
            method: "GET",
            params: ["action" => "detail", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionUpdateGroup" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updateGroup", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "externalId" => "string", "publicStats" => true, "detaining" => true, "isPublic" => true, "threshold" => 0, "pointsLimit" => 0, "localizedTexts" => [],],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionRemoveGroup" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "removeGroup", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionSubgroups" => new Request(
            "name",
            method: "GET",
            params: ["action" => "subgroups", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionSetOrganizational" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setOrganizational", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "value" => true,],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionSetArchived" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setArchived", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "value" => true,],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionSetExam" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setExam", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "value" => true,],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionSetExamPeriod" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setExamPeriod", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "begin" => 1740135333, "end" => 1740135333, "strict" => true,],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionRemoveExamPeriod" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "removeExamPeriod", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionGetExamLocks" => new Request(
            "name",
            method: "GET",
            params: ["action" => "getExamLocks", "id" => "10000000-2000-4000-8000-160000000000", "examId" => "0",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionRelocate" => new Request(
            "name",
            method: "POST",
            params: ["action" => "relocate", "id" => "10000000-2000-4000-8000-160000000000", "newParentId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionStats" => new Request(
            "name",
            method: "GET",
            params: ["action" => "stats", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionStudentsStats" => new Request(
            "name",
            method: "GET",
            params: ["action" => "studentsStats", "id" => "10000000-2000-4000-8000-160000000000", "userId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionStudentsSolutions" => new Request(
            "name",
            method: "GET",
            params: ["action" => "studentsSolutions", "id" => "10000000-2000-4000-8000-160000000000", "userId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionAddStudent" => new Request(
            "name",
            method: "POST",
            params: ["action" => "addStudent", "id" => "10000000-2000-4000-8000-160000000000", "userId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionRemoveStudent" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "removeStudent", "id" => "10000000-2000-4000-8000-160000000000", "userId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionLockStudent" => new Request(
            "name",
            method: "POST",
            params: ["action" => "lockStudent", "id" => "10000000-2000-4000-8000-160000000000", "userId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionUnlockStudent" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "unlockStudent", "id" => "10000000-2000-4000-8000-160000000000", "userId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionMembers" => new Request(
            "name",
            method: "GET",
            params: ["action" => "members", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionAddMember" => new Request(
            "name",
            method: "POST",
            params: ["action" => "addMember", "id" => "10000000-2000-4000-8000-160000000000", "userId" => "text",],
            post: [ "type" => "text",],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionRemoveMember" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "removeMember", "id" => "10000000-2000-4000-8000-160000000000", "userId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionAssignments" => new Request(
            "name",
            method: "GET",
            params: ["action" => "assignments", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupsPresenter.actionShadowAssignments" => new Request(
            "name",
            method: "GET",
            params: ["action" => "shadowAssignments", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupInvitationsPresenter.actionList" => new Request(
            "name",
            method: "GET",
            params: ["action" => "list", "groupId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupInvitationsPresenter.actionCreate" => new Request(
            "name",
            method: "POST",
            params: ["action" => "create", "groupId" => "text",],
            post: [ "expireAt" => 1740135333, "note" => "string",],
        ),
        "App\V1Module\Presenters\GroupInvitationsPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupInvitationsPresenter.actionUpdate" => new Request(
            "name",
            method: "POST",
            params: ["action" => "update", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "expireAt" => 1740135333, "note" => "string",],
        ),
        "App\V1Module\Presenters\GroupInvitationsPresenter.actionRemove" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "remove", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupInvitationsPresenter.actionAccept" => new Request(
            "name",
            method: "POST",
            params: ["action" => "accept", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupExternalAttributesPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default", "filter" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\GroupExternalAttributesPresenter.actionAdd" => new Request(
            "name",
            method: "POST",
            params: ["action" => "add", "groupId" => "text",],
            post: [ "service" => "text", "key" => "text", "value" => "text",],
        ),
        "App\V1Module\Presenters\GroupExternalAttributesPresenter.actionRemove" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "remove", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\InstancesPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default",],
            post: [],
        ),
        "App\V1Module\Presenters\InstancesPresenter.actionCreateInstance" => new Request(
            "name",
            method: "POST",
            params: ["action" => "createInstance",],
            post: [ "name" => "text", "description" => "string", "isOpen" => true,],
        ),
        "App\V1Module\Presenters\InstancesPresenter.actionDetail" => new Request(
            "name",
            method: "GET",
            params: ["action" => "detail", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\InstancesPresenter.actionUpdateInstance" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updateInstance", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "isOpen" => true,],
        ),
        "App\V1Module\Presenters\InstancesPresenter.actionDeleteInstance" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "deleteInstance", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\InstancesPresenter.actionLicences" => new Request(
            "name",
            method: "GET",
            params: ["action" => "licences", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\InstancesPresenter.actionCreateLicence" => new Request(
            "name",
            method: "POST",
            params: ["action" => "createLicence", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "note" => "text", "validUntil" => 1740135333,],
        ),
        "App\V1Module\Presenters\InstancesPresenter.actionUpdateLicence" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updateLicence", "licenceId" => "text",],
            post: [ "note" => "text", "validUntil" => "text", "isValid" => true,],
        ),
        "App\V1Module\Presenters\InstancesPresenter.actionDeleteLicence" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "deleteLicence", "licenceId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionSolutions" => new Request(
            "name",
            method: "GET",
            params: ["action" => "solutions", "exerciseId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionPreSubmit" => new Request(
            "name",
            method: "POST",
            params: ["action" => "preSubmit", "exerciseId" => "text",],
            post: [ "files" => [],],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionSubmit" => new Request(
            "name",
            method: "POST",
            params: ["action" => "submit", "exerciseId" => "text",],
            post: [ "note" => "text", "files" => "string", "runtimeEnvironmentId" => "string", "solutionParams" => "string",],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionResubmitAll" => new Request(
            "name",
            method: "POST",
            params: ["action" => "resubmitAll", "exerciseId" => "text",],
            post: [ "debug" => true,],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionDetail" => new Request(
            "name",
            method: "GET",
            params: ["action" => "detail", "solutionId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionUpdate" => new Request(
            "name",
            method: "POST",
            params: ["action" => "update", "solutionId" => "text",],
            post: [ "note" => "text",],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionDeleteReferenceSolution" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "deleteReferenceSolution", "solutionId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionResubmit" => new Request(
            "name",
            method: "POST",
            params: ["action" => "resubmit", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "debug" => true,],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionSubmissions" => new Request(
            "name",
            method: "GET",
            params: ["action" => "submissions", "solutionId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionFiles" => new Request(
            "name",
            method: "GET",
            params: ["action" => "files", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionDownloadSolutionArchive" => new Request(
            "name",
            method: "GET",
            params: ["action" => "downloadSolutionArchive", "solutionId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionSetVisibility" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setVisibility", "solutionId" => "text",],
            post: [ "visibility" => 0,],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionSubmission" => new Request(
            "name",
            method: "GET",
            params: ["action" => "submission", "submissionId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionDeleteSubmission" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "deleteSubmission", "submissionId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionDownloadResultArchive" => new Request(
            "name",
            method: "GET",
            params: ["action" => "downloadResultArchive", "submissionId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter.actionEvaluationScoreConfig" => new Request(
            "name",
            method: "GET",
            params: ["action" => "evaluationScoreConfig", "submissionId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentSolutionsPresenter.actionSolution" => new Request(
            "name",
            method: "GET",
            params: ["action" => "solution", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentSolutionsPresenter.actionUpdateSolution" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updateSolution", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "note" => "text",],
        ),
        "App\V1Module\Presenters\AssignmentSolutionsPresenter.actionDeleteSolution" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "deleteSolution", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentSolutionsPresenter.actionSetBonusPoints" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setBonusPoints", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "bonusPoints" => 0, "overriddenPoints" => "string",],
        ),
        "App\V1Module\Presenters\AssignmentSolutionsPresenter.actionSubmissions" => new Request(
            "name",
            method: "GET",
            params: ["action" => "submissions", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentSolutionsPresenter.actionSetFlag" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setFlag", "id" => "10000000-2000-4000-8000-160000000000", "flag" => "text",],
            post: [ "value" => true,],
        ),
        "App\V1Module\Presenters\SubmitPresenter.actionResubmit" => new Request(
            "name",
            method: "POST",
            params: ["action" => "resubmit", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "debug" => true,],
        ),
        "App\V1Module\Presenters\AssignmentSolutionsPresenter.actionFiles" => new Request(
            "name",
            method: "GET",
            params: ["action" => "files", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentSolutionsPresenter.actionDownloadSolutionArchive" => new Request(
            "name",
            method: "GET",
            params: ["action" => "downloadSolutionArchive", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentSolutionsPresenter.actionSubmission" => new Request(
            "name",
            method: "GET",
            params: ["action" => "submission", "submissionId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentSolutionsPresenter.actionDeleteSubmission" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "deleteSubmission", "submissionId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentSolutionsPresenter.actionDownloadResultArchive" => new Request(
            "name",
            method: "GET",
            params: ["action" => "downloadResultArchive", "submissionId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentSolutionsPresenter.actionEvaluationScoreConfig" => new Request(
            "name",
            method: "GET",
            params: ["action" => "evaluationScoreConfig", "submissionId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentSolutionReviewsPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentSolutionReviewsPresenter.actionUpdate" => new Request(
            "name",
            method: "POST",
            params: ["action" => "update", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "close" => true,],
        ),
        "App\V1Module\Presenters\AssignmentSolutionReviewsPresenter.actionRemove" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "remove", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentSolutionReviewsPresenter.actionNewComment" => new Request(
            "name",
            method: "POST",
            params: ["action" => "newComment", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "text" => "text", "file" => "text", "line" => 0, "issue" => true, "suppressNotification" => true,],
        ),
        "App\V1Module\Presenters\AssignmentSolutionReviewsPresenter.actionEditComment" => new Request(
            "name",
            method: "POST",
            params: ["action" => "editComment", "id" => "10000000-2000-4000-8000-160000000000", "commentId" => "text",],
            post: [ "text" => "text", "issue" => true, "suppressNotification" => true,],
        ),
        "App\V1Module\Presenters\AssignmentSolutionReviewsPresenter.actionDeleteComment" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "deleteComment", "id" => "10000000-2000-4000-8000-160000000000", "commentId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentSolversPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default", "assignmentId" => "10000000-2000-4000-8000-160000000000", "groupId" => "10000000-2000-4000-8000-160000000000", "userId" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\SubmissionFailuresPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default",],
            post: [],
        ),
        "App\V1Module\Presenters\SubmissionFailuresPresenter.actionUnresolved" => new Request(
            "name",
            method: "GET",
            params: ["action" => "unresolved",],
            post: [],
        ),
        "App\V1Module\Presenters\SubmissionFailuresPresenter.actionDetail" => new Request(
            "name",
            method: "GET",
            params: ["action" => "detail", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\SubmissionFailuresPresenter.actionResolve" => new Request(
            "name",
            method: "POST",
            params: ["action" => "resolve", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "note" => "text", "sendEmail" => true,],
        ),
        "App\V1Module\Presenters\UploadedFilesPresenter.actionStartPartial" => new Request(
            "name",
            method: "POST",
            params: ["action" => "startPartial",],
            post: [ "name" => "text", "size" => 0,],
        ),
        "App\V1Module\Presenters\UploadedFilesPresenter.actionAppendPartial" => new Request(
            "name",
            method: "PUT",
            params: ["action" => "appendPartial", "id" => "10000000-2000-4000-8000-160000000000", "offset" => "0",],
            post: [],
        ),
        "App\V1Module\Presenters\UploadedFilesPresenter.actionCancelPartial" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "cancelPartial", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\UploadedFilesPresenter.actionCompletePartial" => new Request(
            "name",
            method: "POST",
            params: ["action" => "completePartial", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\UploadedFilesPresenter.actionUpload" => new Request(
            "name",
            method: "POST",
            params: ["action" => "upload",],
            post: [],
        ),
        "App\V1Module\Presenters\UploadedFilesPresenter.actionDownloadSupplementaryFile" => new Request(
            "name",
            method: "GET",
            params: ["action" => "downloadSupplementaryFile", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\UploadedFilesPresenter.actionDetail" => new Request(
            "name",
            method: "GET",
            params: ["action" => "detail", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\UploadedFilesPresenter.actionDownload" => new Request(
            "name",
            method: "GET",
            params: ["action" => "download", "id" => "10000000-2000-4000-8000-160000000000", "entry" => "text", "similarSolutionId" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\UploadedFilesPresenter.actionContent" => new Request(
            "name",
            method: "GET",
            params: ["action" => "content", "id" => "10000000-2000-4000-8000-160000000000", "entry" => "text", "similarSolutionId" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\UploadedFilesPresenter.actionDigest" => new Request(
            "name",
            method: "GET",
            params: ["action" => "digest", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default", "offset" => "0", "limit" => "0", "orderBy" => "text", "filters" => [], "locale" => "en",],
            post: [],
        ),
        "App\V1Module\Presenters\RegistrationPresenter.actionCreateAccount" => new Request(
            "name",
            method: "POST",
            params: ["action" => "createAccount",],
            post: [ "email" => "name@domain.tld", "firstName" => "text", "lastName" => "text", "password" => "text", "passwordConfirm" => "text", "instanceId" => "text", "titlesBeforeName" => "text", "titlesAfterName" => "text", "ignoreNameCollision" => true,],
        ),
        "App\V1Module\Presenters\RegistrationPresenter.actionValidateRegistrationData" => new Request(
            "name",
            method: "POST",
            params: ["action" => "validateRegistrationData",],
            post: [ "email" => "string", "password" => "string",],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionListByIds" => new Request(
            "name",
            method: "POST",
            params: ["action" => "listByIds",],
            post: [ "ids" => [],],
        ),
        "App\V1Module\Presenters\UserCalendarsPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default", "id" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\UserCalendarsPresenter.actionExpireCalendar" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "expireCalendar", "id" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\RegistrationPresenter.actionCreateInvitation" => new Request(
            "name",
            method: "POST",
            params: ["action" => "createInvitation",],
            post: [ "email" => "name@domain.tld", "firstName" => "text", "lastName" => "text", "instanceId" => "10000000-2000-4000-8000-160000000000", "titlesBeforeName" => "text", "titlesAfterName" => "text", "groups" => [], "locale" => "en", "ignoreNameCollision" => true,],
        ),
        "App\V1Module\Presenters\RegistrationPresenter.actionAcceptInvitation" => new Request(
            "name",
            method: "POST",
            params: ["action" => "acceptInvitation",],
            post: [ "token" => "text", "password" => "text", "passwordConfirm" => "text",],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionDetail" => new Request(
            "name",
            method: "GET",
            params: ["action" => "detail", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionInvalidateTokens" => new Request(
            "name",
            method: "POST",
            params: ["action" => "invalidateTokens", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionDelete" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "delete", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionGroups" => new Request(
            "name",
            method: "GET",
            params: ["action" => "groups", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionAllGroups" => new Request(
            "name",
            method: "GET",
            params: ["action" => "allGroups", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionInstances" => new Request(
            "name",
            method: "GET",
            params: ["action" => "instances", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionUpdateProfile" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updateProfile", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "firstName" => "text", "lastName" => "text", "titlesBeforeName" => "string", "titlesAfterName" => "string", "email" => "name@domain.tld", "oldPassword" => "text", "password" => "text", "passwordConfirm" => "text", "gravatarUrlEnabled" => true,],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionUpdateSettings" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updateSettings", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "defaultLanguage" => "text", "newAssignmentEmails" => true, "assignmentDeadlineEmails" => true, "submissionEvaluatedEmails" => true, "solutionCommentsEmails" => true, "solutionReviewsEmails" => true, "pointsChangedEmails" => true, "assignmentSubmitAfterAcceptedEmails" => true, "assignmentSubmitAfterReviewedEmails" => true, "exerciseNotificationEmails" => true, "solutionAcceptedEmails" => true, "solutionReviewRequestedEmails" => true,],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionUpdateUiData" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updateUiData", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "uiData" => [], "overwrite" => true,],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionCreateLocalAccount" => new Request(
            "name",
            method: "POST",
            params: ["action" => "createLocalAccount", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionSetRole" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setRole", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "role" => "text",],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionSetAllowed" => new Request(
            "name",
            method: "POST",
            params: ["action" => "setAllowed", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "isAllowed" => true,],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionUpdateExternalLogin" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updateExternalLogin", "id" => "10000000-2000-4000-8000-160000000000", "service" => "text",],
            post: [ "externalId" => "text",],
        ),
        "App\V1Module\Presenters\UsersPresenter.actionRemoveExternalLogin" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "removeExternalLogin", "id" => "10000000-2000-4000-8000-160000000000", "service" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\UserCalendarsPresenter.actionUserCalendars" => new Request(
            "name",
            method: "GET",
            params: ["action" => "userCalendars", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\UserCalendarsPresenter.actionCreateCalendar" => new Request(
            "name",
            method: "POST",
            params: ["action" => "createCalendar", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentSolutionReviewsPresenter.actionPending" => new Request(
            "name",
            method: "GET",
            params: ["action" => "pending", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AssignmentSolutionsPresenter.actionReviewRequests" => new Request(
            "name",
            method: "GET",
            params: ["action" => "reviewRequests", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\EmailVerificationPresenter.actionEmailVerification" => new Request(
            "name",
            method: "POST",
            params: ["action" => "emailVerification",],
            post: [],
        ),
        "App\V1Module\Presenters\EmailVerificationPresenter.actionResendVerificationEmail" => new Request(
            "name",
            method: "POST",
            params: ["action" => "resendVerificationEmail",],
            post: [],
        ),
        "App\V1Module\Presenters\ForgottenPasswordPresenter.actionDefault" => new Request(
            "name",
            method: "POST",
            params: ["action" => "default",],
            post: [ "username" => "text",],
        ),
        "App\V1Module\Presenters\ForgottenPasswordPresenter.actionChange" => new Request(
            "name",
            method: "POST",
            params: ["action" => "change",],
            post: [ "password" => "text",],
        ),
        "App\V1Module\Presenters\ForgottenPasswordPresenter.actionValidatePasswordStrength" => new Request(
            "name",
            method: "POST",
            params: ["action" => "validatePasswordStrength",],
            post: [ "password" => "string",],
        ),
        "App\V1Module\Presenters\RuntimeEnvironmentsPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default",],
            post: [],
        ),
        "App\V1Module\Presenters\HardwareGroupsPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default",],
            post: [],
        ),
        "App\V1Module\Presenters\PipelinesPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default", "offset" => "0", "limit" => "0", "orderBy" => "text", "filters" => [], "locale" => "en",],
            post: [],
        ),
        "App\V1Module\Presenters\PipelinesPresenter.actionCreatePipeline" => new Request(
            "name",
            method: "POST",
            params: ["action" => "createPipeline",],
            post: [ "global" => true,],
        ),
        "App\V1Module\Presenters\PipelinesPresenter.actionGetDefaultBoxes" => new Request(
            "name",
            method: "GET",
            params: ["action" => "getDefaultBoxes",],
            post: [],
        ),
        "App\V1Module\Presenters\PipelinesPresenter.actionForkPipeline" => new Request(
            "name",
            method: "POST",
            params: ["action" => "forkPipeline", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "global" => true,],
        ),
        "App\V1Module\Presenters\PipelinesPresenter.actionGetPipeline" => new Request(
            "name",
            method: "GET",
            params: ["action" => "getPipeline", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\PipelinesPresenter.actionUpdatePipeline" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updatePipeline", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "name" => "text", "version" => 0, "description" => "string", "pipeline" => "string", "parameters" => [], "global" => true,],
        ),
        "App\V1Module\Presenters\PipelinesPresenter.actionRemovePipeline" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "removePipeline", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\PipelinesPresenter.actionUpdateRuntimeEnvironments" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updateRuntimeEnvironments", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\PipelinesPresenter.actionValidatePipeline" => new Request(
            "name",
            method: "POST",
            params: ["action" => "validatePipeline", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "version" => 0,],
        ),
        "App\V1Module\Presenters\PipelinesPresenter.actionGetSupplementaryFiles" => new Request(
            "name",
            method: "GET",
            params: ["action" => "getSupplementaryFiles", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\PipelinesPresenter.actionUploadSupplementaryFiles" => new Request(
            "name",
            method: "POST",
            params: ["action" => "uploadSupplementaryFiles", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "files" => "string",],
        ),
        "App\V1Module\Presenters\PipelinesPresenter.actionDeleteSupplementaryFile" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "deleteSupplementaryFile", "id" => "10000000-2000-4000-8000-160000000000", "fileId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\PipelinesPresenter.actionGetPipelineExercises" => new Request(
            "name",
            method: "GET",
            params: ["action" => "getPipelineExercises", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\SisPresenter.actionStatus" => new Request(
            "name",
            method: "GET",
            params: ["action" => "status",],
            post: [],
        ),
        "App\V1Module\Presenters\SisPresenter.actionGetTerms" => new Request(
            "name",
            method: "GET",
            params: ["action" => "getTerms",],
            post: [],
        ),
        "App\V1Module\Presenters\SisPresenter.actionRegisterTerm" => new Request(
            "name",
            method: "POST",
            params: ["action" => "registerTerm",],
            post: [ "year" => "string", "term" => "string",],
        ),
        "App\V1Module\Presenters\SisPresenter.actionEditTerm" => new Request(
            "name",
            method: "POST",
            params: ["action" => "editTerm", "id" => "text",],
            post: [ "beginning" => 1740135333, "end" => 1740135333, "advertiseUntil" => 1740135333,],
        ),
        "App\V1Module\Presenters\SisPresenter.actionDeleteTerm" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "deleteTerm", "id" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\SisPresenter.actionSubscribedCourses" => new Request(
            "name",
            method: "GET",
            params: ["action" => "subscribedCourses", "userId" => "text", "year" => "0", "term" => "0",],
            post: [],
        ),
        "App\V1Module\Presenters\SisPresenter.actionSupervisedCourses" => new Request(
            "name",
            method: "GET",
            params: ["action" => "supervisedCourses", "userId" => "text", "year" => "0", "term" => "0",],
            post: [],
        ),
        "App\V1Module\Presenters\SisPresenter.actionPossibleParents" => new Request(
            "name",
            method: "GET",
            params: ["action" => "possibleParents", "courseId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\SisPresenter.actionCreateGroup" => new Request(
            "name",
            method: "POST",
            params: ["action" => "createGroup", "courseId" => "text",],
            post: [ "parentGroupId" => "string",],
        ),
        "App\V1Module\Presenters\SisPresenter.actionBindGroup" => new Request(
            "name",
            method: "POST",
            params: ["action" => "bindGroup", "courseId" => "text",],
            post: [ "groupId" => "string",],
        ),
        "App\V1Module\Presenters\SisPresenter.actionUnbindGroup" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "unbindGroup", "courseId" => "text", "groupId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\EmailsPresenter.actionDefault" => new Request(
            "name",
            method: "POST",
            params: ["action" => "default",],
            post: [ "subject" => "text", "message" => "text",],
        ),
        "App\V1Module\Presenters\EmailsPresenter.actionSendToSupervisors" => new Request(
            "name",
            method: "POST",
            params: ["action" => "sendToSupervisors",],
            post: [ "subject" => "text", "message" => "text",],
        ),
        "App\V1Module\Presenters\EmailsPresenter.actionSendToRegularUsers" => new Request(
            "name",
            method: "POST",
            params: ["action" => "sendToRegularUsers",],
            post: [ "subject" => "text", "message" => "text",],
        ),
        "App\V1Module\Presenters\EmailsPresenter.actionSendToGroupMembers" => new Request(
            "name",
            method: "POST",
            params: ["action" => "sendToGroupMembers", "groupId" => "text",],
            post: [ "toSupervisors" => true, "toAdmins" => true, "toObservers" => true, "toMe" => true, "subject" => "text", "message" => "text",],
        ),
        "App\V1Module\Presenters\ShadowAssignmentsPresenter.actionDetail" => new Request(
            "name",
            method: "GET",
            params: ["action" => "detail", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\ShadowAssignmentsPresenter.actionUpdateDetail" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updateDetail", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "version" => 0, "isPublic" => true, "isBonus" => true, "localizedTexts" => [], "maxPoints" => 0, "sendNotification" => true, "deadline" => 1740135333,],
        ),
        "App\V1Module\Presenters\ShadowAssignmentsPresenter.actionCreate" => new Request(
            "name",
            method: "POST",
            params: ["action" => "create",],
            post: [ "groupId" => "string",],
        ),
        "App\V1Module\Presenters\ShadowAssignmentsPresenter.actionRemove" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "remove", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\ShadowAssignmentsPresenter.actionValidate" => new Request(
            "name",
            method: "POST",
            params: ["action" => "validate", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "version" => 0,],
        ),
        "App\V1Module\Presenters\ShadowAssignmentsPresenter.actionCreatePoints" => new Request(
            "name",
            method: "POST",
            params: ["action" => "createPoints", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "userId" => "text", "points" => 0, "note" => "text", "awardedAt" => 1740135333,],
        ),
        "App\V1Module\Presenters\ShadowAssignmentsPresenter.actionUpdatePoints" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updatePoints", "pointsId" => "text",],
            post: [ "points" => 0, "note" => "text", "awardedAt" => 1740135333,],
        ),
        "App\V1Module\Presenters\ShadowAssignmentsPresenter.actionRemovePoints" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "removePoints", "pointsId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\NotificationsPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default", "groupsIds" => [],],
            post: [],
        ),
        "App\V1Module\Presenters\NotificationsPresenter.actionAll" => new Request(
            "name",
            method: "GET",
            params: ["action" => "all",],
            post: [],
        ),
        "App\V1Module\Presenters\NotificationsPresenter.actionCreate" => new Request(
            "name",
            method: "POST",
            params: ["action" => "create",],
            post: [ "groupsIds" => [], "visibleFrom" => 1740135333, "visibleTo" => 1740135333, "role" => "text", "type" => "text", "localizedTexts" => [],],
        ),
        "App\V1Module\Presenters\NotificationsPresenter.actionUpdate" => new Request(
            "name",
            method: "POST",
            params: ["action" => "update", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "groupsIds" => [], "visibleFrom" => 1740135333, "visibleTo" => 1740135333, "role" => "text", "type" => "text", "localizedTexts" => [],],
        ),
        "App\V1Module\Presenters\NotificationsPresenter.actionRemove" => new Request(
            "name",
            method: "DELETE",
            params: ["action" => "remove", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\WorkerFilesPresenter.actionDownloadSubmissionArchive" => new Request(
            "name",
            method: "GET",
            params: ["action" => "downloadSubmissionArchive", "type" => "text", "id" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\WorkerFilesPresenter.actionDownloadSupplementaryFile" => new Request(
            "name",
            method: "GET",
            params: ["action" => "downloadSupplementaryFile", "hash" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\WorkerFilesPresenter.actionUploadResultsFile" => new Request(
            "name",
            method: "PUT",
            params: ["action" => "uploadResultsFile", "type" => "text", "id" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\AsyncJobsPresenter.actionDefault" => new Request(
            "name",
            method: "GET",
            params: ["action" => "default", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AsyncJobsPresenter.actionList" => new Request(
            "name",
            method: "GET",
            params: ["action" => "list", "ageThreshold" => "0", "includeScheduled" => true,],
            post: [],
        ),
        "App\V1Module\Presenters\AsyncJobsPresenter.actionAbort" => new Request(
            "name",
            method: "POST",
            params: ["action" => "abort", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\AsyncJobsPresenter.actionPing" => new Request(
            "name",
            method: "POST",
            params: ["action" => "ping",],
            post: [],
        ),
        "App\V1Module\Presenters\PlagiarismPresenter.actionListBatches" => new Request(
            "name",
            method: "GET",
            params: ["action" => "listBatches", "detectionTool" => "text", "solutionId" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\PlagiarismPresenter.actionBatchDetail" => new Request(
            "name",
            method: "GET",
            params: ["action" => "batchDetail", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [],
        ),
        "App\V1Module\Presenters\PlagiarismPresenter.actionCreateBatch" => new Request(
            "name",
            method: "POST",
            params: ["action" => "createBatch",],
            post: [ "detectionTool" => "text", "detectionToolParams" => "text",],
        ),
        "App\V1Module\Presenters\PlagiarismPresenter.actionUpdateBatch" => new Request(
            "name",
            method: "POST",
            params: ["action" => "updateBatch", "id" => "10000000-2000-4000-8000-160000000000",],
            post: [ "uploadCompleted" => true, "assignments" => [],],
        ),
        "App\V1Module\Presenters\PlagiarismPresenter.actionGetSimilarities" => new Request(
            "name",
            method: "GET",
            params: ["action" => "getSimilarities", "id" => "10000000-2000-4000-8000-160000000000", "solutionId" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\PlagiarismPresenter.actionAddSimilarities" => new Request(
            "name",
            method: "POST",
            params: ["action" => "addSimilarities", "id" => "10000000-2000-4000-8000-160000000000", "solutionId" => "text",],
            post: [ "solutionFileId" => "10000000-2000-4000-8000-160000000000", "fileEntry" => "text", "authorId" => "10000000-2000-4000-8000-160000000000", "similarity" => 0.1, "files" => [],],
        ),
        "App\V1Module\Presenters\ExtensionsPresenter.actionUrl" => new Request(
            "name",
            method: "GET",
            params: ["action" => "url", "extId" => "text", "instanceId" => "text", "locale" => "en", "return" => "text",],
            post: [],
        ),
        "App\V1Module\Presenters\ExtensionsPresenter.actionToken" => new Request(
            "name",
            method: "POST",
            params: ["action" => "token", "extId" => "text",],
            post: [],
        ),
    ];
}
