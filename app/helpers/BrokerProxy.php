<?php

namespace App\Helpers;

use App\Exceptions\SubmissionFailedException;

use ZMQ;
use ZMQSocket;
use ZMQContext;
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
    try {
      $queue = new ZMQSocket(new ZMQContext, ZMQ::SOCKET_REQ, $submissionId);
      $queue->connect($this->brokerAddress);
    } catch (ZMQSocketException $e) {
      throw new SubmissionFailedException("Cannot connect to the Broker.");
    }
      
    try {
      // $queue->setSockOpt(ZMQ::SOCKOPT_SNDTIMEO, $this->sendTimeout);
      $queue->sendmulti([
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

    // $ack = NULL;
    // try {
    //   $queue->setSockOpt(ZMQ::SOCKOPT_RCVTIMEO, $this->ackTimeout);
    //   $ack = $queue->recv();
    // } catch (ZMQSocketException $e) {
    //   throw new SubmissionFailedException("Broker did not send acknowledgement message.");
    // }

    // if ($ack !== self::EXPECTED_ACK) {
    //   throw new SubmissionFailedException("Broker did not send correct acknowledgement message, expected '" . self::EXPECTED_ACK . "', but received '$ack' instead.");
    // }

    // try {
    //   // send an ACK to unblock the socket
    //   $queue->send(self::EXPECTED_ACK);
    // } catch (ZMQSocketException $e) {
    //   throw new SubmissionFailedException("API server was unable to send acknowledge message to the broker.");
    // }

    try {
      $queue->setSockOpt(ZMQ::SOCKOPT_RCVTIMEO, $this->resultTimeout);
      $result = $queue->recv();
    } catch (ZMQSocketException $e) {
      throw new SubmissionFailedException("Receiving result from the broker failed.");
    }

    return $result === self::EXPECTED_RESULT;
  }

}
