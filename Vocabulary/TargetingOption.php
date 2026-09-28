<?php

namespace ConectaEnte\Vocabulary;

use MapasCulturais\i;

enum TargetingOption: string
{
    case NOT_TARGETED = '__edital_nao_se_direciona__';
    case ALL_OPTIONS = '__todas_opcoes__';

    /**
     * Texto fixo em pt-br da opção no campo informado, o que vai ao payload.
     */
    public function text(TargetingField $field): string
    {
        return match ($this) {
            self::NOT_TARGETED => $field->notTargetedText(),
            self::ALL_OPTIONS => 'Todas as opções',
        };
    }

    /**
     * Texto exibido na tela, na língua da instalação.
     */
    public function label(TargetingField $field): string
    {
        return i::__($this->text($field));
    }
}
