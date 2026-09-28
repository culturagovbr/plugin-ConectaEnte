<?php

namespace ConectaEnte\Vocabulary;

enum OpportunityStatus: int
{
    case ENABLED = 1;
    case DRAFT = 0;
    case PHASE = -1;
    case ARCHIVED = -2;
    case DISABLED = -9;
    case TRASH = -10;
    case APPEAL_PHASE = -20;

    /**
     * Nome fixo em pt-br do status.
     */
    public function text(): string
    {
        return match ($this) {
            self::ENABLED => 'Ativado',
            self::DRAFT => 'Rascunho',
            self::PHASE => 'Fase',
            self::ARCHIVED => 'Arquivado',
            self::DISABLED => 'Desabilitado',
            self::TRASH => 'Lixeira',
            self::APPEAL_PHASE => 'Fase de recurso',
        };
    }

    /**
     * Status no formato do contrato: id e nome.
     *
     * @return array{id: int, nome: string}
     */
    public function toPayload(): array
    {
        return ['id' => $this->value, 'nome' => $this->text()];
    }
}
