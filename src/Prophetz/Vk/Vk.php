<?php

namespace Prophetz\Vk;

use Prophetz\Anticaptcha\Anticaptcha;
use Prophetz\Curl\Curl;

class Vk
{
    /** @var Curl  */
    private $curl;
    /** @var Anticaptcha  */
    private $anticaptcha;

    private $requestNumber = 0;

    const CAPTCHA_ERROR = 1;
    const TOKEN_ERROR = 2;
    const UNKNOWN_ERROR = 3;
    const HTTP_AUTH_ERROR = 4;
    const VALIDATION_REQUIRED_ERROR = 5;

    /**
     * @param Curl $curl
     * @param Anticaptcha $anticaptcha
     */
    public function __construct(Curl $curl, Anticaptcha $anticaptcha)
    {
        $this->curl = $curl;
        $this->anticaptcha = $anticaptcha;
    }


    public function getStatus($token)
    {
        $response = $this->send('status.get', array(), $token);
        if (isset($response['text'])) {
            return $response['text'];
        } else {
            return false;
        }
    }

    public function setStatus($status, $token)
    {
        $result = $this->send('status.set', array('text' => $status), $token);

        return $result;
    }

    public function joinGroup($group, $account)
    {
        $result = $this->send('groups.join', array('group_id' => $group), $account->getToken());

        return $result;
    }

    public function writePost($account, $ownerId, $message)
    {
        $result = $this->send('wall.post', array('owner_id' => $ownerId, 'message' => $message), $account->getToken());

        return $result;
    }

    public function enterGroup($account, $groupId)
    {
        $result = $this->send('groups.join', array('group_id' => $groupId), $account->getToken());

        return $result;
    }

    public function addFriend($account, $userId)
    {
        $result = $this->send('friends.add', array('user_id' => $userId), $account->getToken());

        return $result;
    }

    public function addLike($account, $type, $ownerId, $itemId)
    {
        $result = $this->send('likes.add', array('type' => $type, 'owner_id' => $ownerId, 'item_id' => $itemId), $account->getToken());

        return $result;
    }


    public function exitGroup($account, $groupId)
    {
        $result = $this->send('groups.leave', array('group_id' => $groupId), $account->getToken());

        return $result;
    }

    public function addLikes($account, $ownerId, $itemId, $type)
    {
        $result = $this->send('likes.add', array('owner_id' => $ownerId, 'item_id' => $itemId, 'type' => $type), $account->getToken());

        return $result;
    }

    public function usersGet($ids, $fields)
    {
        $ids = implode(',', $ids);
        $fields = implode(',', $fields);

        $result = $this->send('users.get', array('user_ids' => $ids, 'fields' => $fields));

        return $result;
    }

    public function getFriendsCount($userId)
    {
        $result = $this->send('friends.get', array('user_id' => $userId));

        if (!is_null($result)) {
            $count = count($result);
        } else {
            $count = null;
        }

        return $count;
    }

    public function getGroupsWhereAdmin($userId, $token)
    {
        $result = $this->send('groups.get', array('user_id' => $userId, 'filter' => 'admin'), $token);

        return $result;
    }

    public function getGroupsWhereEditor($userId, $token)
    {
        $result = $this->send('groups.get', array('user_id' => $userId, 'filter' => 'editor'), $token);

        return $result;
    }

    public function getGroupsWhereModer($userId, $token)
    {
        $result = $this->send('groups.get', array('user_id' => $userId, 'filter' => 'moder'), $token);

        return $result;
    }

    public function getGroupsInfo($groupIds, $fields)
    {
        $result = $this->send('groups.getById', array('group_ids' => $groupIds, 'fields' => $fields));

        return $result;
    }

    public function getCountries()
    {
        $result = $this->send('database.getCountries', array('need_all' => 1, 'count' => 300));

        return $result;
    }

    public function getCityName($cityIds)
    {
        $result = $this->send('database.getCitiesById', array('city_ids' => $cityIds));

        return $result;
    }


    public function likesGetList($params)
    {
        $result = $this->send('likes.getList', $params);

        return $result;
    }

    public function wallGet($params)
    {
        $result = $this->send('wall.get', $params);

        return $result;
    }


    /**
     * Отправка запроса к апи VK
     * @param $method string
     * @param $params array
     * @param $token string
     * @return null|array
     */
    public function send($method, $params, $token = null)
    {
        $urlParams = array();

        //echo "Начало запроса \n";
        $url = "https://api.vk.com/method/$method";

        if (!is_null($token)) {
            $urlParams[] = "access_token=$token";
        }

        $urlParams[] = "v=5.25";

        $url = $url.'?'.implode('&', $urlParams);

        //var_dump("Отсылаемые параметры:\n");
        //($params);

        $response = $this->curl->init($url)->setPostFields($params)->exec()->getData();
        $response = json_decode($response, true);

        // if connection error - continue
        if (!is_array($response)) {
            return false;
        }

        //echo "Ответ:\n";
        //var_dump($response);

        $check = $this->checkResponse($response);

        //echo "Результат проверки:\n";
        //var_dump($check);

        if ($check['success'] == true) {
            //echo "Возвращаем: \n";
            $this->requestNumber = 0;
            return $response['response'];
        } else {
            switch ($check['error']) {
                case $this::CAPTCHA_ERROR:
                    // получаем текст капчи
                    $captchaText = $this->anticaptcha->decode($response['error']['captcha_img']);
                    $captcha = array('captcha_key' => $captchaText, 'captcha_sid' => $response['error']['captcha_sid']);
                    echo "Текст капчи: $captchaText\n";
                    // отправляем повторный запрос
                    echo "Отправляем повторный запрос\n";
                    $this->requestNumber++;
                    if ($this->requestNumber >= 2) {
                        $this->requestNumber = 0;
                        return null;
                    }
                    return $this->send($method, array_merge($captcha, $params), $token);
                    break;
                case $this::TOKEN_ERROR:
                    // помечаем аккаунт забаненым, если запрос от аккаунта
                    if (!is_null($token)) {
                        $em = $this->getDoctrine()->getManager();
                        $account = $em->getRepository('ProphetzAccountBundle:Account')->findOneBy(array('token' => $token));
                        if (!is_null($account)) {
                            $account->setIsBlocked(true);
                            $em->flush();
                        }
                        $this->requestNumber = 0;
                        return null;
                    }
                    break;
                default:
                    //echo "Не найдено соответствие\n";
                    //var_dump($check);
                    break;
            }

            $this->requestNumber = 0;
            return null;
        }
    }

    /**
     * Функция проверяет результат запроса к VK на ошибки
     * @param $response array
     * @return array
     */
    private function checkResponse($response)
    {
        //echo "Проверяемый ответ:\n";
        //var_dump($response);

        // если VK вернул код ошибки
        if (isset($response['error']['error_code'])) {

            switch ($response['error']['error_code']) {
                case "5":
                    echo 'умер токен';
                    return array('success' => false, 'error' => $this::TOKEN_ERROR);
                    break;
                case "14":
                    echo 'капча!';
                    return array('success' => false, 'error' => $this::CAPTCHA_ERROR);
                    break;
                case "16":
                    echo 'HTTP authorization failed';
                    return array('success' => false, 'error' => $this::HTTP_AUTH_ERROR);
                    break;
                case "17":
                    echo 'Validation required';
                    return array('success' => false, 'error' => $this::VALIDATION_REQUIRED_ERROR);
                    break;
                default:
                    //echo 'Неизвестная ошибка';
                    return array('success' => false, 'error' => $this::UNKNOWN_ERROR);
                    break;
            }
        } else {
            // если VK не вернул код ошибки
            return array('success' => true);
        }
    }
}
