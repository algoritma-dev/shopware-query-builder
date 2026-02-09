<?php

namespace Algoritma\ShopwareQueryBuilder\Exception;

class InsertEntityException extends \Exception
{
    /**
     * @param array<mixed> $errors
     */
    public function __construct(array $errors, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(\json_encode($errors, JSON_THROW_ON_ERROR), $code, $previous);
    }
}
