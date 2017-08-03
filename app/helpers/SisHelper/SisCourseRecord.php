<?php
namespace App\Helpers;

use Exception;
use JsonSerializable;

class SisCourseRecord implements JsonSerializable {
  private static $languages = ['cs', 'en'];

  private $code;

  private $courseId;

  private $affiliation;

  private $captions;

  private $annotations;

  private $year;

  private $term;

  private $sisUserId;

  /**
   * @param $sisUserId
   * @param $data
   * @return SisCourseRecord
   */
  public static function fromArray($sisUserId, $data) {
    $result = new static;
    $result->sisUserId = $sisUserId;

    $result->code = $data["id"];
    $result->courseId = $data["course"];
    $result->affiliation = $data["affiliation"];
    $result->year = $data["year"];
    $result->term = $data["semester"];

    foreach (static::$languages as $language) {
      $result->captions[$language] = $data["caption_" . $language];
      $result->annotations[$language] = $data["annotation_" . $language];
    }

    return $result;
  }

  public function getCaption($lang) {
    if (!array_key_exists($lang, $this->captions)) {
      throw new Exception();
    }

    return $this->captions[$lang];
  }

  public function getAnnotation($lang) {
    if (!array_key_exists($lang, $this->annotations)) {
      throw new Exception();
    }

    return $this->annotations[$lang];
  }

  public function isOwnerStudent() {
    return $this->affiliation === "student";
  }

  public function isOwnerSupervisor() {
    return $this->affiliation === "teacher" || $this->affiliation === "guarantor";
  }

  public function getCode() {
    return $this->code;
  }

  public function getCourseId() {
    return $this->courseId;
  }

  public function jsonSerialize() {
    return [
      'code' => $this->code,
      'courseId' => $this->courseId,
      'captions' => $this->captions,
      'annotations' => $this->annotations
    ];
  }
}