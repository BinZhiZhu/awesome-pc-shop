<?php

namespace common\modules\api\procedures;

use Exception;
use Throwable;

class ApiException extends Exception
{

    /**
     * ApiException constructor.
     * @param int $code
     * @param string $message
     * @param Throwable|null $previous
     */
    public function __construct($code = 0, $message = "", Throwable $previous = null)
    {
        //兼容jsonrpc
        if (empty($message)) {
            $message = \common\AppError::getError($code);
        }
        parent::__construct($message, $code, $previous);
    }
}
