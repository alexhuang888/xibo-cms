<?php

namespace Xibo\Exception;


class XMDSFault extends \Exception
{
    /**
     * Public Constructor
     *
     * @param string $message
     * @param int $code
     * @param \Exception $previous
     */
    public function __construct(string $faultcode, string $faultstring , $code = 0, \Exception $previous = NULL)
    {
        $message = '[' + faultcode + ']: ' + faultstring;

        parent::__construct($message, $code, $previous);
    }
}