<?php

namespace App\Responses;

use Nette;
use Nette\Application\Response;
use Eluceo\iCal\Presentation\Component;

/**
 * Wrapper for iCal responses formatted by eluceo lib.
 */
class CalendarResponse implements Response
{
    /** @var Component */
    private $calendar;

    /**
     * Constructor.
     * @param Component $calendar presentation component
     */
    public function __construct(Component $calendar)
    {
        $this->calendar = $calendar;
    }

    /**
     * Send response to client.
     * @param Nette\Http\IRequest $httpRequest
     * @param Nette\Http\IResponse $httpResponse
     */
    public function send(Nette\Http\IRequest $httpRequest, Nette\Http\IResponse $httpResponse): void
    {
        $httpResponse->setContentType('text/calendar; charset=utf-8');
        $httpResponse->setHeader('Content-Disposition', 'inline; filename="recodex.ics"');
        echo $this->calendar;
    }
}
