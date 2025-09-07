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

    /**
     * Create router with all routes for V1 module.
     * @return Router
     */
    public static function createRouter()
    {
        $router = new RouteList("V1");

        $prefix = "v1";
        $router[] = new Route($prefix, "Default:default");

        $router[] = self::createSecurityRoutes("$prefix/security");
        $router[] = self::createAuthRoutes("$prefix/login");
        $router[] = self::createBrokerRoutes("$prefix/broker");
        $router[] = self::createBrokerReportsRoutes("$prefix/broker-reports");
        $router[] = self::createCommentsRoutes("$prefix/comments");
        $router[] = self::createExercisesRoutes("$prefix/exercises");
        $router[] = self::createAssignmentsRoutes("$prefix/exercise-assignments");
        $router[] = self::createGroupsRoutes("$prefix/groups");
        $router[] = self::createGroupInvitationsRoutes("$prefix/group-invitations");
        $router[] = self::createGroupAttributesRoutes("$prefix/group-attributes");
        $router[] = self::createInstancesRoutes("$prefix/instances");
        $router[] = self::createReferenceSolutionsRoutes("$prefix/reference-solutions");
        $router[] = self::createAssignmentSolutionsRoutes("$prefix/assignment-solutions");
        $router[] = self::createAssignmentSolversRoutes("$prefix/assignment-solvers");
        $router[] = self::createSubmissionFailuresRoutes("$prefix/submission-failures");
        $router[] = self::createUploadedFilesRoutes("$prefix/uploaded-files");
        $router[] = self::createUsersRoutes("$prefix/users");
        $router[] = self::createEmailVerificationRoutes("$prefix/email-verification");
        $router[] = self::createForgottenPasswordRoutes("$prefix/forgotten-password");
        $router[] = self::createRuntimeEnvironmentsRoutes("$prefix/runtime-environments");
        $router[] = self::createHardwareGroupsRoutes("$prefix/hardware-groups");
        $router[] = self::createPipelinesRoutes("$prefix/pipelines");
        $router[] = self::createSisRouter("$prefix/extensions/sis");
        $router[] = self::createEmailsRoutes("$prefix/emails");
        $router[] = self::createShadowAssignmentsRoutes("$prefix/shadow-assignments");
        $router[] = self::createNotificationsRoutes("$prefix/notifications");
        $router[] = self::createWorkerFilesRoutes("$prefix/worker-files");
        $router[] = self::createAsyncJobsRoutes("$prefix/async-jobs");
        $router[] = self::createPlagiarismRoutes("$prefix/plagiarism");
        $router[] = self::createExtensionsRoutes("$prefix/extensions");

        return $router;
    }

    private static function createSecurityRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router[] = new PostRoute("$prefix/check", "Security:check");
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
        $router[] = new PostRoute("$prefix", "Login:default");
        $router[] = new PostRoute("$prefix/refresh", "Login:refresh");
        $router[] = new PostRoute("$prefix/issue-restricted-token", "Login:issueRestrictedToken");
        $router[] = new PostRoute("$prefix/takeover/<userId>", "Login:takeOver");
        $router[] = new PostRoute("$prefix/<authenticatorName>", "Login:external");
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
        $router[] = new GetRoute("$prefix/stats", "Broker:stats");
        $router[] = new PostRoute("$prefix/freeze", "Broker:freeze");
        $router[] = new PostRoute("$prefix/unfreeze", "Broker:unfreeze");
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
        $router[] = new PostRoute("$prefix/error", "BrokerReports:error");
        $router[] = new PostRoute("$prefix/job-status/<jobId>", "BrokerReports:jobStatus");
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
        $router[] = new GetRoute("$prefix/<id>", "Comments:default");
        $router[] = new PostRoute("$prefix/<id>", "Comments:addComment");
        $router[] = new PostRoute("$prefix/<threadId>/comment/<commentId>/toggle", "Comments:togglePrivate");
        $router[] = new PostRoute("$prefix/<threadId>/comment/<commentId>/private", "Comments:setPrivate");
        $router[] = new DeleteRoute("$prefix/<threadId>/comment/<commentId>", "Comments:delete");
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
        $router[] = new GetRoute("$prefix", "Exercises:");
        $router[] = new PostRoute("$prefix", "Exercises:create");
        $router[] = new PostRoute("$prefix/list", "Exercises:listByIds");
        $router[] = new GetRoute("$prefix/authors", "Exercises:authors");
        $router[] = new GetRoute("$prefix/tags", "Exercises:allTags");
        $router[] = new GetRoute("$prefix/tags-stats", "Exercises:tagsStats");
        $router[] = new PostRoute("$prefix/tags/<tag>", "Exercises:tagsUpdateGlobal");
        $router[] = new DeleteRoute("$prefix/tags/<tag>", "Exercises:tagsRemoveGlobal");

        $router[] = new GetRoute("$prefix/<id>", "Exercises:detail");
        $router[] = new DeleteRoute("$prefix/<id>", "Exercises:remove");
        $router[] = new PostRoute("$prefix/<id>", "Exercises:updateDetail");
        $router[] = new PostRoute("$prefix/<id>/validate", "Exercises:validate");
        $router[] = new PostRoute("$prefix/<id>/fork", "Exercises:forkFrom");
        $router[] = new GetRoute("$prefix/<id>/assignments", "Exercises:assignments");
        $router[] = new PostRoute("$prefix/<id>/hardware-groups", "Exercises:hardwareGroups");
        $router[] = new PostRoute("$prefix/<id>/groups/<groupId>", "Exercises:attachGroup");
        $router[] = new DeleteRoute("$prefix/<id>/groups/<groupId>", "Exercises:detachGroup");
        $router[] = new PostRoute("$prefix/<id>/tags/<name>", "Exercises:addTag");
        $router[] = new DeleteRoute("$prefix/<id>/tags/<name>", "Exercises:removeTag");
        $router[] = new PostRoute("$prefix/<id>/archived", "Exercises:setArchived");
        $router[] = new PostRoute("$prefix/<id>/author", "Exercises:setAuthor");
        $router[] = new PostRoute("$prefix/<id>/admins", "Exercises:setAdmins");
        $router[] = new PostRoute("$prefix/<id>/notification", "Exercises:sendNotification");

        $router[] = new GetRoute("$prefix/<id>/supplementary-files", "ExerciseFiles:getSupplementaryFiles");
        $router[] = new PostRoute("$prefix/<id>/supplementary-files", "ExerciseFiles:uploadSupplementaryFiles");
        $router[] = new DeleteRoute(
            "$prefix/<id>/supplementary-files/<fileId>",
            "ExerciseFiles:deleteSupplementaryFile"
        );
        $router[] = new GetRoute(
            "$prefix/<id>/supplementary-files/download-archive",
            "ExerciseFiles:downloadSupplementaryFilesArchive"
        );
        $router[] = new GetRoute("$prefix/<id>/attachment-files", "ExerciseFiles:getAttachmentFiles");
        $router[] = new PostRoute("$prefix/<id>/attachment-files", "ExerciseFiles:uploadAttachmentFiles");
        $router[] = new DeleteRoute("$prefix/<id>/attachment-files/<fileId>", "ExerciseFiles:deleteAttachmentFile");
        $router[] = new GetRoute(
            "$prefix/<id>/attachment-files/download-archive",
            "ExerciseFiles:downloadAttachmentFilesArchive"
        );

        $router[] = new GetRoute("$prefix/<id>/tests", "ExercisesConfig:getTests");
        $router[] = new PostRoute("$prefix/<id>/tests", "ExercisesConfig:setTests");
        $router[] = new GetRoute("$prefix/<id>/environment-configs", "ExercisesConfig:getEnvironmentConfigs");
        $router[] = new PostRoute("$prefix/<id>/environment-configs", "ExercisesConfig:updateEnvironmentConfigs");
        $router[] = new GetRoute("$prefix/<id>/config", "ExercisesConfig:getConfiguration");
        $router[] = new PostRoute("$prefix/<id>/config", "ExercisesConfig:setConfiguration");
        $router[] = new PostRoute("$prefix/<id>/config/variables", "ExercisesConfig:getVariablesForExerciseConfig");
        $router[] = new GetRoute(
            "$prefix/<id>/environment/<runtimeEnvironmentId>/hwgroup/<hwGroupId>/limits",
            "ExercisesConfig:getHardwareGroupLimits"
        );
        $router[] = new PostRoute(
            "$prefix/<id>/environment/<runtimeEnvironmentId>/hwgroup/<hwGroupId>/limits",
            "ExercisesConfig:setHardwareGroupLimits"
        );
        $router[] = new DeleteRoute(
            "$prefix/<id>/environment/<runtimeEnvironmentId>/hwgroup/<hwGroupId>/limits",
            "ExercisesConfig:removeHardwareGroupLimits"
        );
        $router[] = new GetRoute("$prefix/<id>/limits", "ExercisesConfig:getLimits");
        $router[] = new PostRoute("$prefix/<id>/limits", "ExercisesConfig:setLimits");
        $router[] = new GetRoute("$prefix/<id>/score-config", "ExercisesConfig:getScoreConfig");
        $router[] = new PostRoute("$prefix/<id>/score-config", "ExercisesConfig:setScoreConfig");

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
        $router[] = new PostRoute("$prefix", "Assignments:create");
        $router[] = new GetRoute("$prefix/<id>", "Assignments:detail");
        $router[] = new PostRoute("$prefix/<id>", "Assignments:updateDetail");
        $router[] = new DeleteRoute("$prefix/<id>", "Assignments:remove");
        $router[] = new GetRoute("$prefix/<id>/solutions", "Assignments:solutions");
        $router[] = new GetRoute("$prefix/<id>/best-solutions", "Assignments:bestSolutions");
        $router[] = new GetRoute("$prefix/<id>/download-best-solutions", "Assignments:downloadBestSolutionsArchive");
        $router[] = new GetRoute("$prefix/<id>/users/<userId>/solutions", "Assignments:userSolutions");
        $router[] = new GetRoute("$prefix/<id>/users/<userId>/best-solution", "Assignments:bestSolution");
        $router[] = new PostRoute("$prefix/<id>/validate", "Assignments:validate");
        $router[] = new PostRoute("$prefix/<id>/sync-exercise", "Assignments:syncWithExercise");

        $router[] = new GetRoute("$prefix/<id>/can-submit", "Submit:canSubmit");
        $router[] = new PostRoute("$prefix/<id>/submit", "Submit:submit");
        $router[] = new GetRoute("$prefix/<id>/resubmit-all", "Submit:resubmitAllAsyncJobStatus");
        $router[] = new PostRoute("$prefix/<id>/resubmit-all", "Submit:resubmitAll");
        $router[] = new PostRoute("$prefix/<id>/pre-submit", "Submit:preSubmit");

        $router[] = new GetRoute("$prefix/<id>/async-jobs", "AsyncJobs:assignmentJobs");
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

        $router[] = new GetRoute("$prefix", "Groups:");
        $router[] = new PostRoute("$prefix", "Groups:addGroup");
        $router[] = new PostRoute("$prefix/validate-add-group-data", "Groups:validateAddGroupData");
        $router[] = new GetRoute("$prefix/<id>", "Groups:detail");
        $router[] = new PostRoute("$prefix/<id>", "Groups:updateGroup");
        $router[] = new DeleteRoute("$prefix/<id>", "Groups:removeGroup");
        $router[] = new GetRoute("$prefix/<id>/subgroups", "Groups:subgroups");

        $router[] = new PostRoute("$prefix/<id>/organizational", "Groups:setOrganizational");
        $router[] = new PostRoute("$prefix/<id>/archived", "Groups:setArchived");
        $router[] = new PostRoute("$prefix/<id>/exam", "Groups:setExam");
        $router[] = new PostRoute("$prefix/<id>/examPeriod", "Groups:setExamPeriod");
        $router[] = new DeleteRoute("$prefix/<id>/examPeriod", "Groups:removeExamPeriod");
        $router[] = new GetRoute("$prefix/<id>/exam/<examId>", "Groups:getExamLocks");
        $router[] = new PostRoute("$prefix/<id>/relocate/<newParentId>", "Groups:relocate");

        $router[] = new GetRoute("$prefix/<id>/students/stats", "Groups:stats");
        $router[] = new GetRoute("$prefix/<id>/students/<userId>", "Groups:studentsStats");
        $router[] = new GetRoute("$prefix/<id>/students/<userId>/solutions", "Groups:studentsSolutions");
        $router[] = new PostRoute("$prefix/<id>/students/<userId>", "Groups:addStudent");
        $router[] = new DeleteRoute("$prefix/<id>/students/<userId>", "Groups:removeStudent");
        $router[] = new PostRoute("$prefix/<id>/lock/<userId>", "Groups:lockStudent");
        $router[] = new DeleteRoute("$prefix/<id>/lock/<userId>", "Groups:unlockStudent");

        // members = all other types of memberships except students
        $router[] = new GetRoute("$prefix/<id>/members", "Groups:members");
        $router[] = new PostRoute("$prefix/<id>/members/<userId>", "Groups:addMember");
        $router[] = new DeleteRoute("$prefix/<id>/members/<userId>", "Groups:removeMember");

        $router[] = new GetRoute("$prefix/<id>/assignments", "Groups:assignments");
        $router[] = new GetRoute("$prefix/<id>/shadow-assignments", "Groups:shadowAssignments");

        // invitations (which cannot be in invitations route)
        $router[] = new GetRoute("$prefix/<groupId>/invitations", "GroupInvitations:list");
        $router[] = new PostRoute("$prefix/<groupId>/invitations", "GroupInvitations:create");

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

        $router[] = new GetRoute("$prefix/<id>", "GroupInvitations:");
        $router[] = new PostRoute("$prefix/<id>", "GroupInvitations:update");
        $router[] = new DeleteRoute("$prefix/<id>", "GroupInvitations:remove");
        $router[] = new PostRoute("$prefix/<id>/accept", "GroupInvitations:accept");
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

        $router[] = new GetRoute($prefix, "GroupExternalAttributes:");
        $router[] = new PostRoute("$prefix/<groupId>", "GroupExternalAttributes:add");
        $router[] = new DeleteRoute("$prefix/<groupId>", "GroupExternalAttributes:remove");
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
        $router[] = new GetRoute("$prefix", "Instances:");
        $router[] = new PostRoute("$prefix", "Instances:createInstance");
        $router[] = new GetRoute("$prefix/<id>", "Instances:detail");
        $router[] = new PostRoute("$prefix/<id>", "Instances:updateInstance");
        $router[] = new DeleteRoute("$prefix/<id>", "Instances:deleteInstance");
        $router[] = new GetRoute("$prefix/<id>/licences", "Instances:licences");
        $router[] = new PostRoute("$prefix/<id>/licences", "Instances:createLicence");
        $router[] = new PostRoute("$prefix/licences/<licenceId>", "Instances:updateLicence");
        $router[] = new DeleteRoute("$prefix/licences/<licenceId>", "Instances:deleteLicence");
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
        $router[] = new GetRoute("$prefix/exercise/<exerciseId>", "ReferenceExerciseSolutions:solutions");
        $router[] = new PostRoute("$prefix/exercise/<exerciseId>/pre-submit", "ReferenceExerciseSolutions:preSubmit");
        $router[] = new PostRoute("$prefix/exercise/<exerciseId>/submit", "ReferenceExerciseSolutions:submit");
        $router[] = new PostRoute(
            "$prefix/exercise/<exerciseId>/resubmit-all",
            "ReferenceExerciseSolutions:resubmitAll"
        );

        $router[] = new GetRoute("$prefix/<solutionId>", "ReferenceExerciseSolutions:detail");
        $router[] = new PostRoute("$prefix/<solutionId>", "ReferenceExerciseSolutions:update");
        $router[] = new DeleteRoute("$prefix/<solutionId>", "ReferenceExerciseSolutions:deleteReferenceSolution");
        $router[] = new PostRoute("$prefix/<id>/resubmit", "ReferenceExerciseSolutions:resubmit");
        $router[] = new GetRoute("$prefix/<solutionId>/submissions", "ReferenceExerciseSolutions:submissions");
        $router[] = new GetRoute("$prefix/<id>/files", "ReferenceExerciseSolutions:files");
        $router[] = new GetRoute(
            "$prefix/<solutionId>/download-solution",
            "ReferenceExerciseSolutions:downloadSolutionArchive"
        );
        $router[] = new PostRoute("$prefix/<solutionId>/visibility", "ReferenceExerciseSolutions:setVisibility");

        $router[] = new GetRoute("$prefix/submission/<submissionId>", "ReferenceExerciseSolutions:submission");
        $router[] = new DeleteRoute("$prefix/submission/<submissionId>", "ReferenceExerciseSolutions:deleteSubmission");
        $router[] = new GetRoute(
            "$prefix/submission/<submissionId>/download-result",
            "ReferenceExerciseSolutions:downloadResultArchive"
        );
        $router[] = new GetRoute(
            "$prefix/submission/<submissionId>/score-config",
            "ReferenceExerciseSolutions:evaluationScoreConfig"
        );

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
        $router[] = new GetRoute("$prefix/<id>", "AssignmentSolutions:solution");
        $router[] = new PostRoute("$prefix/<id>", "AssignmentSolutions:updateSolution");
        $router[] = new DeleteRoute("$prefix/<id>", "AssignmentSolutions:deleteSolution");
        $router[] = new PostRoute("$prefix/<id>/bonus-points", "AssignmentSolutions:setBonusPoints");
        $router[] = new GetRoute("$prefix/<id>/submissions", "AssignmentSolutions:submissions");
        $router[] = new PostRoute("$prefix/<id>/set-flag/<flag>", "AssignmentSolutions:setFlag");
        $router[] = new PostRoute("$prefix/<id>/resubmit", "Submit:resubmit");
        $router[] = new GetRoute("$prefix/<id>/files", "AssignmentSolutions:files");
        $router[] = new GetRoute("$prefix/<id>/download-solution", "AssignmentSolutions:downloadSolutionArchive");

        $router[] = new GetRoute("$prefix/submission/<submissionId>", "AssignmentSolutions:submission");
        $router[] = new DeleteRoute("$prefix/submission/<submissionId>", "AssignmentSolutions:deleteSubmission");
        $router[] = new GetRoute(
            "$prefix/submission/<submissionId>/download-result",
            "AssignmentSolutions:downloadResultArchive"
        );
        $router[] = new GetRoute(
            "$prefix/submission/<submissionId>/score-config",
            "AssignmentSolutions:evaluationScoreConfig"
        );

        $router[] = new GetRoute("$prefix/<id>/review", "AssignmentSolutionReviews:");
        $router[] = new PostRoute("$prefix/<id>/review", "AssignmentSolutionReviews:update");
        $router[] = new DeleteRoute("$prefix/<id>/review", "AssignmentSolutionReviews:remove");
        $router[] = new PostRoute("$prefix/<id>/review-comment", "AssignmentSolutionReviews:newComment");
        $router[] = new PostRoute("$prefix/<id>/review-comment/<commentId>", "AssignmentSolutionReviews:editComment");
        $router[] = new DeleteRoute(
            "$prefix/<id>/review-comment/<commentId>",
            "AssignmentSolutionReviews:deleteComment"
        );

        return $router;
    }

    private static function createAssignmentSolversRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router[] = new GetRoute("$prefix", "AssignmentSolvers:");
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
        $router[] = new GetRoute("$prefix", "SubmissionFailures:");
        $router[] = new GetRoute("$prefix/unresolved", "SubmissionFailures:unresolved");
        $router[] = new GetRoute("$prefix/<id>", "SubmissionFailures:detail");
        $router[] = new PostRoute("$prefix/<id>/resolve", "SubmissionFailures:resolve");
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
        $router[] = new PostRoute("$prefix/partial", "UploadedFiles:startPartial");
        $router[] = new PutRoute("$prefix/partial/<id>", "UploadedFiles:appendPartial");
        $router[] = new DeleteRoute("$prefix/partial/<id>", "UploadedFiles:cancelPartial");
        $router[] = new PostRoute("$prefix/partial/<id>", "UploadedFiles:completePartial");

        $router[] = new PostRoute("$prefix", "UploadedFiles:upload");
        $router[] = new GetRoute("$prefix/supplementary-file/<id>/download", "UploadedFiles:downloadSupplementaryFile");
        $router[] = new GetRoute("$prefix/<id>", "UploadedFiles:detail");
        $router[] = new GetRoute("$prefix/<id>/download", "UploadedFiles:download");
        $router[] = new GetRoute("$prefix/<id>/content", "UploadedFiles:content");
        $router[] = new GetRoute("$prefix/<id>/digest", "UploadedFiles:digest");
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
        $router[] = new GetRoute("$prefix", "Users:");
        $router[] = new PostRoute("$prefix", "Registration:createAccount");
        $router[] = new PostRoute("$prefix/validate-registration-data", "Registration:validateRegistrationData");
        $router[] = new PostRoute("$prefix/list", "Users:listByIds");
        $router[] = new GetRoute("$prefix/ical/<id>", "UserCalendars:");
        $router[] = new DeleteRoute("$prefix/ical/<id>", "UserCalendars:expireCalendar");
        $router[] = new PostRoute("$prefix/invite", "Registration:createInvitation");
        $router[] = new PostRoute("$prefix/accept-invitation", "Registration:acceptInvitation");

        $router[] = new GetRoute("$prefix/<id>", "Users:detail");
        $router[] = new PostRoute("$prefix/<id>/invalidate-tokens", "Users:invalidateTokens");
        $router[] = new DeleteRoute("$prefix/<id>", "Users:delete");
        $router[] = new GetRoute("$prefix/<id>/groups", "Users:groups");
        $router[] = new GetRoute("$prefix/<id>/groups/all", "Users:allGroups");
        $router[] = new GetRoute("$prefix/<id>/instances", "Users:instances");
        $router[] = new PostRoute("$prefix/<id>", "Users:updateProfile");
        $router[] = new PostRoute("$prefix/<id>/settings", "Users:updateSettings");
        $router[] = new PostRoute("$prefix/<id>/ui-data", "Users:updateUiData");
        $router[] = new PostRoute("$prefix/<id>/create-local", "Users:createLocalAccount");
        $router[] = new PostRoute("$prefix/<id>/role", "Users:setRole");
        $router[] = new PostRoute("$prefix/<id>/allowed", "Users:setAllowed");
        $router[] = new PostRoute("$prefix/<id>/external-login/<service>", "Users:updateExternalLogin");
        $router[] = new DeleteRoute("$prefix/<id>/external-login/<service>", "Users:removeExternalLogin");
        $router[] = new GetRoute("$prefix/<id>/calendar-tokens", "UserCalendars:userCalendars");
        $router[] = new PostRoute("$prefix/<id>/calendar-tokens", "UserCalendars:createCalendar");
        $router[] = new GetRoute("$prefix/<id>/pending-reviews", "AssignmentSolutionReviews:pending");
        $router[] = new GetRoute("$prefix/<id>/review-requests", "AssignmentSolutions:reviewRequests");
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
        $router[] = new PostRoute("$prefix/verify", "EmailVerification:emailVerification");
        $router[] = new PostRoute("$prefix/resend", "EmailVerification:resendVerificationEmail");
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
        $router[] = new PostRoute("$prefix", "ForgottenPassword:");
        $router[] = new PostRoute("$prefix/change", "ForgottenPassword:change");
        $router[] = new PostRoute("$prefix/validate-password-strength", "ForgottenPassword:validatePasswordStrength");
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
        $router[] = new GetRoute("$prefix", "RuntimeEnvironments:");
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
        $router[] = new GetRoute("$prefix", "HardwareGroups:");
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
        $router[] = new GetRoute("$prefix", "Pipelines:");
        $router[] = new PostRoute("$prefix", "Pipelines:createPipeline");
        $router[] = new GetRoute("$prefix/boxes", "Pipelines:getDefaultBoxes");
        $router[] = new PostRoute("$prefix/<id>/fork", "Pipelines:forkPipeline");
        $router[] = new GetRoute("$prefix/<id>", "Pipelines:getPipeline");
        $router[] = new PostRoute("$prefix/<id>", "Pipelines:updatePipeline");
        $router[] = new DeleteRoute("$prefix/<id>", "Pipelines:removePipeline");
        $router[] = new PostRoute("$prefix/<id>/runtime-environments", "Pipelines:updateRuntimeEnvironments");
        $router[] = new PostRoute("$prefix/<id>/validate", "Pipelines:validatePipeline");
        $router[] = new GetRoute("$prefix/<id>/supplementary-files", "Pipelines:getSupplementaryFiles");
        $router[] = new PostRoute("$prefix/<id>/supplementary-files", "Pipelines:uploadSupplementaryFiles");
        $router[] = new DeleteRoute("$prefix/<id>/supplementary-files/<fileId>", "Pipelines:deleteSupplementaryFile");
        $router[] = new GetRoute("$prefix/<id>/exercises", "Pipelines:getPipelineExercises");
        return $router;
    }

    private static function createSisRouter(string $prefix): RouteList
    {
        $router = new RouteList();
        $router[] = new GetRoute("$prefix/status/", "Sis:status");
        $router[] = new GetRoute("$prefix/terms/", "Sis:getTerms");
        $router[] = new PostRoute("$prefix/terms/", "Sis:registerTerm");
        $router[] = new PostRoute("$prefix/terms/<id>", "Sis:editTerm");
        $router[] = new DeleteRoute("$prefix/terms/<id>", "Sis:deleteTerm");
        $router[] = new GetRoute(
            "$prefix/users/<userId>/subscribed-groups/<year>/<term>/as-student",
            "Sis:subscribedCourses"
        );
        $router[] = new GetRoute("$prefix/users/<userId>/supervised-courses/<year>/<term>", "Sis:supervisedCourses");
        $router[] = new GetRoute("$prefix/remote-courses/<courseId>/possible-parents", "Sis:possibleParents");
        $router[] = new PostRoute("$prefix/remote-courses/<courseId>/create", "Sis:createGroup");
        $router[] = new PostRoute("$prefix/remote-courses/<courseId>/bind", "Sis:bindGroup");
        $router[] = new DeleteRoute("$prefix/remote-courses/<courseId>/bindings/<groupId>", "Sis:unbindGroup");
        return $router;
    }

    private static function createEmailsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router[] = new PostRoute("$prefix", "Emails:default");
        $router[] = new PostRoute("$prefix/supervisors", "Emails:sendToSupervisors");
        $router[] = new PostRoute("$prefix/regular-users", "Emails:sendToRegularUsers");
        $router[] = new PostRoute("$prefix/groups/<groupId>", "Emails:sendToGroupMembers");
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
        $router[] = new GetRoute("$prefix/<id>", "ShadowAssignments:detail");
        $router[] = new PostRoute("$prefix/<id>", "ShadowAssignments:updateDetail");
        $router[] = new PostRoute("$prefix", "ShadowAssignments:create");
        $router[] = new DeleteRoute("$prefix/<id>", "ShadowAssignments:remove");
        $router[] = new PostRoute("$prefix/<id>/validate", "ShadowAssignments:validate");
        $router[] = new PostRoute("$prefix/<id>/create-points", "ShadowAssignments:createPoints");
        $router[] = new PostRoute("$prefix/points/<pointsId>", "ShadowAssignments:updatePoints");
        $router[] = new DeleteRoute("$prefix/points/<pointsId>", "ShadowAssignments:removePoints");
        return $router;
    }

    private static function createNotificationsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router[] = new GetRoute("$prefix", "Notifications:default");
        $router[] = new GetRoute("$prefix/all", "Notifications:all");
        $router[] = new PostRoute("$prefix", "Notifications:create");
        $router[] = new PostRoute("$prefix/<id>", "Notifications:update");
        $router[] = new DeleteRoute("$prefix/<id>", "Notifications:remove");
        return $router;
    }

    private static function createWorkerFilesRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router[] = new GetRoute("$prefix/submission-archive/<type>/<id>", "WorkerFiles:downloadSubmissionArchive");
        $router[] = new GetRoute("$prefix/supplementary-file/<hash>", "WorkerFiles:downloadSupplementaryFile");
        $router[] = new PutRoute("$prefix/result/<type>/<id>", "WorkerFiles:uploadResultsFile");
        return $router;
    }

    private static function createAsyncJobsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router[] = new GetRoute("$prefix/<id>", "AsyncJobs:default");
        $router[] = new GetRoute("$prefix", "AsyncJobs:list");
        $router[] = new PostRoute("$prefix/<id>/abort", "AsyncJobs:abort");
        $router[] = new PostRoute("$prefix/ping", "AsyncJobs:ping");
        return $router;
    }

    private static function createPlagiarismRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router[] = new GetRoute("$prefix", "Plagiarism:listBatches");
        $router[] = new GetRoute("$prefix/<id>", "Plagiarism:batchDetail");
        $router[] = new PostRoute("$prefix", "Plagiarism:createBatch");
        $router[] = new PostRoute("$prefix/<id>", "Plagiarism:updateBatch");
        $router[] = new GetRoute("$prefix/<id>/<solutionId>", "Plagiarism:getSimilarities");
        $router[] = new PostRoute("$prefix/<id>/<solutionId>", "Plagiarism:addSimilarities");
        return $router;
    }

    private static function createExtensionsRoutes(string $prefix): RouteList
    {
        $router = new RouteList();
        $router[] = new GetRoute("$prefix/<extId>/<instanceId>", "Extensions:url");
        $router[] = new PostRoute("$prefix/<extId>", "Extensions:token");
        return $router;
    }
}
