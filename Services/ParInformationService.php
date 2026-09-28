<?php

namespace ConectaEnte\Services;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Entities\FederativeEntitySeal;
use ConectaEnte\Http\ParInformationResult;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;

/**
 * Lê a árvore do PAR só do cache, por Ente Federado; quem a busca na API é o job de sincronização.
 */
class ParInformationService
{
    const CACHE_KEY_PREFIX = 'conectaente:par-information:federative-entity:';

    public function __construct(private int $cacheTtl = 300)
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

    /**
     * Nulo quando a oportunidade não tem selo de ente federado (ativo) vinculado.
     */
    public function getForOpportunity(Opportunity $opportunity): ?ParInformationResult
    {
        $app = App::i();
        $link = $app->repo(FederativeEntitySeal::class)->findOneByOpportunity($opportunity);

        if (!$link) {
            return null;
        }

        return $this->getForFederativeEntity($link->federativeEntity);
    }

    public function getForFederativeEntity(FederativeEntity $federativeEntity): ParInformationResult
    {
        $app = App::i();
        // mscache: namespace fixo — o job grava sem subsite, e a tela lê de dentro de um
        // uma leitura só, e conferindo o tipo: classe antiga no cache vira __PHP_Incomplete_Class
        $cached = $app->mscache->fetch(self::cacheKey($federativeEntity));

        if ($cached instanceof ParInformationResult) {
            return $cached;
        }

        return ParInformationResult::unavailable();
    }
}
