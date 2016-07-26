<?php

namespace Prophetz\Vk;

use Prophetz\Anticaptcha\Anticaptcha;
use Prophetz\Curl\Curl;
use Prophetz\Vk\Exception\RequestError;
use Prophetz\Vk\Exception\UnknownFieldResponse;

class Client
{
    /** @var Curl  */
    private $curl;
    /** @var Anticaptcha  */
    private $anticaptcha;
    /** @var array */
    private $lastQuery;
    /** @var array */
    private $response;

    const CAPTCHA_ERROR = 1;
    const TOKEN_ERROR = 2;
    const UNKNOWN_ERROR = 3;
    const HTTP_AUTH_ERROR = 4;
    const VALIDATION_REQUIRED_ERROR = 5;

    private $apiVersion = '5.25';
    private $token;

    public function __construct(Curl $curl, Anticaptcha $anticaptcha, $token)
    {
        $this->curl = $curl;
        $this->anticaptcha = $anticaptcha;
        $this->token = $token;
    }

    /**
     * @param $method
     * @param $params
     * @return Client
     * @throws \Exception
     */
    public function send($method, $params)
    {
        $this->setLastQuery(array(
            'method' => $method,
            'params' => $params,
        ));

        $url = $this->createRequestUrl($method);

        $response = $this->curl->init($url)->setPostFields($params)->exec()->getData();

        if (!is_array($response)) {

            throw new RequestError();
        }

        $check = $this->checkResponse($response);

        if ($check['success'] == true) {

            $this->requestNumber = 0;
            $this->response = $response['response'];

            return $this;
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

    /**
     * @param $method
     * @return string
     */
    private function createRequestUrl($method)
    {
        $urlParams = array();
        $url = "https://api.vk.com/method/$method";
        if (!is_null($this->token)) {
            $urlParams[] = 'access_token='.$this->token;
        }
        $urlParams[] = "v=".$this->apiVersion;
        $url = $url.'?'.implode('&', $urlParams);

        return $url;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getResponseField($field)
    {
        if (!isset($this->response[$field])) {
            throw new UnknownFieldResponse($field);
        }

        return $this->response[$field];
    }


    /**
     * @return string
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    /**
     * @param string $apiVersion
     */
    public function setApiVersion($apiVersion)
    {
        $this->apiVersion = $apiVersion;
    }

    /**
     * @return mixed
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param mixed $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @return array
     */
    public function getLastQuery()
    {
        return $this->lastQuery;
    }

    /**
     * @param array $lastQuery
     */
    public function setLastQuery($lastQuery)
    {
        $this->lastQuery = $lastQuery;
    }
}