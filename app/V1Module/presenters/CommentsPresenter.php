<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\Notifications\SolutionCommentsEmailsSender;
use App\Model\Entity\Comment;
use App\Model\Entity\CommentThread;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\Comments;
use App\Model\Repository\ReferenceExerciseSolutions;
use App\Security\ACL\ICommentPermissions;

/**
 * Endpoints for comment manipulation
 * @LoggedIn
 */
class CommentsPresenter extends BasePresenter {

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
   * @param string $id
   * @return CommentThread
   */
  protected function findThreadOrCreateIt(string $id) {
    $thread = $this->comments->getThread($id);
    if (!$thread) {
      $thread = CommentThread::createThread($id);
      $this->comments->persist($thread, false);
    }

    return $thread;
  }

  public function checkDefault($id) {
    $thread = $this->comments->getThread($id);

    if ($thread) {
      if (!$this->commentAcl->canViewThread($thread)) {
        throw new ForbiddenRequestException();
      }
    } else {
      if (!$this->commentAcl->canCreateThread()) {
        throw new ForbiddenRequestException();
      }
    }
  }

  /**
   * Get a comment thread
   * @GET
   * @param string $id Identifier of the comment thread
   * @throws ForbiddenRequestException
   */
  public function actionDefault($id) {
    $thread = $this->findThreadOrCreateIt($id);
    $this->comments->flush();
    $user = $this->getCurrentUser();
    $thread->filterPublic($user);
    $this->sendSuccessResponse($thread);
  }

  public function checkAddComment(string $id) {
    $thread = $this->comments->getThread($id);

    if (!$thread && !$this->commentAcl->canCreateThread()) {
      throw new ForbiddenRequestException();
    }

    if ($thread && !$this->commentAcl->canAddComment($thread)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Add a comment to a thread
   * @POST
   * @Param(type="post", name="text", validation="string:1..", description="Text of the comment")
   * @Param(type="post", name="isPrivate", validation="string", description="True if the comment is private")
   * @param string $id Identifier of the comment thread
   * @throws ForbiddenRequestException
   */
  public function actionAddComment(string $id) {
    $thread = $this->findThreadOrCreateIt($id);

    $user = $this->getCurrentUser();
    $text = $this->getRequest()->getPost("text");
    $isPrivate = filter_var($this->getRequest()->getPost("isPrivate"), FILTER_VALIDATE_BOOLEAN);
    $comment = Comment::createComment($thread, $user, $text, $isPrivate);

    $this->comments->persist($comment, false);
    $this->comments->persist($thread, false);
    $this->comments->flush();

    // send email to all participants in comment thread
    if ($solution = $this->assignmentSolutions->get($id)) {
      $this->solutionCommentsEmailsSender->assignmentSolutionComment($solution, $comment);
    } else if ($solution = $this->referenceExerciseSolutions->get($id)) {
      $this->solutionCommentsEmailsSender->referenceSolutionComment($solution, $comment);
    } else {
      // Nothing to do here...
    }

    $this->sendSuccessResponse($comment);
  }

  public function checkTogglePrivate(string $threadId, string $commentId) {
    /** @var Comment $comment */
    $comment = $this->comments->findOrThrow($commentId);

    if ($comment->getThread()->getId() !== $threadId) {
      throw new NotFoundException();
    }

    if (!$this->commentAcl->canAlter($comment)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Make a private comment public or vice versa
   * @POST
   * @param string $threadId Identifier of the comment thread
   * @param string $commentId Identifier of the comment
   * @throws NotFoundException
   */
  public function actionTogglePrivate(string $threadId, string $commentId) {
    /** @var Comment $comment */
    $comment = $this->comments->findOrThrow($commentId);

    $comment->togglePrivate();
    $this->comments->persist($comment);
    $this->comments->flush();

    $this->sendSuccessResponse($comment);
  }

  public function checkDelete(string $threadId, string $commentId) {
    /** @var Comment $comment */
    $comment = $this->comments->findOrThrow($commentId);

    if ($comment->getThread()->getId() !== $threadId) {
      throw new NotFoundException();
    }

    if (!$this->commentAcl->canDelete($comment)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Delete a comment
   * @DELETE
   * @param string $threadId Identifier of the comment thread
   * @param string $commentId Identifier of the comment
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionDelete(string $threadId, string $commentId) {
    /** @var Comment $comment */
    $comment = $this->comments->findOrThrow($commentId);

    $this->comments->remove($comment, true);
    $this->sendSuccessResponse("OK");
  }
}
