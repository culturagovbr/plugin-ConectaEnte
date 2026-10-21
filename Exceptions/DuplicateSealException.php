<?php

namespace ConectaEnte\Exceptions;

class DuplicateSealException extends \RuntimeException
{
    private $sealId;
    private $existingName;

    public function __construct($sealId, $existingName)
    {
        $this->sealId = (int) $sealId;
        $this->existingName = (string) $existingName;

        parent::__construct(sprintf(
            'O selo %d já está associado ao ente "%s".',
            $this->sealId,
            $this->existingName
        ));
    }

    public function getSealId()
    {
        return $this->sealId;
    }

    public function getExistingName()
    {
        return $this->existingName;
    }
}
