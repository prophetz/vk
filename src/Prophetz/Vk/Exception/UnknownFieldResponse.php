<?php

namespace Prophetz\Vk\Exception;

class UnknownFieldResponse extends \Exception
{
    private $message = 'Unknown field in response: ';
    private $code;
    private $previous;

    public function __construct($field)
    {
        $message = $this->message . $field;

        parent::__construct($message, $this->code, $this->previous);
    }


}