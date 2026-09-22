<?php

namespace ConectaEnte\Services;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Entities\FederativeEntitySeal;
use ConectaEnte\Http\ParInformationResult;
use ConectaEnte\Plugin;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;

/**
 * Resolve a árvore do PAR do ente ligado a uma oportunidade pelo selo, com
 * cache por ente (não por oportunidade: entes com várias oportunidades
 * compartilham o mesmo fetch).
 */
class ParInformationService
{
    const CACHE_KEY_PREFIX = 'conectaente:par-information:federative-entity:';

    public function __construct(private int $cacheTtl = 300)
    {
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
        $key = self::CACHE_KEY_PREFIX . $federativeEntity->id;

        if ($app->cache->contains($key)) {
            return $app->cache->fetch($key);
        }

        $result = Plugin::instance()->client()->getParInformation($federativeEntity->token);

        // só o desfecho de sucesso (ou "não existe aqui") vale a pena guardar;
        // falha de rede/token não deve grudar no cache e mascarar uma correção.
        if (!$result->unreachable) {
            $app->cache->save($key, $result, $this->cacheTtl);
        }

        return $result;
    }
}
