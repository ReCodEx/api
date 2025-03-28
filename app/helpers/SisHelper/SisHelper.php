<?php

namespace App\Helpers;

use InvalidArgumentException;
use Generator;
use Nette;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Nette\Utils\Json;

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
     * @param HandlerStack|null $handler An optional HTTP handler (mainly for unit testing purposes)
     */
    public function __construct($apiBase, $faculty, $secret, ?HandlerStack $handler = null)
    {
        $this->apiBase = $apiBase;
        $this->faculty = $faculty;
        $this->secret = $secret;

        if (!str_ends_with($this->apiBase, '/')) {
            $this->apiBase .= '/';
        }

        $options = [
            'base_uri' => $this->apiBase . 'rozvrhng/rest.php'
        ];

        if ($handler !== null) {
            $options['handler'] = $handler;
        }

        $this->client = new Client($options);
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
        } catch (ClientException $e) {
            throw new InvalidArgumentException("Invalid year or semester number", 0, $e);
        }

        $data = Json::decode($response->getBody()->getContents(), true);

        foreach ($data["events"] as $course) {
            yield SisCourseRecord::fromArray($sisUserId, $course);
        }
    }
}
