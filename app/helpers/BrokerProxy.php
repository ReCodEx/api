<?php

namespace App\Helpers;

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
  const EXPECTED_ACK = "ack";

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
   * @return bool Evaluation has been started on remote server when returns TRUE.
   * @throws SubmissionFailedException on any error
   */
  public function startEvaluation(string $jobId, array $hardwareGroups, array $headers = [], string $archiveRemotePath, string $resultRemotePath) {
    $queue = null;
    $poll = null;

    try {
      $queue = new ZMQSocket(new ZMQContext, ZMQ::SOCKET_DEALER, $jobId);
      // Configure socket to not wait at close time
      $queue->setsockopt(ZMQ::SOCKOPT_LINGER, 0);
      $queue->connect($this->brokerAddress);
    } catch (ZMQSocketException $e) {
      throw new SubmissionFailedException("Cannot connect to the Broker.");
    }

    try {
      $poll = new ZMQPoll();
      $poll->add($queue, ZMQ::POLL_IN);
    } catch (ZMQPollException $e) {
      $queue->disconnect($this->brokerAddress);
      throw new SubmissionFailedException("Cannot create ZMQ poll.");
    }

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
      throw new SubmissionFailedException("Uploading solution to the Broker failed or timed out.");
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
    if ($response === null) {
      $queue->disconnect($this->brokerAddress);
      throw new SubmissionFailedException("Receiving response from the broker failed.");
    }

    $queue->disconnect($this->brokerAddress);
    return $response[0] === self::EXPECTED_RESULT;
  }

  /**
   * WORKAROUND for https://github.com/mkoppanen/php-zmq/issues/176
   * Wait until given socket can be read from
   * @param ZMQSocket $queue The socket for which we want to wait
   * @param int $timeout Time limit in milliseconds
   * @return array|null
   * @throws SubmissionFailedException
   */
  private function pollReadWorkaround(ZMQSocket $queue, int $timeout) {
    $timeoutSeconds = $timeout / 1000;
    $limit = microtime(TRUE) + $timeout / 1000;

    do {
      $waitTime = min($limit - microtime(true), $timeoutSeconds / 10);
      if ($waitTime > 0) {
        $waitTimeMicroseconds = $waitTime * 1000 * 1000;
        usleep($waitTimeMicroseconds);
      }

      $result = $queue->recvmulti(ZMQ::MODE_DONTWAIT);

      if ($result !== FALSE) {
        return $result;
      }
    } while (microtime(true) <= $limit);

    return null;
  }
}
