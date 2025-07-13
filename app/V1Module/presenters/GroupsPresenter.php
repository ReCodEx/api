<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Attributes\ResponseFormat;
use App\Helpers\MetaFormats\FormatDefinitions\GroupFormat;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\InvalidApiArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\FrontendErrorMappings;
use App\Helpers\Localizations;
use App\Model\Entity\Assignment;
use App\Model\Entity\ShadowAssignment;
use App\Model\Entity\Group;
use App\Model\Entity\GroupExamLock;
use App\Model\Entity\Instance;
use App\Model\Entity\LocalizedGroup;
use App\Model\Entity\GroupMembership;
use App\Model\Entity\AssignmentSolution;
use App\Model\Repository\Assignments;
use App\Model\Repository\Groups;
use App\Model\Repository\GroupExams;
use App\Model\Repository\GroupExamLocks;
use App\Model\Repository\Users;
use App\Model\Repository\Instances;
use App\Model\Repository\GroupMemberships;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\SecurityEvents;
use App\Model\View\AssignmentViewFactory;
use App\Model\View\AssignmentSolutionViewFactory;
use App\Model\View\ShadowAssignmentViewFactory;
use App\Model\View\GroupViewFactory;
use App\Model\View\UserViewFactory;
use App\Security\ACL\IAssignmentPermissions;
use App\Security\ACL\IAssignmentSolutionPermissions;
use App\Security\ACL\IShadowAssignmentPermissions;
use App\Security\ACL\IGroupPermissions;
use App\Security\Identity;
use App\Security\Loader;
use App\Security\UserStorage;
use DateTime;
use Nette\Application\Request;

/**
 * Endpoints for group manipulation
 * @LoggedIn
 */
class GroupsPresenter extends BasePresenter
{
    /**
     * @var Groups
     * @inject
     */
    public $groups;

    /**
     * @var GroupExams
     * @inject
     */
    public $groupExams;

    /**
     * @var GroupExamLocks
     * @inject
     */
    public $groupExamLocks;

    /**
     * @var Instances
     * @inject
     */
    public $instances;

    /**
     * @var Users
     * @inject
     */
    public $users;

    /**
     * @var GroupMemberships
     * @inject
     */
    public $groupMemberships;

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
     * @var SecurityEvents
     * @inject
     */
    public $securityEvents;

    /**
     * @var IGroupPermissions
     * @inject
     */
    public $groupAcl;

    /**
     * @var IAssignmentPermissions
     * @inject
     */
    public $assignmentAcl;

    /**
     * @var IAssignmentSolutionPermissions
     * @inject
     */
    public $assignmentSolutionAcl;


    /**
     * @var IShadowAssignmentPermissions
     * @inject
     */
    public $shadowAssignmentAcl;

    /**
     * @var Loader
     * @inject
     */
    public $aclLoader;

    /**
     * @var UserStorage
     * @inject
     */
    public $userStorage;

    /**
     * @var GroupViewFactory
     * @inject
     */
    public $groupViewFactory;

    /**
     * @var UserViewFactory
     * @inject
     */
    public $userViewFactory;

    /**
     * @var AssignmentViewFactory
     * @inject
     */
    public $assignmentViewFactory;

    /**
     * @var AssignmentSolutionViewFactory
     * @inject
     */
    public $solutionsViewFactory;

    /**
     * @var ShadowAssignmentViewFactory
     * @inject
     */
    public $shadowAssignmentViewFactory;

    /**
     * Get a list of all non-archived groups a user can see. The return set is filtered by parameters.
     * @GET
     */
    #[Query("instanceId", new VString(), "Only groups of this instance are returned.", required: false, nullable: true)]
    #[Query(
        "ancestors",
        new VBool(),
        "If true, returns an ancestral closure of the initial result set. "
            . "Included ancestral groups do not respect other filters (archived, search, ...).",
        required: false,
    )]
    #[Query(
        "search",
        new VString(),
        "Search string. Only groups containing this string as a substring of their names are returned.",
        required: false,
        nullable: true,
    )]
    #[Query("archived", new VBool(), "Include also archived groups in the result.", required: false)]
    #[Query(
        "onlyArchived",
        new VBool(),
        "Automatically implies \$archived flag and returns only archived groups.",
        required: false,
    )]
    public function actionDefault(
        ?string $instanceId = null,
        bool $ancestors = false,
        ?string $search = null,
        bool $archived = false,
        bool $onlyArchived = false
    ) {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Helper method that handles updating points limit and threshold to a group entity (from a request).
     * @param Request $req request data
     * @param Group $group to be updated
     */
    private function setGroupPoints(Request $req, Group $group): void
    {
        $threshold = $req->getPost("threshold");
        $pointsLimit = $req->getPost("pointsLimit");
        if ($threshold !== null && $pointsLimit !== null) {
            throw new InvalidApiArgumentException(
                'threshold',
                "A group may have either a threshold or points limit, not both."
            );
        }
        if ($threshold !== null) {
            if ($threshold <= 0 || $threshold > 100) {
                throw new InvalidApiArgumentException('threshold', "A threshold must be in the (0, 100] (%) range.");
            }
            $group->setThreshold($threshold / 100);
        } else {
            $group->setThreshold(null);
        }
        if ($pointsLimit !== null) {
            if ($pointsLimit <= 0) {
                throw new InvalidApiArgumentException('pointsLimit', "A points limit must be a positive number.");
            }
            $group->setPointsLimit($pointsLimit);
        } else {
            $group->setPointsLimit(null);
        }
    }

    /**
     * Create a new group
     * @POST
     * @throws ForbiddenRequestException
     * @throws InvalidApiArgumentException
     */
    #[Post("instanceId", new VUuid(), "An identifier of the instance where the group should be created")]
    #[Post(
        "externalId",
        new VMixed(),
        "An informative, human readable identifier of the group",
        required: false,
        nullable: true,
    )]
    #[Post(
        "parentGroupId",
        new VUuid(),
        "Identifier of the parent group (if none is given, a top-level group is created)",
        required: false,
    )]
    #[Post("publicStats", new VBool(), "Should students be able to see each other's results?", required: false)]
    #[Post("detaining", new VBool(), "Are students prevented from leaving the group on their own?", required: false)]
    #[Post("isPublic", new VBool(), "Should the group be visible to all student?", required: false)]
    #[Post(
        "isOrganizational",
        new VBool(),
        "Whether the group is organizational (no assignments nor students).",
        required: false,
    )]
    #[Post("isExam", new VBool(), "Whether the group is an exam group.", required: false)]
    #[Post("localizedTexts", new VArray(), "Localized names and descriptions", required: false)]
    #[Post("threshold", new VInt(), "A minimum percentage of points needed to pass the course", required: false)]
    #[Post("pointsLimit", new VInt(), "A minimum of (absolute) points needed to pass the course", required: false)]
    #[Post(
        "noAdmin",
        new VBool(),
        "If true, no admin is assigned to group (current user is assigned as admin by default.",
        required: false,
    )]
    #[ResponseFormat(GroupFormat::class)]
    public function actionAddGroup()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Validate group creation data
     * @POST
     * @throws ForbiddenRequestException
     */
    #[Post("name", new VMixed(), "Name of the group", nullable: true)]
    #[Post("locale", new VMixed(), "The locale of the name", nullable: true)]
    #[Post("instanceId", new VMixed(), "Identifier of the instance where the group belongs", nullable: true)]
    #[Post("parentGroupId", new VMixed(), "Identifier of the parent group", required: false, nullable: true)]
    public function actionValidateAddGroupData()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Update group info
     * @POST
     * @throws InvalidApiArgumentException
     */
    #[Post(
        "externalId",
        new VMixed(),
        "An informative, human readable identifier of the group",
        required: false,
        nullable: true,
    )]
    #[Post("publicStats", new VBool(), "Should students be able to see each other's results?")]
    #[Post("detaining", new VBool(), "Are students prevented from leaving the group on their own?", required: false)]
    #[Post("isPublic", new VBool(), "Should the group be visible to all student?")]
    #[Post("threshold", new VInt(), "A minimum percentage of points needed to pass the course", required: false)]
    #[Post("pointsLimit", new VInt(), "A minimum of (absolute) points needed to pass the course", required: false)]
    #[Post("localizedTexts", new VArray(), "Localized names and descriptions")]
    #[Path("id", new VUuid(), "An identifier of the updated group", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionUpdateGroup(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Set the 'isOrganizational' flag for a group
     * @POST
     * @throws BadRequestException
     * @throws NotFoundException
     */
    #[Post("value", new VBool(), "The value of the flag", required: true)]
    #[Path("id", new VUuid(), "An identifier of the updated group", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionSetOrganizational(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Set the 'isArchived' flag for a group
     * @POST
     * @throws NotFoundException
     */
    #[Post("value", new VBool(), "The value of the flag", required: true)]
    #[Path("id", new VUuid(), "An identifier of the updated group", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionSetArchived(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Change the group "exam" indicator. If denotes that the group should be listed in exam groups instead of
     * regular groups and the assignments should have "isExam" flag set by default.
     * @POST
     * @throws BadRequestException
     * @throws NotFoundException
     */
    #[Post("value", new VBool(), "The value of the flag", required: true)]
    #[Path("id", new VUuid(), "An identifier of the updated group", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionSetExam(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Set an examination period (in the future) when the group will be secured for submitting.
     * Only locked students may submit solutions in the group during this period.
     * This endpoint is also used to update already planned exam period, but only dates in the future
     * can be edited (e.g., once an exam begins, the beginning may no longer be updated).
     * @POST
     * @throws NotFoundException
     */
    #[Post(
        "begin",
        new VTimestamp(),
        "When the exam begins (unix ts in the future, optional if update is performed).",
        required: false,
        nullable: true,
    )]
    #[Post(
        "end",
        new VTimestamp(),
        "When the exam ends (unix ts in the future, no more than a day after 'begin').",
        required: true,
    )]
    #[Post("strict", new VBool(), "Whether locked users are prevented from accessing other groups.", required: false)]
    #[Path("id", new VUuid(), "An identifier of the updated group", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionSetExamPeriod(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Change the group back to regular group (remove information about an exam).
     * @DELETE
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "An identifier of the updated group", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionRemoveExamPeriod(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Retrieve a list of locks for given exam
     * @GET
     */
    #[Path("id", new VUuid(), "An identifier of the related group", required: true)]
    #[Path("examId", new VInt(), "An identifier of the exam", required: true)]
    public function actionGetExamLocks(string $id, string $examId)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Relocate the group under a different parent.
     * @POST
     * @throws NotFoundException
     * @throws BadRequestException
     */
    #[Path("id", new VUuid(), "An identifier of the relocated group", required: true)]
    #[Path("newParentId", new VString(), "An identifier of the new parent group", required: true)]
    public function actionRelocate(string $id, string $newParentId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Delete a group
     * @DELETE
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    public function actionRemoveGroup(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Get details of a group
     * @GET
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionDetail(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Get a list of subgroups of a group
     * @GET
     * @DEPRECATED Subgroup list is part of group view.
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    public function actionSubgroups(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Get a list of members of a group
     * @GET
     * @DEPRECATED Members are listed in group view.
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    public function actionMembers(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Add/update a membership (other than student) for given user
     * @POST
     */
    #[Post("type", new VString(1), "Identifier of membership type (admin, supervisor, ...)", required: true)]
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the supervisor", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionAddMember(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Remove a member (other than student) from a group
     * @DELETE
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the supervisor", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionRemoveMember(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Get all exercise assignments for a group
     * @GET
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    public function actionAssignments(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Get all shadow assignments for a group
     * @GET
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    public function actionShadowAssignments(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Get statistics of a group. If the user does not have the rights to view all of these, try to at least
     * return their statistics.
     * @GET
     * @throws ForbiddenRequestException
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    public function actionStats(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Get statistics of a single student in a group
     * @GET
     * @throws BadRequestException
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the student", required: true)]
    public function actionStudentsStats(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Get all solutions of a single student from all assignments in a group
     * @GET
     * @throws BadRequestException
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the student", required: true)]
    public function actionStudentsSolutions(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Add a student to a group
     * @POST
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the student", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionAddStudent(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Remove a student from a group
     * @DELETE
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the student", required: true)]
    #[ResponseFormat(GroupFormat::class)]
    public function actionRemoveStudent(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Lock student in a group and with an IP from which the request was made.
     * @POST
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the student", required: true)]
    public function actionLockStudent(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Unlock a student currently locked in a group.
     * @DELETE
     */
    #[Path("id", new VUuid(), "Identifier of the group", required: true)]
    #[Path("userId", new VString(), "Identifier of the student", required: true)]
    public function actionUnlockStudent(string $id, string $userId)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * @param Request $req
     * @param Group $group
     * @throws InvalidApiArgumentException
     */
    private function updateLocalizations(Request $req, Group $group): void
    {
        $localizedTexts = $req->getPost("localizedTexts");

        if (count($localizedTexts) > 0) {
            $localizations = [];

            foreach ($localizedTexts as $item) {
                $lang = $item["locale"];
                $otherGroups = $this->groups->findByName(
                    $lang,
                    $item["name"],
                    $group->getInstance(),
                    $group->getParentGroup()
                );

                foreach ($otherGroups as $otherGroup) {
                    if ($otherGroup !== $group) {
                        throw new InvalidApiArgumentException(
                            'name',
                            "There is already a group of this name, please choose a different one."
                        );
                    }
                }

                if (array_key_exists($lang, $localizations)) {
                    throw new InvalidApiArgumentException(
                        'localizedTexts',
                        sprintf("Duplicate entry for locale %s", $lang)
                    );
                }

                $name = $item["name"] ?: "";
                $description = $item["description"] ?: "";
                $localizations[$lang] = new LocalizedGroup($lang, $name, $description);
            }

            /** @var LocalizedGroup $text */
            foreach ($group->getLocalizedTexts() as $text) {
                // Localizations::updateCollection only updates the inverse side of the relationship. Doctrine needs us
                // to update the other side manually. We set it to null for all potentially removed localizations first.
                $text->setGroup(null);
            }

            Localizations::updateCollection($group->getLocalizedTexts(), $localizations);

            foreach ($group->getLocalizedTexts() as $text) {
                $text->setGroup($group);
                $this->groups->persist($text, false);
            }
        }
    }
}
