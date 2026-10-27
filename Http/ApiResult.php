<?php

namespace ConectaEnte\Http;

/**
 * Status HTTP e corpo de uma ação do controller, para a regra ficar testável sem o App.
 */
class ApiResult
{
    private $status;
    private $body;

    private function __construct($status, $body)
    {
        $this->status = $status;
        $this->body = $body;
    }

    public static function ok($body, $status = 200)
    {
        return new self($status, $body);
    }

    /**
     * Mesmo formato do `Controller::errorJson()` do core.
     */
    public static function error($status, $data)
    {
        return new self($status, ['error' => true, 'data' => $data]);
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function isError()
    {
        return $this->status >= 400;
    }
}
