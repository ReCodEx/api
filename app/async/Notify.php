<?php

namespace App\Async;

use Nette;
use Nette\Utils\Arrays;
use LogicException;

/**
 * Helper that wraps inotify functions and also handles situations when the inotify is not available.
 */
class Notify
{
    use Nette\SmartObject;

    /**
     * Checks whether inotify functions are available (the extension package is present).
     * @return bool true if inotify interface is present
     */
    public static function isAvailable(): bool
    {
        $requirements = [ 'inotify_init', 'inotify_add_watch', 'inotify_read', 'inotify_queue_len', 'stream_select' ];
        foreach ($requirements as $reqFnc) {
            if (!function_exists($reqFnc)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @var string|null path to the inotify file used to wake up the worker (null = no inotify)
     */
    private $inotifyFile = null;

    /**
     * @var resource|null the resource that represents initialized inotify instance
     */
    private $inotifyStream = null;

    /**
     * @var int|null the resource that represents the watchdog attached to the file
     */
    private $watchDescriptor = null;


    public function __construct($config)
    {
        $inotify = (bool)Arrays::get($config, "inotify", false);
        if ($inotify && !self::isAvailable()) {
            $inotify = false;
        }
        $this->inotifyFile = $inotify ? Arrays::get($config, "inotifyFile", null) : null;
    }

    /**
     * Initialize inotify and starts watching for events on selected file.
     */
    public function init(): void
    {
        if ($this->inotifyFile) {
            if (!file_exists($this->inotifyFile)) {
                touch($this->inotifyFile);
                chmod($this->inotifyFile, 0666);
            }

            $this->inotifyStream = inotify_init();
            $this->watchDescriptor = inotify_add_watch($this->inotifyStream, $this->inotifyFile, IN_ATTRIB);
        }
    }

    /**
     * Clear the internal watchers and inotify instance.
     */
    public function clear(): void
    {
        if ($this->inotifyStream && $this->watchDescriptor) {
            inotify_rm_watch($this->inotifyStream, $this->watchDescriptor);
            fclose($this->inotifyStream);
            $this->inotifyStream = $this->watchDescriptor = null;
        }
    }

    /**
     * Internal helper function that tests whether events are in queue and remove them.
     * @return bool true if events (i.e., notification) was in the queue
     */
    private function isNotifiedInternal(): bool
    {
        $pending = inotify_queue_len($this->inotifyStream);
        if ($pending > 0) {
            inotify_read($this->inotifyStream); // the result is thrown away (we do not need event details)
            return true;
        } else {
            return false;
        }
    }

    /**
     * Non-blocking function that tests whether a notification has been risen.
     * Notifications are cleared once read.
     * @return bool true if notification is present, false otherwise
     * @throws LogicException
     */
    public function isNotified(): bool
    {
        if (!$this->inotifyFile) {
            return false;
        }

        if (!$this->inotifyStream || !$this->watchDescriptor) {
            throw new LogicException(
                "Notification functions must not be invoked before the notify object is initialized."
            );
        }

        return $this->isNotifiedInternal();
    }

    /**
     * Wait (block) until a nofitication is risen or timeout is breached.
     * @param int $timeout maximal waiting time in seconds
     * @return bool true if notifications were collected, false on timeout
     * @throws LogicException
     */
    public function waitForNotification(int $timeout = 1): bool
    {
        if (!$this->inotifyFile) {
            sleep($timeout);
            return false;
        }

        if (!$this->inotifyStream || !$this->watchDescriptor) {
            throw new LogicException(
                "Notification functions must not be invoked before the notify object is initialized."
            );
        }

        $streams = [ $this->inotifyStream ];
        @stream_select($streams, $streams, $streams, $timeout); // will block until event or timeout
        return $this->isNotifiedInternal();
    }

    /**
     * Raise the notification
     */
    public function notify(): void
    {
        if ($this->inotifyFile) {
            touch($this->inotifyFile); // easiest way to trigger attrib modification which leads to inotify event
        }
    }
}
