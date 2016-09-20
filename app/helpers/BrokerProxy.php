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
 * @author  Šimon Rozsíval <simon@rozsival.com>
 */
class BrokerProxy {

  const EXPECTED_RESULT = "accept";
  const EXPECTED_ACK = "ack";

  /** @var string */
  private $brokerAddress;

  /** @var int */
  private $ackTimeout;

  /** @var int */
  private $sendTimeout;

  /** @var int */
  private $resultTimeout;


  public function __construct($config) {
    $this->brokerAddress = Arrays::get($config, 'address');
    $this->ackTimeout = intval(Arrays::get($config, ['timeouts', 'ack'], 100));
    $this->sendTimeout = intval(Arrays::get($config, ['timeouts', 'send'], 5000));
    $this->resultTimeout = intval(Arrays::get($config, ['timeouts', 'result'], 1000));
  }

  /**
   * @param $submissionId
   * @param $archiveRemotePath
   * @param $resultRemotePath
   * @return bool Evaluation has been started on remote server when returns TRUE.
   * @throws SubmissionFailedException
   * @internal param $string
   * @internal param $string
   * @internal param $string
   * @internal param $string
   */
  public function startEvaluation(string $submissionId, string $hardwareGroup, string $archiveRemotePath, string $resultRemotePath) {
    $queue = NULL;
    $poll = NULL;

    try {
      $queue = new ZMQSocket(new ZMQContext, ZMQ::SOCKET_DEALER, $submissionId);
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
        "identity",
        "eval",
        $submissionId,
        "hwgroup=$hardwareGroup",
        "",
        $archiveRemotePath,
        $resultRemotePath
      ]);
    } catch (ZMQSocketException $e) {
      throw new SubmissionFailedException("Uploading solution to the Broker failed or timeouted.");
    }

    $ack = NULL;
    try {
      $queue->setSockOpt(ZMQ::SOCKOPT_RCVTIMEO, $this->ackTimeout);
      $ack = $queue->recvMulti();
    } catch (ZMQException $e) {
      throw new SubmissionFailedException("Broker did not send acknowledgement message.");
    }

    if ($ack !== self::EXPECTED_ACK) {
      throw new SubmissionFailedException("Broker did not send correct acknowledgement message, expected '" . self::EXPECTED_ACK . "', but received '$ack' instead.");
    }

    try {
      $queue->setSockOpt(ZMQ::SOCKOPT_RCVTIMEO, $this->resultTimeout);
      $result = $queue->recvMulti();
    } catch (ZMQSocketException $e) {
      throw new SubmissionFailedException("Receiving result from the broker failed.");
    }

    var_dump($result); exit;

    return $result === self::EXPECTED_RESULT;
  }

}
