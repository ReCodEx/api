<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Model\Repository\ShadowAssignments;
use App\Model\View\ShadowAssignmentViewFactory;
use App\Security\ACL\IShadowAssignmentPermissions;

/**
 * Endpoints for points assignment manipulation
 * @LoggedIn
 */
class ShadowAssignmentsPresenter extends BasePresenter {

  /**
   * @var ShadowAssignments
   * @inject
   */
  public $shadowAssignments;

  /**
   * @var IShadowAssignmentPermissions
   * @inject
   */
  public $shadowAssignmentAcl;

  /**
   * @var ShadowAssignmentViewFactory
   * @inject
   */
  public $shadowAssignmentViewFactory;


  public function checkDetail(string $id) {
    $assignment = $this->shadowAssignments->findOrThrow($id);
    if (!$this->shadowAssignmentAcl->canViewDetail($assignment)) {
      throw new ForbiddenRequestException("You cannot view this shadow assignment.");
    }
  }

  /**
   * Get details of a shadow assignment
   * @GET
   * @param string $id Identifier of the assignment
   * @throws NotFoundException
   */
  public function actionDetail(string $id) {
    $assignment = $this->shadowAssignments->findOrThrow($id);
    $this->sendSuccessResponse($this->shadowAssignmentViewFactory->getAssignment($assignment));
  }
}
