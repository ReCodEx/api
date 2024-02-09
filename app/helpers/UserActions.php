<?php

namespace App\Helpers;

use App\Security\Identity;
use Nette;
use DateTime;
use Nette\Utils\Json;
use Tracy\Debugger;

/**
 * Logs all actions of users into file.
 */
class UserActions
{
    private const USER_ACTIONS_LOG = "user_actions.log";
    private const COLUMNS_GLUE = ",";

    /** @var Nette\Security\User */
    private $user;

    /**
     * UserActions constructor.
     * @param Nette\Security\User $user
     */
    public function __construct(Nette\Security\User $user)
    {
        $this->user = $user;
    }

    /**
     * Log an action carried out right now by the currently logged user.
     * @param string $action Action name
     * @param string|null $remoteAddr IP address from which the action was requested
     * @param array $params Parameters of the request
     * @param int $code HTTP response code
     * @param mixed $data Additional data
     * @return bool if writing to file was alright
     */
    public function log(string $action, ?string $remoteAddr, array $params, int $code, $data = null): bool
    {
        if (!Debugger::isEnabled()) {
            // debugger is not enabled, this means log directory is not accessible
            return false;
        }

        if (!is_dir(Debugger::$logDirectory)) {
            throw new \RuntimeException("Logging directory '" . Debugger::$logDirectory . "' not found");
        }

        /** @var ?Identity $identity */
        $identity = $this->user->getIdentity();
        if ($identity === null || !($identity instanceof Identity)) {
            return false;
        }

        // construct content for logger
        $content = [
            $identity->getUserData() !== null ? $identity->getUserData()->getId() : "null",
            (new DateTime())->getTimestamp(),
            $remoteAddr ?? '',
            $action,
            Json::encode($params),
            $code,
            Json::encode($data)
        ];

        // write content as csv to file
        $log = fopen(Debugger::$logDirectory . '/' . self::USER_ACTIONS_LOG, 'a');
        $putResult = fputcsv($log, $content, self::COLUMNS_GLUE);
        $closeResult = fclose($log);
        return $putResult && $closeResult;
    }
}
