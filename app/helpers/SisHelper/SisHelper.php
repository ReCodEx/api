<?php

namespace App\Helpers;

use App\Exceptions\InvalidArgumentException;
use Generator;
use Nette;
use GuzzleHttp;
use Nette\Utils\Json;
use Nette\Utils\Strings;

class SisHelper
{
    use Nette\SmartObject;

    private $apiBase;

    private $faculty;

    private $secret;

    private $client;

    /**
     * @param string $apiBase
     * @param string $faculty
     * @param string $secret
     * @param GuzzleHttp\HandlerStack|null $handler An optional HTTP handler (mainly for unit testing purposes)
     */
    public function __construct($apiBase, $faculty, $secret, GuzzleHttp\HandlerStack $handler = null)
    {
        $this->apiBase = $apiBase;
        $this->faculty = $faculty;
        $this->secret = $secret;

        if (!Strings::endsWith($this->apiBase, '/')) {
            $this->apiBase .= '/';
        }

        $options = [
            'base_uri' => $this->apiBase . 'rozvrhng/rest.php'
        ];

        if ($handler !== null) {
            $options['handler'] = $handler;
        }

        $this->client = new GuzzleHttp\Client($options);
    }

    /**
     * @param string $sisUserId
     * @param int|null $year
     * @param int $term
     * @return SisCourseRecord[]|Generator
     * @throws InvalidArgumentException
     */
    public function getCourses($sisUserId, $year = null, $term = 1)
    {
        $salt = join(',', [time(), $this->faculty, $sisUserId]);
        $hash = hash('sha256', "$salt,$this->secret");

        $params = [
            'endpoint' => 'muj_rozvrh',
            'ukco' => $sisUserId,
            'auth_token' => "$salt\$$hash",
            'fak' => $this->faculty,
            'extras' => ['annotations']
        ];

        if ($year !== null) {
            $params['semesters'] = [sprintf("%s-%s", $year, $term)];
        }

        try {
            $response = $this->client->get('', ['query' => $params]);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            throw new InvalidArgumentException("Invalid year or semester number");
        }

        $data = Json::decode($response->getBody()->getContents(), Json::FORCE_ARRAY);

        foreach ($data["events"] as $course) {
            yield SisCourseRecord::fromArray($sisUserId, $course);
        }
    }
}
