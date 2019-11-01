<?php


namespace Dskripchenko\LaravelApi\Components;


use Throwable;

/**
 * Class ApiException
 * @package Dskripchenko\LaravelApi\Components
 */
class ApiException extends \Exception
{
    /**
     * @var string $errorKey
     */
    protected $errorKey;

    /**
     * @return string
     */
    public function getErrorKey(){
        return $this->errorKey;
    }

    /**
     * ApiException constructor.
     * @param string $errorKey
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($errorKey, $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorKey = $errorKey;
    }
}