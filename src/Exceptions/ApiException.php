<?php

namespace Dskripchenko\LaravelApi\Exceptions;

use Throwable;
use Exception;

/**
 * Class ApiException
 * @package Dskripchenko\LaravelApi\Components
 */
class ApiException extends Exception
{
    /**
     * @var string $errorKey
     */
    protected $errorKey;

    /**
     * @return string
     */
    public function getErrorKey(): string
    {
        return $this->errorKey;
    }

    /**
     * ApiException constructor.
     * @param string $errorKey
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $errorKey, $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorKey = $errorKey;
    }
}
