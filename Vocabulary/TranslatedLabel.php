<?php

namespace ConectaEnte\Vocabulary;

use MapasCulturais\i;

trait TranslatedLabel
{
    /**
     * Texto fixo em pt-br da opção, o que vai ao payload.
     */
    public function text(): string
    {
        return $this->value;
    }

    /**
     * Texto exibido na tela, na língua da instalação.
     */
    public function label(): string
    {
        return i::__($this->text());
    }
}
