<?php

namespace ConectaEnte\Migrations;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Entities\LogAttempt;
use ConectaEnte\Http\SendAcceptance;
use ConectaEnte\Http\SendResult;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use ConectaEnte\Services\SendOutcome;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;

/**
 * Recupera do histórico, para os editais já enviados, o id no CultBR, o tipo de ação e o CNPJ de quem enviou.
 */
final class RecoverParEditalOrigin
{
    /** Preenche só o que falta; devolve quantas oportunidades mudaram. */
    public static function run(): int
    {
        $app = App::i();
        $changed = 0;

        foreach (self::lastAcceptedPerOpportunity() as $opportunityId => $origin) {
            $opportunity = $app->repo(Opportunity::class)->find($opportunityId);

            if ($opportunity && self::fill($opportunity, $origin)) {
                // saveMetadata, nunca save: salvar a oportunidade selada a reenviaria
                $opportunity->saveMetadata();
                $changed++;
            }
        }

        return $changed;
    }

    /** @return array<int, array{id: int, actionType: ?int, document: ?string}> */
    private static function lastAcceptedPerOpportunity(): array
    {
        $attempts = App::i()->repo(LogAttempt::class)->findBy(['outcome' => SendOutcome::SUCCESS], ['id' => 'ASC']);
        $origins = [];

        foreach ($attempts as $attempt) {
            $id = SendAcceptance::parEditalIdOf($attempt->responseBody);

            if ($id === null) {
                continue;
            }

            $payload = json_decode((string) $attempt->requestPayload, true);

            $origins[$attempt->log->opportunityId] = [
                'id' => $id,
                'actionType' => SendResult::actionTypeOf(json_decode($attempt->responseBody, true)),
                'document' => is_array($payload) ? ($payload['ente_federado']['cnpj'] ?? null) : null,
            ];
        }

        return $origins;
    }

    private static function fill(Opportunity $opportunity, array $origin): bool
    {
        $changed = false;

        $owner = Plugin::instance()->opportunitySender()->ownerOfParEdital($origin['id'], $opportunity);

        if (!$opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID) && $owner === null) {
            $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_ID, (string) $origin['id']);
            $changed = true;
        }

        if ($origin['actionType'] && !$opportunity->getMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID)) {
            $opportunity->setMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID, (string) $origin['actionType']);
            $changed = true;
        }

        if (self::isRegistered($origin['document']) && !$opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_FEDERATIVE_ENTITY_DOCUMENT)) {
            $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_FEDERATIVE_ENTITY_DOCUMENT, $origin['document']);
            $changed = true;
        }

        return $changed;
    }

    private static function isRegistered(?string $document): bool
    {
        return $document !== null && App::i()->repo(FederativeEntity::class)->findOneBy(['document' => $document]) !== null;
    }
}
