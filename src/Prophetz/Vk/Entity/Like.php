<?php

namespace Prophetz\Vk\Entity;

use Prophetz\Vk\Client;
use Prophetz\Vk\Vk;

class Like
{
    /** @var Client  */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }


    public function getList($params)
    {
        $result = $this->client->send('likes.getList', $params);

        return $result;
    }

    public function add($params)
    {
        $result = $this->client->send('likes.add', $params);

        return $result;
    }

    public function delete()
    {

    }

    public function isLiked()
    {

    }
}