<?php

namespace Prophetz\Vk\Exception;

class UnknownFieldResponse extends \Exception
{
    protected $message = 'Unknown field in response: ';
    protected $code;
    protected $previous;

    public function __construct($field)
    {
        $message = $this->message . $field;

        parent::__construct($message, $this->code, $this->previous);
    }


}