<?php

namespace App\Security\Policies;

use App\Model\Entity\Assignment;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Comment;
use App\Model\Repository\Assignments;
use App\Model\Repository\AssignmentSolutions;
use App\Security\Identity;

class CommentPermissionPolicy implements IPermissionPolicy
{
    private $assignments;
    private $assignmentSolutions;

    public function __construct(Assignments $assignments, AssignmentSolutions $assignmentSolutions)
    {
        $this->assignments = $assignments;
        $this->assignmentSolutions = $assignmentSolutions;
    }

    public function getAssociatedClass()
    {
        return Comment::class;
    }

    public function isAuthor(Identity $identity, Comment $comment)
    {
        $user = $identity->getUserData();
        if (!$user) {
            return false;
        }

        return $user === $comment->getUser();
    }

    public function isSolutionComment(Identity $identity, Comment $comment)
    {
        $user = $identity->getUserData();
        if (!$user) {
            return false;
        }

        $solution = $this->assignmentSolutions->get($comment->getCommentThread()->getId());
        return $solution !== null;
    }

    public function isSupervisorInGroupOfCommentedSolution(Identity $identity, Comment $comment)
    {
        $user = $identity->getUserData();
        if (!$user) {
            return false;
        }

        /** @var ?AssignmentSolution $solution */
        $solution = $this->assignmentSolutions->get($comment->getCommentThread()->getId());
        if (
            $solution === null ||
            $solution->getAssignment() === null
        ) {
            return false;
        }

        $group = $solution->getAssignment()->getGroup();
        return $group && ($group->isSupervisorOf($user) || $group->isAdminOf($user));
    }


    public function isSupervisorInGroupOfCommentedAssignment(Identity $identity, Comment $comment)
    {
        $user = $identity->getUserData();
        if (!$user) {
            return false;
        }

        /** @var ?Assignment $assignment */
        $assignment = $this->assignments->get($comment->getCommentThread()->getId());
        if ($assignment === null) {
            return false;
        }

        $group = $assignment->getGroup();
        return $group && ($group->isSupervisorOf($user) || $group->isAdminOf($user));
    }

    public function userIsNotGroupLocked(Identity $identity, Comment $comment): bool
    {
        $user = $identity->getUserData();
        return $user && !$user->isGroupLocked();
    }
}
