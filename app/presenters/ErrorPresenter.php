<?php

namespace App\Presenters;

use Nette;
use Nette\Application\Responses;

class ErrorPresenter implements Nette\Application\IPresenter {
	use Nette\SmartObject;

	/**
	 * @return Nette\Application\IResponse
	 */
	public function run(Nette\Application\Request $request) {
		$e = $request->getParameter('exception');
    return $this->sendJson([
      error => TRUE,
      code  => $e->getCode(),
      msg   => $e->getMessage()
    ]);
	}
}
