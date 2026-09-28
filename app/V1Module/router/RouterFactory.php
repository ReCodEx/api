<?php

namespace App\V1Module;

use Nette;
use Nette\Routing\Router;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;
use App\V1Module\Router\GetRoute;
use App\V1Module\Router\PostRoute;
use App\V1Module\Router\PutRoute;
use App\V1Module\Router\DeleteRoute;

/**
 * Router factory for V1 module.
 */
class RouterFactory
{
    use Nette\StaticClass;

    private static $strictMode = false;

    public static function setStrictMode(bool $strict = true): void
    {
        self::$strictMode = $strict;
    }

    /**
     * Create router with all routes for V1 module.
     * @return Router
     */
    public static function createRouter()
    {
        $router = new RouteList("V1");

        $prefix = "v1";
        $router->add(new Route($prefix, "Default:default"));

        $router->add(self::createSecurityRoutes("$prefix/security"));
        $router->add(self::createAuthRoutes("$prefix/login"));
        $router->add(self::createBrokerRoutes("$prefix/broker"));
        $router->add(self::createBrokerReportsRoutes("$prefix/broker-reports"));
        $router->add(self::createCommentsRoutes("$prefix/comments"));
        $router->add(self::createExercisesRoutes("$prefix/exercises"));
        $router->add(self::createAssignmentsRoutes("$prefix/exercise-assignments"));
        $router->add(self::createGroupsRoutes("$prefix/groups"));
        $router->add(self::createGroupInvitationsRoutes("$prefix/group-invitations"));
        $router->add(self::createGroupAttributesRoutes("$prefix/group-attributes"));
        $router->add(self::createInstancesRoutes("$prefix/instances"));
        $router->add(self::createReferenceSolutionsRoutes("$prefix/reference-solutions"));
        $router->add(self::createAssignmentSolutionsRoutes("$prefix/assignment-solutions"));
        $router->add(self::createAssignmentSolversRoutes("$prefix/assignment-solvers"));
        $router->add(self::createSubmissionFailuresRoutes("$prefix/submission-failures"));
        $router->add(self::createUploadedFilesRoutes("$prefix/uploaded-files"));
        $router->add(self::createUsersRoutes("$prefix/users"));
        $router->add(self::createEmailVerificationRoutes("$prefix/email-verification"));
        $router->add(self::createForgottenPasswordRoutes("$prefix/forgotten-password"));
        $router->add(self::createRuntimeEnvironmentsRoutes("$prefix/runtime-environments"));
        $router->add(self::createHardwareGroupsRoutes("$prefix/hardware-groups"));
        $router->add(self::createPipelinesRoutes("$prefix/pipelines"));
        $router->add(self::createSisRouter("$prefix/extensions/sis"));
        $router->add(self::createEmailsRoutes("$prefix/emails"));
        $router->add(self::createShadowAssignmentsRoutes("$prefix/shadow-assignments"));
        $router->add(self::createNotificationsRoutes("$prefix/notifications"));
        $router->add(self::createWorkerFilesRoutes("$prefix/worker-files"));
        $router->add(self::createAsyncJobsRoutes("$prefix/async-jobs"));
        $router->add(self::createPlagiarismRoutes("$prefix/plagiarism"));
        $router->add(self::createExtensionsRoutes("$prefix/extensions"));

        return $router;
    }

    private static function createSecurityRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new PostRoute("$prefix/check", "Security:check"));
        return $router;
    }

    /**
     * Adds all Authentication endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createAuthRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new PostRoute("$prefix", "Login:default"));
        $router->add(new PostRoute("$prefix/refresh", "Login:refresh"));
        $router->add(new PostRoute("$prefix/issue-restricted-token", "Login:issueRestrictedToken"));
        $router->add(new PostRoute("$prefix/takeover/<userId>", "Login:takeOver"));
        $router->add(new PostRoute("$prefix/<authenticatorName>", "Login:external"));
        return $router;
    }

    /**
     * Adds all Broker endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createBrokerRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix/stats", "Broker:stats"));
        $router->add(new PostRoute("$prefix/freeze", "Broker:freeze"));
        $router->add(new PostRoute("$prefix/unfreeze", "Broker:unfreeze"));
        return $router;
    }

    /**
     * Adds all BrokerReports endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createBrokerReportsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new PostRoute("$prefix/error", "BrokerReports:error"));
        $router->add(new PostRoute("$prefix/job-status/<jobId>", "BrokerReports:jobStatus"));
        return $router;
    }

    /**
     * Adds all Comments endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createCommentsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix/<id>", "Comments:default"));
        $router->add(new PostRoute("$prefix/<id>", "Comments:addComment"));
        $router->add(new PostRoute("$prefix/<threadId>/comment/<commentId>/toggle", "Comments:togglePrivate"));
        $router->add(new PostRoute("$prefix/<threadId>/comment/<commentId>/private", "Comments:setPrivate"));
        $router->add(new DeleteRoute("$prefix/<threadId>/comment/<commentId>", "Comments:delete"));
        return $router;
    }

    /**
     * Adds all Exercises endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createExercisesRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix", "Exercises:"));
        $router->add(new PostRoute("$prefix", "Exercises:create"));
        $router->add(new PostRoute("$prefix/list", "Exercises:listByIds"));
        $router->add(new GetRoute("$prefix/authors", "Exercises:authors"));
        $router->add(new GetRoute("$prefix/tags", "Exercises:allTags"));
        $router->add(new GetRoute("$prefix/tags-stats", "Exercises:tagsStats"));
        $router->add(new PostRoute("$prefix/tags/<tag>", "Exercises:tagsUpdateGlobal"));
        $router->add(new DeleteRoute("$prefix/tags/<tag>", "Exercises:tagsRemoveGlobal"));

        $router->add(new GetRoute("$prefix/<id>", "Exercises:detail"));
        $router->add(new DeleteRoute("$prefix/<id>", "Exercises:remove"));
        $router->add(new PostRoute("$prefix/<id>", "Exercises:updateDetail"));
        $router->add(new PostRoute("$prefix/<id>/validate", "Exercises:validate"));
        $router->add(new PostRoute("$prefix/<id>/fork", "Exercises:forkFrom"));
        $router->add(new GetRoute("$prefix/<id>/assignments", "Exercises:assignments"));
        $router->add(new PostRoute("$prefix/<id>/hardware-groups", "Exercises:hardwareGroups"));
        $router->add(new PostRoute("$prefix/<id>/groups/<groupId>", "Exercises:attachGroup"));
        $router->add(new DeleteRoute("$prefix/<id>/groups/<groupId>", "Exercises:detachGroup"));
        $router->add(new PostRoute("$prefix/<id>/tags/<name>", "Exercises:addTag"));
        $router->add(new DeleteRoute("$prefix/<id>/tags/<name>", "Exercises:removeTag"));
        $router->add(new PostRoute("$prefix/<id>/archived", "Exercises:setArchived"));
        $router->add(new PostRoute("$prefix/<id>/author", "Exercises:setAuthor"));
        $router->add(new PostRoute("$prefix/<id>/admins", "Exercises:setAdmins"));
        $router->add(new PostRoute("$prefix/<id>/notification", "Exercises:sendNotification"));

        // exercise files
        $router->add(new GetRoute("$prefix/<id>/files", "ExerciseFiles:getExerciseFiles"));
        $router->add(new PostRoute("$prefix/<id>/files", "ExerciseFiles:uploadExerciseFiles"));
        $router->add(new DeleteRoute("$prefix/<id>/files/<fileId>", "ExerciseFiles:deleteExerciseFile"));
        $router->add(new GetRoute("$prefix/<id>/files/download-archive", "ExerciseFiles:downloadExerciseFilesArchive"));

        // file links
        $router->add(new GetRoute("$prefix/<id>/file-links", "ExerciseFiles:getFileLinks"));
        $router->add(new PostRoute("$prefix/<id>/file-links", "ExerciseFiles:createFileLink"));
        $router->add(new PostRoute("$prefix/<id>/file-links/<linkId>", "ExerciseFiles:updateFileLink"));
        $router->add(new DeleteRoute("$prefix/<id>/file-links/<linkId>", "ExerciseFiles:deleteFileLink"));

        // special download route for file link by its key
        $router->add(
            new GetRoute("$prefix/<id>/file-download/<linkKey>", "UploadedFiles:downloadExerciseFileLinkByKey")
        );

        if (!self::$strictMode) {
            // deprecated routes for supplementary-files (replaced with `files`)
            $router->add(new GetRoute("$prefix/<id>/supplementary-files", "ExerciseFiles:getExerciseFiles"));
            $router->add(new PostRoute("$prefix/<id>/supplementary-files", "ExerciseFiles:uploadExerciseFiles"));
            $router->add(new DeleteRoute(
                "$prefix/<id>/supplementary-files/<fileId>",
                "ExerciseFiles:deleteExerciseFile"
            ));
            $router->add(new GetRoute(
                "$prefix/<id>/supplementary-files/download-archive",
                "ExerciseFiles:downloadExerciseFilesArchive"
            ));

            // deprecated (will be removed with AttachmentFile entity, unified with exercise-files)
            $router->add(new GetRoute("$prefix/<id>/attachment-files", "ExerciseFiles:getAttachmentFiles"));
            $router->add(new PostRoute("$prefix/<id>/attachment-files", "ExerciseFiles:uploadAttachmentFiles"));
            $router->add(
                new DeleteRoute("$prefix/<id>/attachment-files/<fileId>", "ExerciseFiles:deleteAttachmentFile")
            );
            $router->add(new GetRoute(
                "$prefix/<id>/attachment-files/download-archive",
                "ExerciseFiles:downloadAttachmentFilesArchive"
            ));
        }

        $router->add(new GetRoute("$prefix/<id>/tests", "ExercisesConfig:getTests"));
        $router->add(new PostRoute("$prefix/<id>/tests", "ExercisesConfig:setTests"));
        $router->add(new GetRoute("$prefix/<id>/environment-configs", "ExercisesConfig:getEnvironmentConfigs"));
        $router->add(new PostRoute("$prefix/<id>/environment-configs", "ExercisesConfig:updateEnvironmentConfigs"));
        $router->add(new GetRoute("$prefix/<id>/config", "ExercisesConfig:getConfiguration"));
        $router->add(new PostRoute("$prefix/<id>/config", "ExercisesConfig:setConfiguration"));
        $router->add(new PostRoute("$prefix/<id>/config/variables", "ExercisesConfig:getVariablesForExerciseConfig"));
        $router->add(new GetRoute(
            "$prefix/<id>/environment/<runtimeEnvironmentId>/hwgroup/<hwGroupId>/limits",
            "ExercisesConfig:getHardwareGroupLimits"
        ));
        $router->add(new PostRoute(
            "$prefix/<id>/environment/<runtimeEnvironmentId>/hwgroup/<hwGroupId>/limits",
            "ExercisesConfig:setHardwareGroupLimits"
        ));
        $router->add(new DeleteRoute(
            "$prefix/<id>/environment/<runtimeEnvironmentId>/hwgroup/<hwGroupId>/limits",
            "ExercisesConfig:removeHardwareGroupLimits"
        ));
        $router->add(new GetRoute("$prefix/<id>/limits", "ExercisesConfig:getLimits"));
        $router->add(new PostRoute("$prefix/<id>/limits", "ExercisesConfig:setLimits"));
        $router->add(new GetRoute("$prefix/<id>/score-config", "ExercisesConfig:getScoreConfig"));
        $router->add(new PostRoute("$prefix/<id>/score-config", "ExercisesConfig:setScoreConfig"));

        return $router;
    }

    /**
     * Adds all Assignments endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createAssignmentsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new PostRoute("$prefix", "Assignments:create"));
        $router->add(new GetRoute("$prefix/<id>", "Assignments:detail"));
        $router->add(new PostRoute("$prefix/<id>", "Assignments:updateDetail"));
        $router->add(new PostRoute("$prefix/<id>/localized-texts", "Assignments:updateLocalizedTexts"));
        $router->add(new DeleteRoute("$prefix/<id>", "Assignments:remove"));
        $router->add(new GetRoute("$prefix/<id>/solutions", "Assignments:solutions"));
        $router->add(new GetRoute("$prefix/<id>/best-solutions", "Assignments:bestSolutions"));
        $router->add(new GetRoute("$prefix/<id>/download-best-solutions", "Assignments:downloadBestSolutionsArchive"));
        $router->add(new GetRoute("$prefix/<id>/users/<userId>/solutions", "Assignments:userSolutions"));
        $router->add(new GetRoute("$prefix/<id>/users/<userId>/best-solution", "Assignments:bestSolution"));
        $router->add(new PostRoute("$prefix/<id>/validate", "Assignments:validate"));
        $router->add(new PostRoute("$prefix/<id>/sync-exercise", "Assignments:syncWithExercise"));

        $router->add(new GetRoute("$prefix/<id>/can-submit", "Submit:canSubmit"));
        $router->add(new PostRoute("$prefix/<id>/submit", "Submit:submit"));
        $router->add(new GetRoute("$prefix/<id>/resubmit-all", "Submit:resubmitAllAsyncJobStatus"));
        $router->add(new PostRoute("$prefix/<id>/resubmit-all", "Submit:resubmitAll"));
        $router->add(new PostRoute("$prefix/<id>/pre-submit", "Submit:preSubmit"));

        $router->add(new GetRoute("$prefix/<id>/async-jobs", "AsyncJobs:assignmentJobs"));
        return $router;
    }

    /**
     * Adds all Groups endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createGroupsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();

        $router->add(new GetRoute("$prefix", "Groups:"));
        $router->add(new PostRoute("$prefix", "Groups:addGroup"));
        $router->add(new PostRoute("$prefix/validate-add-group-data", "Groups:validateAddGroupData"));
        $router->add(new GetRoute("$prefix/<id>", "Groups:detail"));
        $router->add(new PostRoute("$prefix/<id>", "Groups:updateGroup"));
        $router->add(new DeleteRoute("$prefix/<id>", "Groups:removeGroup"));
        $router->add(new GetRoute("$prefix/<id>/subgroups", "Groups:subgroups"));

        $router->add(new PostRoute("$prefix/<id>/organizational", "Groups:setOrganizational"));
        $router->add(new PostRoute("$prefix/<id>/archived", "Groups:setArchived"));
        $router->add(new PostRoute("$prefix/<id>/exam", "Groups:setExam"));
        $router->add(new PostRoute("$prefix/<id>/examPeriod", "Groups:setExamPeriod"));
        $router->add(new DeleteRoute("$prefix/<id>/examPeriod", "Groups:removeExamPeriod"));
        $router->add(new GetRoute("$prefix/<id>/exam/<examId>", "Groups:getExamLocks"));
        $router->add(new PostRoute("$prefix/<id>/relocate/<newParentId>", "Groups:relocate"));

        $router->add(new GetRoute("$prefix/<id>/students/stats", "Groups:stats"));
        $router->add(new GetRoute("$prefix/<id>/students/<userId>", "Groups:studentsStats"));
        $router->add(new GetRoute("$prefix/<id>/students/<userId>/solutions", "Groups:studentsSolutions"));
        $router->add(new PostRoute("$prefix/<id>/students/<userId>", "Groups:addStudent"));
        $router->add(new DeleteRoute("$prefix/<id>/students/<userId>", "Groups:removeStudent"));
        $router->add(new PostRoute("$prefix/<id>/lock/<userId>", "Groups:lockStudent"));
        $router->add(new DeleteRoute("$prefix/<id>/lock/<userId>", "Groups:unlockStudent"));

        // members = all other types of memberships except students
        $router->add(new GetRoute("$prefix/<id>/members", "Groups:members"));
        $router->add(new PostRoute("$prefix/<id>/members/<userId>", "Groups:addMember"));
        $router->add(new DeleteRoute("$prefix/<id>/members/<userId>", "Groups:removeMember"));

        $router->add(new GetRoute("$prefix/<id>/assignments", "Groups:assignments"));
        $router->add(new GetRoute("$prefix/<id>/shadow-assignments", "Groups:shadowAssignments"));

        // invitations (which cannot be in invitations route)
        $router->add(new GetRoute("$prefix/<groupId>/invitations", "GroupInvitations:list"));
        $router->add(new PostRoute("$prefix/<groupId>/invitations", "GroupInvitations:create"));

        return $router;
    }

    /**
     * Adds all group invitations endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createGroupInvitationsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();

        $router->add(new GetRoute("$prefix/<id>", "GroupInvitations:"));
        $router->add(new PostRoute("$prefix/<id>", "GroupInvitations:update"));
        $router->add(new DeleteRoute("$prefix/<id>", "GroupInvitations:remove"));
        $router->add(new PostRoute("$prefix/<id>/accept", "GroupInvitations:accept"));
        return $router;
    }

    /**
     * Adds all group external attributes endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createGroupAttributesRoutes(string $prefix): RouteList
    {
        $router = new RouteList();

        $router->add(new GetRoute($prefix, "GroupExternalAttributes:"));
        $router->add(new GetRoute("$prefix/<groupId>", "GroupExternalAttributes:get"));
        $router->add(new PostRoute("$prefix/<groupId>", "GroupExternalAttributes:add"));
        $router->add(new DeleteRoute("$prefix/<groupId>", "GroupExternalAttributes:remove"));
        return $router;
    }

    /**
     * Adds all Instances endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createInstancesRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix", "Instances:"));
        $router->add(new PostRoute("$prefix", "Instances:createInstance"));
        $router->add(new GetRoute("$prefix/<id>", "Instances:detail"));
        $router->add(new PostRoute("$prefix/<id>", "Instances:updateInstance"));
        $router->add(new DeleteRoute("$prefix/<id>", "Instances:deleteInstance"));
        $router->add(new GetRoute("$prefix/<id>/licences", "Instances:licences"));
        $router->add(new PostRoute("$prefix/<id>/licences", "Instances:createLicence"));
        $router->add(new PostRoute("$prefix/licences/<licenceId>", "Instances:updateLicence"));
        $router->add(new DeleteRoute("$prefix/licences/<licenceId>", "Instances:deleteLicence"));
        return $router;
    }

    /**
     * Adds all ReferenceSolutions endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createReferenceSolutionsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix/exercise/<exerciseId>", "ReferenceExerciseSolutions:solutions"));
        $router->add(new PostRoute("$prefix/exercise/<exerciseId>/pre-submit", "ReferenceExerciseSolutions:preSubmit"));
        $router->add(new PostRoute("$prefix/exercise/<exerciseId>/submit", "ReferenceExerciseSolutions:submit"));
        $router->add(new PostRoute(
            "$prefix/exercise/<exerciseId>/resubmit-all",
            "ReferenceExerciseSolutions:resubmitAll"
        ));

        $router->add(new GetRoute("$prefix/<solutionId>", "ReferenceExerciseSolutions:detail"));
        $router->add(new PostRoute("$prefix/<solutionId>", "ReferenceExerciseSolutions:update"));
        $router->add(new DeleteRoute("$prefix/<solutionId>", "ReferenceExerciseSolutions:deleteReferenceSolution"));
        $router->add(new PostRoute("$prefix/<id>/resubmit", "ReferenceExerciseSolutions:resubmit"));
        $router->add(new GetRoute("$prefix/<solutionId>/submissions", "ReferenceExerciseSolutions:submissions"));
        $router->add(new GetRoute("$prefix/<id>/files", "ReferenceExerciseSolutions:files"));
        $router->add(new GetRoute(
            "$prefix/<solutionId>/download-solution",
            "ReferenceExerciseSolutions:downloadSolutionArchive"
        ));
        $router->add(new PostRoute("$prefix/<solutionId>/visibility", "ReferenceExerciseSolutions:setVisibility"));

        $router->add(new GetRoute("$prefix/submission/<submissionId>", "ReferenceExerciseSolutions:submission"));
        $router->add(
            new DeleteRoute("$prefix/submission/<submissionId>", "ReferenceExerciseSolutions:deleteSubmission")
        );
        $router->add(new GetRoute(
            "$prefix/submission/<submissionId>/download-result",
            "ReferenceExerciseSolutions:downloadResultArchive"
        ));
        $router->add(new GetRoute(
            "$prefix/submission/<submissionId>/score-config",
            "ReferenceExerciseSolutions:evaluationScoreConfig"
        ));

        return $router;
    }

    /**
     * Adds all AssignmentSolution endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createAssignmentSolutionsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix/<id>", "AssignmentSolutions:solution"));
        $router->add(new PostRoute("$prefix/<id>", "AssignmentSolutions:updateSolution"));
        $router->add(new DeleteRoute("$prefix/<id>", "AssignmentSolutions:deleteSolution"));
        $router->add(new PostRoute("$prefix/<id>/bonus-points", "AssignmentSolutions:setBonusPoints"));
        $router->add(new GetRoute("$prefix/<id>/submissions", "AssignmentSolutions:submissions"));
        $router->add(new PostRoute("$prefix/<id>/set-flag/<flag>", "AssignmentSolutions:setFlag"));
        $router->add(new PostRoute("$prefix/<id>/resubmit", "Submit:resubmit"));
        $router->add(new GetRoute("$prefix/<id>/files", "AssignmentSolutions:files"));
        $router->add(new GetRoute("$prefix/<id>/download-solution", "AssignmentSolutions:downloadSolutionArchive"));

        $router->add(new GetRoute("$prefix/submission/<submissionId>", "AssignmentSolutions:submission"));
        $router->add(new DeleteRoute("$prefix/submission/<submissionId>", "AssignmentSolutions:deleteSubmission"));
        $router->add(new GetRoute(
            "$prefix/submission/<submissionId>/download-result",
            "AssignmentSolutions:downloadResultArchive"
        ));
        $router->add(new GetRoute(
            "$prefix/submission/<submissionId>/score-config",
            "AssignmentSolutions:evaluationScoreConfig"
        ));

        $router->add(new GetRoute("$prefix/<id>/review", "AssignmentSolutionReviews:"));
        $router->add(new PostRoute("$prefix/<id>/review", "AssignmentSolutionReviews:update"));
        $router->add(new DeleteRoute("$prefix/<id>/review", "AssignmentSolutionReviews:remove"));
        $router->add(new PostRoute("$prefix/<id>/review-comment", "AssignmentSolutionReviews:newComment"));
        $router->add(new PostRoute("$prefix/<id>/review-comment/<commentId>", "AssignmentSolutionReviews:editComment"));
        $router->add(new DeleteRoute(
            "$prefix/<id>/review-comment/<commentId>",
            "AssignmentSolutionReviews:deleteComment"
        ));

        return $router;
    }

    private static function createAssignmentSolversRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix", "AssignmentSolvers:"));
        return $router;
    }

    /**
     * Adds all Submission failures endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createSubmissionFailuresRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix", "SubmissionFailures:"));
        $router->add(new GetRoute("$prefix/unresolved", "SubmissionFailures:unresolved"));
        $router->add(new GetRoute("$prefix/<id>", "SubmissionFailures:detail"));
        $router->add(new PostRoute("$prefix/<id>/resolve", "SubmissionFailures:resolve"));
        return $router;
    }

    /**
     * Adds all UploadedFiles endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createUploadedFilesRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new PostRoute("$prefix/partial", "UploadedFiles:startPartial"));
        $router->add(new PutRoute("$prefix/partial/<id>", "UploadedFiles:appendPartial"));
        $router->add(new DeleteRoute("$prefix/partial/<id>", "UploadedFiles:cancelPartial"));
        $router->add(new PostRoute("$prefix/partial/<id>", "UploadedFiles:completePartial"));

        $router->add(new GetRoute("$prefix/link/<id>", "UploadedFiles:downloadExerciseFileByLink"));

        $router->add(new PostRoute("$prefix", "UploadedFiles:upload"));
        $router->add(new GetRoute("$prefix/<id>", "UploadedFiles:detail"));
        $router->add(new GetRoute("$prefix/<id>/download", "UploadedFiles:download"));
        $router->add(new GetRoute("$prefix/<id>/content", "UploadedFiles:content"));
        $router->add(new GetRoute("$prefix/<id>/digest", "UploadedFiles:digest"));

        if (!self::$strictMode) {
            // deprecated (should be handled by generic download)
            $router->add(
                new GetRoute("$prefix/supplementary-file/<id>/download", "UploadedFiles:downloadExerciseFile")
            );
        }
        return $router;
    }

    /**
     * Adds all Users endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createUsersRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix", "Users:"));
        $router->add(new PostRoute("$prefix", "Registration:createAccount"));
        $router->add(new PostRoute("$prefix/validate-registration-data", "Registration:validateRegistrationData"));
        $router->add(new PostRoute("$prefix/list", "Users:listByIds"));
        $router->add(new GetRoute("$prefix/ical/<id>", "UserCalendars:"));
        $router->add(new DeleteRoute("$prefix/ical/<id>", "UserCalendars:expireCalendar"));
        $router->add(new PostRoute("$prefix/invite", "Registration:createInvitation"));
        $router->add(new PostRoute("$prefix/accept-invitation", "Registration:acceptInvitation"));

        $router->add(new GetRoute("$prefix/<id>", "Users:detail"));
        $router->add(new GetRoute("$prefix/external-login/<service>/<externalId>", "Users:findByExternalLogin"));
        $router->add(new PostRoute("$prefix/<id>/invalidate-tokens", "Users:invalidateTokens"));
        $router->add(new DeleteRoute("$prefix/<id>", "Users:delete"));
        $router->add(new GetRoute("$prefix/<id>/groups", "Users:groups"));
        $router->add(new GetRoute("$prefix/<id>/groups/all", "Users:allGroups"));
        $router->add(new GetRoute("$prefix/<id>/instances", "Users:instances"));
        $router->add(new PostRoute("$prefix/<id>", "Users:updateProfile"));
        $router->add(new PostRoute("$prefix/<id>/settings", "Users:updateSettings"));
        $router->add(new PostRoute("$prefix/<id>/ui-data", "Users:updateUiData"));
        $router->add(new PostRoute("$prefix/<id>/create-local", "Users:createLocalAccount"));
        $router->add(new PostRoute("$prefix/<id>/role", "Users:setRole"));
        $router->add(new PostRoute("$prefix/<id>/allowed", "Users:setAllowed"));
        $router->add(new PostRoute("$prefix/<id>/external-login/<service>", "Users:updateExternalLogin"));
        $router->add(new DeleteRoute("$prefix/<id>/external-login/<service>", "Users:removeExternalLogin"));
        $router->add(new GetRoute("$prefix/<id>/calendar-tokens", "UserCalendars:userCalendars"));
        $router->add(new PostRoute("$prefix/<id>/calendar-tokens", "UserCalendars:createCalendar"));
        $router->add(new GetRoute("$prefix/<id>/pending-reviews", "AssignmentSolutionReviews:pending"));
        $router->add(new GetRoute("$prefix/<id>/review-requests", "AssignmentSolutions:reviewRequests"));
        return $router;
    }

    /**
     * All endpoints for email addresses verification.
     * @param string $prefix
     * @return RouteList
     */
    private static function createEmailVerificationRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new PostRoute("$prefix/verify", "EmailVerification:emailVerification"));
        $router->add(new PostRoute("$prefix/resend", "EmailVerification:resendVerificationEmail"));
        return $router;
    }

    /**
     * Adds all ForgottenPassword endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createForgottenPasswordRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new PostRoute("$prefix", "ForgottenPassword:"));
        $router->add(new PostRoute("$prefix/change", "ForgottenPassword:change"));
        $router->add(new PostRoute("$prefix/validate-password-strength", "ForgottenPassword:validatePasswordStrength"));
        return $router;
    }

    /**
     * Adds all RuntimeEnvironment endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createRuntimeEnvironmentsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix", "RuntimeEnvironments:"));
        return $router;
    }

    /**
     * Adds all HardwareGroups endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createHardwareGroupsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix", "HardwareGroups:"));
        return $router;
    }

    /**
     * Adds all Pipelines endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createPipelinesRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix", "Pipelines:"));
        $router->add(new PostRoute("$prefix", "Pipelines:createPipeline"));
        $router->add(new GetRoute("$prefix/boxes", "Pipelines:getDefaultBoxes"));
        $router->add(new PostRoute("$prefix/<id>/fork", "Pipelines:forkPipeline"));
        $router->add(new GetRoute("$prefix/<id>", "Pipelines:getPipeline"));
        $router->add(new PostRoute("$prefix/<id>", "Pipelines:updatePipeline"));
        $router->add(new DeleteRoute("$prefix/<id>", "Pipelines:removePipeline"));
        $router->add(new PostRoute("$prefix/<id>/runtime-environments", "Pipelines:updateRuntimeEnvironments"));
        $router->add(new PostRoute("$prefix/<id>/validate", "Pipelines:validatePipeline"));
        $router->add(new GetRoute("$prefix/<id>/exercise-files", "Pipelines:getExerciseFiles"));
        $router->add(new PostRoute("$prefix/<id>/exercise-files", "Pipelines:uploadExerciseFiles"));
        $router->add(new DeleteRoute("$prefix/<id>/exercise-files/<fileId>", "Pipelines:deleteExerciseFile"));
        $router->add(new GetRoute("$prefix/<id>/exercises", "Pipelines:getPipelineExercises"));

        if (!self::$strictMode) {
            // deprecated routes for supplementary files
            $router->add(new GetRoute("$prefix/<id>/supplementary-files", "Pipelines:getExerciseFiles"));
            $router->add(new PostRoute("$prefix/<id>/supplementary-files", "Pipelines:uploadExerciseFiles"));
            $router->add(new DeleteRoute("$prefix/<id>/supplementary-files/<fileId>", "Pipelines:deleteExerciseFile"));
        }
        return $router;
    }

    private static function createSisRouter(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix/status/", "Sis:status"));
        $router->add(new GetRoute("$prefix/terms/", "Sis:getTerms"));
        $router->add(new PostRoute("$prefix/terms/", "Sis:registerTerm"));
        $router->add(new PostRoute("$prefix/terms/<id>", "Sis:editTerm"));
        $router->add(new DeleteRoute("$prefix/terms/<id>", "Sis:deleteTerm"));
        $router->add(new GetRoute(
            "$prefix/users/<userId>/subscribed-groups/<year>/<term>/as-student",
            "Sis:subscribedCourses"
        ));
        $router->add(new GetRoute("$prefix/users/<userId>/supervised-courses/<year>/<term>", "Sis:supervisedCourses"));
        $router->add(new GetRoute("$prefix/remote-courses/<courseId>/possible-parents", "Sis:possibleParents"));
        $router->add(new PostRoute("$prefix/remote-courses/<courseId>/create", "Sis:createGroup"));
        $router->add(new PostRoute("$prefix/remote-courses/<courseId>/bind", "Sis:bindGroup"));
        $router->add(new DeleteRoute("$prefix/remote-courses/<courseId>/bindings/<groupId>", "Sis:unbindGroup"));
        return $router;
    }

    private static function createEmailsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new PostRoute("$prefix", "Emails:default"));
        $router->add(new PostRoute("$prefix/supervisors", "Emails:sendToSupervisors"));
        $router->add(new PostRoute("$prefix/regular-users", "Emails:sendToRegularUsers"));
        $router->add(new PostRoute("$prefix/groups/<groupId>", "Emails:sendToGroupMembers"));
        return $router;
    }

    /**
     * Adds all ShadowAssignments endpoints to given router.
     * @param string $prefix Route prefix
     * @return RouteList All endpoint routes
     */
    private static function createShadowAssignmentsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix/<id>", "ShadowAssignments:detail"));
        $router->add(new PostRoute("$prefix/<id>", "ShadowAssignments:updateDetail"));
        $router->add(new PostRoute("$prefix", "ShadowAssignments:create"));
        $router->add(new DeleteRoute("$prefix/<id>", "ShadowAssignments:remove"));
        $router->add(new PostRoute("$prefix/<id>/validate", "ShadowAssignments:validate"));
        $router->add(new PostRoute("$prefix/<id>/create-points", "ShadowAssignments:createPoints"));
        $router->add(new PostRoute("$prefix/points/<pointsId>", "ShadowAssignments:updatePoints"));
        $router->add(new DeleteRoute("$prefix/points/<pointsId>", "ShadowAssignments:removePoints"));
        return $router;
    }

    private static function createNotificationsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix", "Notifications:default"));
        $router->add(new GetRoute("$prefix/all", "Notifications:all"));
        $router->add(new PostRoute("$prefix", "Notifications:create"));
        $router->add(new PostRoute("$prefix/<id>", "Notifications:update"));
        $router->add(new DeleteRoute("$prefix/<id>", "Notifications:remove"));
        return $router;
    }

    private static function createWorkerFilesRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix/submission-archive/<type>/<id>", "WorkerFiles:downloadSubmissionArchive"));
        $router->add(new GetRoute("$prefix/exercise-file/<hash>", "WorkerFiles:downloadExerciseFile"));
        $router->add(new PutRoute("$prefix/result/<type>/<id>", "WorkerFiles:uploadResultsFile"));

        if (!self::$strictMode) {
            // deprecated route for supplementary files
            $router->add(new GetRoute("$prefix/supplementary-file/<hash>", "WorkerFiles:downloadExerciseFile"));
        }
        return $router;
    }

    private static function createAsyncJobsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix/<id>", "AsyncJobs:default"));
        $router->add(new GetRoute("$prefix", "AsyncJobs:list"));
        $router->add(new PostRoute("$prefix/<id>/abort", "AsyncJobs:abort"));
        $router->add(new PostRoute("$prefix/ping", "AsyncJobs:ping"));
        return $router;
    }

    private static function createPlagiarismRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix", "Plagiarism:listBatches"));
        $router->add(new GetRoute("$prefix/<id>", "Plagiarism:batchDetail"));
        $router->add(new PostRoute("$prefix", "Plagiarism:createBatch"));
        $router->add(new PostRoute("$prefix/<id>", "Plagiarism:updateBatch"));
        $router->add(new GetRoute("$prefix/<id>/<solutionId>", "Plagiarism:getSimilarities"));
        $router->add(new PostRoute("$prefix/<id>/<solutionId>", "Plagiarism:addSimilarities"));
        return $router;
    }

    private static function createExtensionsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router->add(new GetRoute("$prefix/<extId>/<instanceId>", "Extensions:url"));
        $router->add(new PostRoute("$prefix/<extId>", "Extensions:token"));
        return $router;
    }
}
