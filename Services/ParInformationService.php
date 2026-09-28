<?php

namespace ConectaEnte\Services;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Entities\FederativeEntitySeal;
use ConectaEnte\Http\Client;
use ConectaEnte\Http\ParInformationResult;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;

/**
 * A árvore do PAR por Ente Federado: do cache enquanto está fresca, da API quando não está.
 */
class ParInformationService
{
    const CACHE_KEY_PREFIX = 'conectaente:par-information:federative-entity:';

    public function __construct(private int $cacheTtl, private Client $client)
    {
    }

    public function cacheTtl(): int
    {
        return $this->cacheTtl;
    }

    public static function cacheKey(FederativeEntity $federativeEntity): string
    {
        return self::CACHE_KEY_PREFIX . $federativeEntity->id;
    }

    /** A árvore para a tela, buscada quando o cache está frio; nulo sem selo de Ente Federado. */
    public function getForOpportunity(Opportunity $opportunity): ?ParInformationResult
    {
        $federativeEntity = $this->federativeEntityOf($opportunity);

        return $federativeEntity ? $this->getForFederativeEntity($federativeEntity) : null;
    }

    /** Só o que está à mão: quem salva o edital não pode ficar preso esperando o CultBR. */
    public function cachedForOpportunity(Opportunity $opportunity): ?ParInformationResult
    {
        $federativeEntity = $this->federativeEntityOf($opportunity);

        if (!$federativeEntity) {
            return null;
        }

        return $this->cached($federativeEntity) ?? ParInformationResult::unavailable();
    }

    /** O que o job deixou para trás, sem chamar a API. */
    public function cachedForFederativeEntity(FederativeEntity $federativeEntity): ?ParInformationResult
    {
        return $this->cached($federativeEntity);
    }

    public function getForFederativeEntity(FederativeEntity $federativeEntity): ParInformationResult
    {
        return $this->cached($federativeEntity) ?? $this->fetch($federativeEntity);
    }

    /** Busca na API e guarda; falha não entra no cache, e volta inteira para a tela nomear o motivo. */
    public function fetch(FederativeEntity $federativeEntity): ParInformationResult
    {
        $result = $this->client->getParInformation($federativeEntity->token, $federativeEntity->document);

        if ($result->tree || $result->notFound) {
            App::i()->mscache->save(self::cacheKey($federativeEntity), $result, $this->cacheTtl);
        }

        return $result;
    }

    private function federativeEntityOf(Opportunity $opportunity): ?FederativeEntity
    {
        return App::i()->repo(FederativeEntitySeal::class)->findOneByOpportunity($opportunity)?->federativeEntity;
    }

    // mscache tem namespace fixo: o job grava sem subsite e a tela lê de dentro de um
    private function cached(FederativeEntity $federativeEntity): ?ParInformationResult
    {
        $cached = App::i()->mscache->fetch(self::cacheKey($federativeEntity));

        return $cached instanceof ParInformationResult ? $cached : null;
    }
}
