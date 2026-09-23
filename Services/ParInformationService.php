<?php

namespace ConectaEnte\Services;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Entities\FederativeEntitySeal;
use ConectaEnte\Http\ParInformationResult;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;

/**
 * Lê a árvore do PAR do ente ligado a uma oportunidade pelo selo, só do
 * cache (por ente, não por oportunidade — entes com várias oportunidades
 * compartilham o mesmo dado). Nunca chama a API: quem popula o cache é o
 * `Jobs\ParInformationSyncJob`, fora do caminho da requisição do usuário.
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
        $key = self::cacheKey($federativeEntity);

        if ($app->cache->contains($key)) {
            return $app->cache->fetch($key);
        }

        return ParInformationResult::unavailable();
    }
}
