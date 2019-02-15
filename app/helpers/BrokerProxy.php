<?php

namespace App\Helpers;

use App\Exceptions\InvalidStateException;
use App\Exceptions\SubmissionFailedException;

use ZMQ;
use ZMQContext;
use ZMQException;
use ZMQPoll;
use ZMQPollException;
use ZMQSocket;
use ZMQSocketException;

use Nette\Utils\Arrays;

/**
 * Helper class for handling connection with broker over ZeroMQ communication channel.
 */
class BrokerProxy {

  const EXPECTED_RESULT = "accept";
  const REJECTED_RESULT = "reject";
  const EXPECTED_ACK = "ack";

  const COMMAND_STATS = "get-runtime-stats";
  const COMMAND_FREEZE = "freeze";
  const COMMAND_UNFREEZE = "unfreeze";

  /** @var string IP address or hostname (with port) of ReCodEx broker */
  private $brokerAddress;

  /** @var int Time limit (milliseconds) how long to wait for broker accept message after submitting new job */
  private $ackTimeout;

  /** @var int Time limit (milliseconds) how long to try sending new job to the broker */
  private $sendTimeout;

  /** @var int Time limit (milliseconds) how long to wait for final broker message if the job can be processed or not */
  private $resultTimeout;

  /**
   * Constructor
   * @param array $config Array with data about broker address and all timeouts
   */
  public function __construct(array $config) {
    $this->brokerAddress = Arrays::get($config, 'address');
    $this->ackTimeout = intval(Arrays::get($config, ['timeouts', 'ack'], 100));
    $this->sendTimeout = intval(Arrays::get($config, ['timeouts', 'send'], 5000));
    $this->resultTimeout = intval(Arrays::get($config, ['timeouts', 'result'], 1000));
  }

  /**
   * Start evaluation of new job. This means sending proper message to broker that we want this new
   * job to be evaluated, receive confirmation that the message was successfuly received and finally
   * receive confirmation if the evaluation can be processed or not (for example if there is worker
   * for that hwgroup available).
   * @param string $jobId Unique identifier of the new job
   * @param array $hardwareGroups Hardware groups of this submission
   * @param array $headers Headers used to further specify which workers can process the job
   * @param string $archiveRemotePath URL of the archive with source codes and job evaluation configuration
   * @param string $resultRemotePath URL where to store resulting archive of whole evaluation
   * @return bool Evaluation has been started on remote server when returns true.
   * @throws SubmissionFailedException on any error
   * @throws ZMQSocketException
   * @throws InvalidStateException
   */
  public function startEvaluation(string $jobId, array $hardwareGroups, array $headers, string $archiveRemotePath, string $resultRemotePath) {
    $queue = $this->brokerConnect($jobId);

    $hwGroup = implode('|', $hardwareGroups);
    $message = [];
    $message[] = "eval";
    $message[] = $jobId;
    $message[] = "hwgroup=$hwGroup";

    foreach ($headers as $key => $value) {
      $message[] = sprintf("%s=%s", $key, $value);
    }

    $message[] = "";
    $message[] = $archiveRemotePath;
    $message[] = $resultRemotePath;

    try {
      $queue->setsockopt(ZMQ::SOCKOPT_SNDTIMEO, $this->sendTimeout);
      $queue->sendmulti($message);
    } catch (ZMQSocketException $e) {
      $queue->disconnect($this->brokerAddress);
      throw new SubmissionFailedException("Uploading solution to the Broker failed or timed out - {$e->getMessage()}");
    }

    $ack = $this->pollReadWorkaround($queue, $this->ackTimeout);
    if ($ack === null) {
      $queue->disconnect($this->brokerAddress);
      throw new SubmissionFailedException("Broker did not send acknowledgement message.");
    }

    if ($ack[0] !== self::EXPECTED_ACK) {
      $queue->disconnect($this->brokerAddress);
      throw new SubmissionFailedException("Broker did not send correct acknowledgement message, expected '" . self::EXPECTED_ACK . "', but received '$ack' instead.");
    }

    $response = $this->pollReadWorkaround($queue, $this->resultTimeout);
    if ($response === null || count($response) < 1) {
      $queue->disconnect($this->brokerAddress);
      throw new SubmissionFailedException("Receiving response from the broker failed.");
    }

    // just close the damn connection
    $queue->disconnect($this->brokerAddress);

    if ($response[0] === self::REJECTED_RESULT) {
      $rejectMessage = "The broker rejected our request";
      if (count($response) > 1) {
        array_shift($response);
        $rejectMessage .= ": " . implode(" ", $response);
      }
      throw new SubmissionFailedException($rejectMessage);
    }
    return $response[0] === self::EXPECTED_RESULT;
  }

  /**
   * Get broker stats.
   * @return string[]
   * @throws InvalidStateException
   * @throws ZMQSocketException
   */
  public function getStats(): array {
    $queue = $this->brokerConnect();

    try {
      $queue->setsockopt(ZMQ::SOCKOPT_SNDTIMEO, $this->sendTimeout);
      $queue->sendmulti([self::COMMAND_STATS]);
    } catch (ZMQSocketException $e) {
      $queue->disconnect($this->brokerAddress);
      throw new InvalidStateException("Stats retrieval failed or timed out - {$e->getMessage()}");
    }

    $response = $this->pollReadWorkaround($queue, $this->resultTimeout);
    if ($response === null) {
      $queue->disconnect($this->brokerAddress);
      throw new InvalidStateException("Receiving response from the broker failed.");
    }

    $queue->disconnect($this->brokerAddress);

    // check returned stats if they are in correct format
    $response = array_values($response);
    if (count($response) % 2 !== 0) {
      throw new InvalidStateException("Malformed stats returned by broker");
    }

    // process stats into associative array
    $results = [];
    for ($i = 0; $i < count($response); $i += 2) {
      $results[$response[$i]] = $response[$i + 1];
    }

    return $results;
  }

  /**
   * Freeze broker and its execution.
   * @throws InvalidStateException
   * @throws ZMQSocketException
   */
  public function freeze(): void {
    $queue = $this->brokerConnect();

    try {
      $queue->setsockopt(ZMQ::SOCKOPT_SNDTIMEO, $this->sendTimeout);
      $queue->sendmulti([self::COMMAND_FREEZE]);
    } catch (ZMQSocketException $e) {
      $queue->disconnect($this->brokerAddress);
      throw new InvalidStateException("Freeze failed or timed out - {$e->getMessage()}");
    }

    $ack = $this->pollReadWorkaround($queue, $this->ackTimeout);
    if ($ack === null || count($ack) < 1 || $ack[0] !== self::EXPECTED_ACK) {
      $queue->disconnect($this->brokerAddress);
      throw new InvalidStateException("Broker did not send acknowledgement message.");
    }

    $queue->disconnect($this->brokerAddress);
  }

  /**
   * Unfreeze broker and its execution.
   * @throws ZMQSocketException
   * @throws InvalidStateException
   */
  public function unfreeze() {
    $queue = $this->brokerConnect();

    try {
      $queue->setsockopt(ZMQ::SOCKOPT_SNDTIMEO, $this->sendTimeout);
      $queue->sendmulti([self::COMMAND_UNFREEZE]);
    } catch (ZMQSocketException $e) {
      $queue->disconnect($this->brokerAddress);
      throw new InvalidStateException("Unfreeze failed or timed out - {$e->getMessage()}");
    }

    $ack = $this->pollReadWorkaround($queue, $this->ackTimeout);
    if ($ack === null || count($ack) < 1 || $ack[0] !== self::EXPECTED_ACK) {
      $queue->disconnect($this->brokerAddress);
      throw new InvalidStateException("Broker did not send acknowledgement message.");
    }

    $queue->disconnect($this->brokerAddress);
  }

  /**
   * Connect to the broker and setup the connection.
   * @param string|null $persistentId
   * @return ZMQSocket
   * @throws InvalidStateException
   * @throws ZMQSocketException
   */
  private function brokerConnect($persistentId = null): ZMQSocket {
    $queue = null;
    $poll = null;

    try {
      $queue = new ZMQSocket(new ZMQContext(), ZMQ::SOCKET_DEALER, $persistentId);
      // Configure socket to not wait at close time
      $queue->setsockopt(ZMQ::SOCKOPT_LINGER, 0);
      $queue->connect($this->brokerAddress);
    } catch (ZMQSocketException $e) {
      throw new InvalidStateException("Cannot connect to the Broker - {$e->getMessage()}");
    }

    try {
      $poll = new ZMQPoll();
      $poll->add($queue, ZMQ::POLL_IN);
    } catch (ZMQPollException $e) {
      $queue->disconnect($this->brokerAddress);
      throw new InvalidStateException("Cannot create ZMQ poll - {$e->getMessage()}");
    }

    return $queue;
  }

  /**
   * WORKAROUND for https://github.com/mkoppanen/php-zmq/issues/176
   * Wait until given socket can be read from
   * @param ZMQSocket $queue The socket for which we want to wait
   * @param int $timeout Time limit in milliseconds
   * @return array|null
   * @throws ZMQSocketException
   */
  private function pollReadWorkaround(ZMQSocket $queue, int $timeout) {
    $timeoutSeconds = $timeout / 1000;
    $limit = microtime(true) + $timeout / 1000;

    do {
      $waitTime = min($limit - microtime(true), $timeoutSeconds / 10);
      if ($waitTime > 0) {
        $waitTimeMicroseconds = $waitTime * 1000 * 1000;
        usleep($waitTimeMicroseconds);
      }

      $result = $queue->recvmulti(ZMQ::MODE_DONTWAIT);

      if ($result !== false) {
        return $result;
      }
    } while (microtime(true) <= $limit);

    return null;
  }
}
