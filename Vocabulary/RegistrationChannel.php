<?php

namespace ConectaEnte\Vocabulary;

enum RegistrationChannel: string
{
    use TranslatedLabel;

    case EMAIL = 'email';
    case IN_PERSON = 'presencial';
    case MAIL = 'correio';
    case ORAL = 'oral';
    case SYSTEM = 'sistema';
    case OTHER = 'outros';

    /**
     * Texto fixo em pt-br da forma de inscrição.
     */
    public function text(): string
    {
        return match ($this) {
            self::EMAIL => 'E-mail',
            self::IN_PERSON => 'Presencial',
            self::MAIL => 'Correspondência',
            self::ORAL => 'Oralidade',
            self::SYSTEM => 'Sistema digital',
            self::OTHER => 'Outros',
        };
    }
}
