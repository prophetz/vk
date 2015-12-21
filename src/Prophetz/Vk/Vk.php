<?php

namespace Prophetz\Vk;

use Prophetz\Anticaptcha\Anticaptcha;
use Prophetz\Curl\Curl;
use Prophetz\Vk\Entity\Like;

class Vk
{
    /** @var Like  */
    private $likes;
    /** @var Client */
    private $client;

    /**
     * @param Curl $curl
     * @param Anticaptcha $anticaptcha
     * @param null $token
     */
    public function __construct(Curl $curl, Anticaptcha $anticaptcha, $token = null)
    {
        $client = new Client($curl, $anticaptcha, $token);
        $this->client = $client;
        $this->likes = new Like($client);
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param Client $client
     */
    public function setClient($client)
    {
        $this->client = $client;
    }
}
