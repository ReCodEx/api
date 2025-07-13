<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\Notifications\SolutionCommentsEmailsSender;
use App\Helpers\Notifications\AssignmentCommentsEmailsSender;
use App\Model\Entity\Comment;
use App\Model\Entity\CommentThread;
use App\Model\Repository\Assignments;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\Comments;
use App\Model\Repository\ReferenceExerciseSolutions;
use App\Security\ACL\ICommentPermissions;

/**
 * Endpoints for comment manipulation
 * @LoggedIn
 */
class CommentsPresenter extends BasePresenter
{
    /**
     * @var Comments
     * @inject
     */
    public $comments;

    /**
     * @var ICommentPermissions
     * @inject
     */
    public $commentAcl;

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
     * @var ReferenceExerciseSolutions
     * @inject
     */
    public $referenceExerciseSolutions;

    /**
     * @var SolutionCommentsEmailsSender
     * @inject
     */
    public $solutionCommentsEmailsSender;

    /**
     * @var AssignmentCommentsEmailsSender
     * @inject
     */
    public $assignmentCommentsEmailsSender;

    /**
     * @param string $id
     * @return CommentThread
     */
    protected function findThreadOrCreateIt(string $id)
    {
        $thread = $this->comments->getThread($id);
        if (!$thread) {
            $thread = CommentThread::createThread($id);
            $this->comments->persist($thread, false);
        }

        return $thread;
    }

    // public function checkDefault($id)
    // {
    //     $thread = $this->comments->getThread($id);

    //     if ($thread) {
    //         if (!$this->commentAcl->canViewThread($thread)) {
    //             throw new ForbiddenRequestException();
    //         }
    //     } else {
    //         if (!$this->commentAcl->canCreateThread()) {
    //             throw new ForbiddenRequestException();
    //         }
    //     }
    // }

    /**
     * Get a comment thread
     * @GET
     * @throws ForbiddenRequestException
     */
    #[Path("id", new VUuid(), "Identifier of the comment thread", required: true)]
    public function actionDefault($id)
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkAddComment(string $id)
    // {
    //     $thread = $this->comments->getThread($id);

    //     if (!$thread && !$this->commentAcl->canCreateThread()) {
    //         throw new ForbiddenRequestException();
    //     }

    //     if ($thread && !$this->commentAcl->canAddComment($thread)) {
    //         throw new ForbiddenRequestException();
    //     }
    // }

    /**
     * Add a comment to a thread
     * @POST
     * @throws ForbiddenRequestException
     */
    #[Post("text", new VString(1, 65535), "Text of the comment")]
    #[Post("isPrivate", new VBool(), "True if the comment is private", required: false)]
    #[Path("id", new VUuid(), "Identifier of the comment thread", required: true)]
    public function actionAddComment(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkTogglePrivate(string $threadId, string $commentId)
    // {
    //     /** @var Comment $comment */
    //     $comment = $this->comments->findOrThrow($commentId);

    //     if ($comment->getThread()->getId() !== $threadId) {
    //         throw new NotFoundException();
    //     }

    //     if (!$this->commentAcl->canAlter($comment)) {
    //         throw new ForbiddenRequestException();
    //     }
    // }

    /**
     * Make a private comment public or vice versa
     * @DEPRECATED
     * @POST
     * @throws NotFoundException
     */
    #[Path("threadId", new VString(), "Identifier of the comment thread", required: true)]
    #[Path("commentId", new VString(), "Identifier of the comment", required: true)]
    public function actionTogglePrivate(string $threadId, string $commentId)
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkSetPrivate(string $threadId, string $commentId)
    // {
    //     /** @var Comment $comment */
    //     $comment = $this->comments->findOrThrow($commentId);

    //     if ($comment->getThread()->getId() !== $threadId) {
    //         throw new NotFoundException();
    //     }

    //     if (!$this->commentAcl->canAlter($comment)) {
    //         throw new ForbiddenRequestException();
    //     }
    // }

    /**
     * Set the private flag of a comment
     * @POST
     * @throws NotFoundException
     */
    #[Post("isPrivate", new VBool(), "True if the comment is private")]
    #[Path("threadId", new VString(), "Identifier of the comment thread", required: true)]
    #[Path("commentId", new VString(), "Identifier of the comment", required: true)]
    public function actionSetPrivate(string $threadId, string $commentId)
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkDelete(string $threadId, string $commentId)
    // {
    //     /** @var Comment $comment */
    //     $comment = $this->comments->findOrThrow($commentId);

    //     if ($comment->getThread()->getId() !== $threadId) {
    //         throw new NotFoundException();
    //     }

    //     if (!$this->commentAcl->canDelete($comment)) {
    //         throw new ForbiddenRequestException();
    //     }
    // }

    /**
     * Delete a comment
     * @DELETE
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Path("threadId", new VString(), "Identifier of the comment thread", required: true)]
    #[Path("commentId", new VString(), "Identifier of the comment", required: true)]
    public function actionDelete(string $threadId, string $commentId)
    {
        $this->sendSuccessResponse("OK");
    }
}
