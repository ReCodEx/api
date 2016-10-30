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
   * @param string $hardwareGroup Hardware group of this submission
   * @param string $archiveRemotePath URL of the archive with source codes and job evaluation configuration
   * @param string $resultRemotePath URL where to store resulting archive of whole evaluation
   * @return bool Evaluation has been started on remote server when returns TRUE.
   * @throws SubmissionFailedException on any error
   */
  public function startEvaluation(string $jobId, string $hardwareGroup, string $archiveRemotePath, string $resultRemotePath) {
    $queue = NULL;
    $poll = NULL;

    try {
      $queue = new ZMQSocket(new ZMQContext, ZMQ::SOCKET_DEALER, $jobId);
      $queue->connect($this->brokerAddress);
    } catch (ZMQSocketException $e) {
      throw new SubmissionFailedException("Cannot connect to the Broker.");
    }

    try {
      $poll = new ZMQPoll();
      $poll->add($queue, ZMQ::POLL_IN);
    } catch (ZMQPollException $e) {
      throw new SubmissionFailedException("Cannot create ZMQ poll.");
    }

    try {
      $queue->setSockOpt(ZMQ::SOCKOPT_SNDTIMEO, $this->sendTimeout);
      $queue->sendmulti([
        "eval",
        $jobId,
        "hwgroup=$hardwareGroup",
        "",
        $archiveRemotePath,
        $resultRemotePath
      ]);
    } catch (ZMQSocketException $e) {
      throw new SubmissionFailedException("Uploading solution to the Broker failed or timed out.");
    }

    $ackReceived = $this->pollRead($poll, $queue, $this->ackTimeout);

    if (!$ackReceived) {
      throw new SubmissionFailedException("Broker did not send acknowledgement message.");
    }

    $ack = $queue->recvMulti();

    if ($ack[0] !== self::EXPECTED_ACK) {
      throw new SubmissionFailedException("Broker did not send correct acknowledgement message, expected '" . self::EXPECTED_ACK . "', but received '$ack' instead.");
    }

    $responseReceived = $this->pollRead($poll, $queue, $this->resultTimeout);

    if (!$responseReceived) {
      throw new SubmissionFailedException("Receiving response from the broker failed.");
    }

    $response = $queue->recvMulti();

    return $response[0] === self::EXPECTED_RESULT;
  }

  /**
   * Wait until given socket can be read from
   * @param ZMQPoll $poll Polling helper structure
   * @param ZMQSocket $queue The socket for which we want to wait
   * @param int $timeout Time limit in milliseconds
   */
  private function pollRead(ZMQPoll $poll, ZMQSocket $queue, int $timeout) {
    $readable = [];
    $writable = [];

    try {
      $events = $poll->poll($readable, $writable, $timeout);
      $errors = $poll->getLastErrors();
      if (count($errors) > 0) {
        throw new SubmissionFailedException("ZMQ polling returned error(s).");
      }
    } catch (ZMQPollException $e) {
      throw new SubmissionFailedException("ZMQ polling raised an exception.");
    }

    return in_array($queue, $readable);
  }
}
