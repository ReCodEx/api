<?php

namespace App\Helpers;

use App\Model\Entity\ReferenceExerciseSolution;
use App\Security\ACL\IExercisePermissions;


/**
 * Permissions decorator injects permission flags to serialized entities, which are passed
 * on in the response.
 * @warning This is just a prototype. It will be rewritten properly once a security endpoint is implemented
 * and authorization and execution of endpoints will be separated.
 */
class PermissionsResponseDecorator implements IResponseDecorator
{
  private $exerciseAcl;

  public function __construct(IExercisePermissions $exerciseAcl)
  {
    $this->exerciseAcl = $exerciseAcl;
  }


  public function decorate($payload)
  {
    // This might not be necessary, but just in case ...
    if ($payload instanceof \Doctrine\Common\Collections\Collection) {
      $payload = $payload->getValues();
    }

    if (is_array($payload)) {
      return array_map([$this, 'decorate'], $payload);
    }

    // This is just a proof of concept, which is used to demonstrate the functionality.
    // The flags-permissions mapping will be in neon configuration file.
    if ($payload instanceof ReferenceExerciseSolution) {
      $json = $payload->jsonSerialize();
      $json['permissionHints'] = [
        'delete' => $this->exerciseAcl->canDeleteReferenceSolution($payload->getExercise(), $payload),
        'evaluate' => $this->exerciseAcl->canEvaluateReferenceSolution($payload->getExercise(), $payload),
      ];
      return $json;
    }

    return $payload;
  }
}
