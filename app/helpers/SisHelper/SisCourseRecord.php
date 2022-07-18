<?php

namespace App\Helpers;

use DateTime;
use Exception;
use JsonSerializable;

class SisCourseRecord implements JsonSerializable
{
    private static $languages = ['cs', 'en'];

    private $code;

    private $courseId;

    private $type;

    private $affiliation;

    private $captions;

    private $annotations;

    private $year;

    private $term;

    private $sisUserId;

    private $dayOfWeek;

    private $time;

    private $room;

    private $fortnightly;

    private $oddWeeks;

    private static $typeMap = [
        "P" => "lecture",
        "X" => "lab"
    ];

    /**
     * @param string $sisUserId
     * @param array $data
     * @return SisCourseRecord
     */
    public static function fromArray($sisUserId, $data)
    {
        $result = new SisCourseRecord();
        $result->sisUserId = $sisUserId;

        $result->code = $data["id"];
        $result->courseId = $data["course"];
        $result->affiliation = $data["affiliation"];
        $result->year = $data["year"];
        $result->term = $data["semester"];
        $result->dayOfWeek = $data["day_of_week"] !== null ? intval($data["day_of_week"]) - 1 : null;
        if ($data["time"] !== null) {
            $minutes = intval($data["time"]);
            $result->time = (new DateTime())
                ->setTime(floor($minutes / 60), $minutes % 60)
                ->format("H:i");
        } else {
            $result->time = null;
        }
        $result->room = $data["room"];
        $result->fortnightly = $data["fortnight"];
        $result->oddWeeks = $data["firstweek"] == 1;
        $result->type = array_key_exists($data["type"], self::$typeMap) ? self::$typeMap[$data["type"]] : "unknown";

        foreach (self::$languages as $language) {
            $result->captions[$language] = $data["caption_" . $language];
            $result->annotations[$language] = !empty($data["annotation_" . $language]) ? $data["annotation_" . $language] : '';
        }

        return $result;
    }

    public function getSisUserId()
    {
        return $this->sisUserId;
    }

    public function getCaption($lang)
    {
        if (!array_key_exists($lang, $this->captions)) {
            throw new Exception("Caption for language '$lang' does not exist");
        }

        return $this->captions[$lang];
    }

    public function getAnnotation($lang)
    {
        if (!array_key_exists($lang, $this->annotations)) {
            throw new Exception("Annotation for language '$lang' does not exist'");
        }

        return $this->annotations[$lang];
    }

    public function getTermIdentifier(): string
    {
        return sprintf("%s-%s", $this->year, $this->term);
    }

    public function isOwnerStudent()
    {
        return $this->affiliation === "student";
    }

    public function isOwnerSupervisor()
    {
        return $this->affiliation === "teacher" || $this->affiliation === "guarantor";
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getCourseId()
    {
        return $this->courseId;
    }

    /**
     * @return mixed
     */
    public function getDayOfWeek()
    {
        return $this->dayOfWeek;
    }

    /**
     * @return mixed
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * @return mixed
     */
    public function getRoom()
    {
        return $this->room;
    }

    /**
     * @return mixed
     */
    public function isFortnightly()
    {
        return $this->fortnightly;
    }

    /**
     * @return mixed
     */
    public function getOddWeeks()
    {
        return $this->oddWeeks;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'code' => $this->code,
            'courseId' => $this->courseId,
            'captions' => $this->captions,
            'annotations' => $this->annotations,
            'dayOfWeek' => $this->dayOfWeek,
            'time' => $this->time,
            'room' => $this->room,
            'fortnightly' => $this->fortnightly,
            'oddWeeks' => $this->oddWeeks,
            'type' => $this->type
        ];
    }
}
