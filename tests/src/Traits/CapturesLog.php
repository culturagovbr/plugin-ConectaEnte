<?php

namespace Tests\ConectaEnte\Traits;

use MapasCulturais\App;
use Monolog\Handler\TestHandler;

trait CapturesLog
{
    private ?TestHandler $capturedLog = null;

    protected function captureLog(): TestHandler
    {
        App::i()->log->pushHandler($this->capturedLog = new TestHandler());

        return $this->capturedLog;
    }

    // o logger da App sobrevive ao teste: o handler precisa sair junto com ele
    protected function popCapturedLog(): void
    {
        if ($this->capturedLog) {
            App::i()->log->popHandler();
            $this->capturedLog = null;
        }
    }
}
