<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\BadRequestException;
use App\Model\Repository\Assignments;
use App\Model\Repository\AssignmentSolvers;
use App\Model\Repository\Users;
use App\Model\Repository\Groups;
use App\Exceptions\ForbiddenRequestException;
use App\Security\ACL\IAssignmentPermissions;
use App\Security\ACL\IGroupPermissions;

/**
 * Endpoints for fetching assignment-solvers data.
 * @LoggedIn
 */
class AssignmentSolversPresenter extends BasePresenter
{
    /**
     * @var Assignments
     * @inject
     */
    public $assignments;

    /**
     * @var AssignmentSolvers
     * @inject
     */
    public $assignmentSolvers;

    /**
     * @var Users
     * @inject
     */
    public $users;

    /**
     * @var Groups
     * @inject
     */
    public $groups;

    /**
     * @var IAssignmentPermissions
     * @inject
     */
    public $assignmentAcl;

    /**
     * @var IGroupPermissions
     * @inject
     */
    public $groupAcl;


    public function noncheckDefault(?string $assignmentId, ?string $groupId, ?string $userId): void
    {
        $assignment = $assignmentId ? $this->assignments->findOrThrow($assignmentId) : null;
        $group = !$assignment && $groupId ? $this->groups->findOrThrow($groupId) : null;
        $user = $userId ? $this->users->findOrThrow($userId) : null;

        // the rules for accessing solvers are implied by permissions for accessing solutions
        // they are nonchecked differently based on the query parameters
        if ($assignment) {
            // when selecting solvers for a particular assignment, one can see all solutions
            // or solutions of a particular user (if user is set)
            if (
                !$this->assignmentAcl->canViewAssignmentSolutions($assignment)
                && (!$user || !$this->assignmentAcl->canViewSubmissions($assignment, $user))
            ) {
                throw new ForbiddenRequestException("You cannot access selected subset of assignment solvers");
            }
        } elseif ($group) {
            // when selecting solvers of all assignments from a group, one can see entire group stats,
            // or if the user is given, load see all assignments + user stats from selected group
            if (
                !$this->groupAcl->canViewStats($group)
                && (!$user || !$this->groupAcl->canViewAssignments($group)
                    || !$this->groupAcl->canViewStudentStats($group, $user))
            ) {
                throw new ForbiddenRequestException("You cannot access selected subset of assignment solvers");
            }
        } else {
            // either an assignment or a group must be selected
            throw new BadRequestException("Either assignment or group must be set to narrow down the result.");
        }
    }

    /**
     * Get a list of assignment solvers based on given parameters (assignment/group and solver user).
     * Either assignment or group ID must be set (group is ignored if assignment is set), user ID is optional.
     * @GET
     */
    #[Query("assignmentId", new VUuid(), required: false)]
    #[Query(
        "groupId",
        new VUuid(),
        "An alternative for assignment ID, selects all assignments from a group.",
        required: false,
    )]
    #[Query("userId", new VUuid(), required: false)]
    public function actionDefault(?string $assignmentId, ?string $groupId, ?string $userId): void
    {
        $this->sendSuccessResponse("OK");
    }
}
