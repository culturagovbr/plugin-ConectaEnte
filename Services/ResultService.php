<?php

namespace ConectaEnte\Services;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Http\ApiRead;
use ConectaEnte\Http\SendResult;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;

final class ResultService
{
    const STRUCTURE_CACHE_SECONDS = 300;

    // arquivado segue: o edital encerrado ainda presta contas do resultado
    const STATUSES_WITH_RESULT = [Opportunity::STATUS_ENABLED, Opportunity::STATUS_ARCHIVED];

    public function __construct(private Plugin $plugin)
    {
    }

    /**
     * O estado do resultado, conferindo antes o que é local e só depois o que exige o CultBR.
     */
    public function stateOf(Opportunity $opportunity): ResultState
    {
        $federativeEntity = $opportunity->parent ? null : $this->plugin->sealedOpportunity()->federativeEntityOf($opportunity);

        if (!$federativeEntity) {
            return ResultState::of(ResultState::NO_TAB);
        }

        return $this->localBlock($opportunity, $federativeEntity) ?? $this->remoteState($opportunity, $federativeEntity);
    }

    /** A estrutura do formulário lida agora no CultBR, sem cache: é a que vale no envio. */
    public function freshStructure(Opportunity $opportunity): ApiRead
    {
        $federativeEntity = $this->plugin->sealedOpportunity()->federativeEntityOf($opportunity);
        $read = $this->plugin->client()->getResultFormStructure($federativeEntity->token, (int) $opportunity->getMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID));

        if ($read->body !== null) {
            App::i()->mscache->save($this->structureCacheKey($opportunity), $read->body, self::STRUCTURE_CACHE_SECONDS);
        }

        return $read;
    }

    private function localBlock(Opportunity $opportunity, FederativeEntity $federativeEntity): ?ResultState
    {
        $sentBy = $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_FEDERATIVE_ENTITY_DOCUMENT);

        return match (true) {
            !in_array((int) $opportunity->status, self::STATUSES_WITH_RESULT, true) => ResultState::of(ResultState::UNAVAILABLE, 'status'),
            !$opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID) => ResultState::of(ResultState::NO_PAR_EDITAL),
            $sentBy && $sentBy !== $federativeEntity->document => ResultState::of(ResultState::FEDERATIVE_ENTITY_CHANGED),
            !$opportunity->lastPhase?->publishedRegistrations => ResultState::of(ResultState::AWAITING_FINAL_PUBLICATION),
            default => null,
        };
    }

    private function remoteState(Opportunity $opportunity, FederativeEntity $federativeEntity): ResultState
    {
        $actionTypeRead = $this->ensureActionType($opportunity, $federativeEntity);

        if ($actionTypeRead) {
            return ResultState::of(ResultState::CULTBR_UNAVAILABLE, $this->unavailableReason($actionTypeRead));
        }

        $cached = App::i()->mscache->fetch($this->structureCacheKey($opportunity));
        $read = is_array($cached) ? ApiRead::ok($cached) : $this->freshStructure($opportunity);

        return match (true) {
            $read->body !== null => ResultState::available($read->body),
            $read->notFound => ResultState::of(ResultState::NO_FORM),
            default => ResultState::of(ResultState::CULTBR_UNAVAILABLE, $this->unavailableReason($read)),
        };
    }

    // o tipo de ação só vem no eco do envio do edital; para o acervo sem ele, o modelo de planilha o devolve
    private function ensureActionType(Opportunity $opportunity, FederativeEntity $federativeEntity): ?ApiRead
    {
        if ($opportunity->getMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID)) {
            return null;
        }

        $read = $this->plugin->client()->getResultSpreadsheetModel($federativeEntity->token, (int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID));
        $actionType = SendResult::actionTypeOf($read->body);

        if (!$actionType) {
            return $read->body !== null ? ApiRead::rejected('sem tipo de ação') : $read;
        }

        // saveMetadata, nunca save: salvar a oportunidade selada a reenviaria
        $opportunity->setMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID, (string) $actionType);
        $opportunity->saveMetadata();

        return null;
    }

    private function unavailableReason(ApiRead $read): string
    {
        return $read->unreachable ? 'unreachable' : 'rejected';
    }

    private function structureCacheKey(Opportunity $opportunity): string
    {
        return 'conectaente:result-form:' . (int) $opportunity->getMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID);
    }
}
