<?php

namespace ConectaEnte\Entities;

use Doctrine\ORM\Mapping as ORM;
use MapasCulturais\App;

/**
 * Ente federado habilitado a informar editais ao CultBR: um selo, um token, um CNPJ.
 *
 * Sem propriedade `status`: Entity::setStatus(int) e o MagicSetter interceptam.
 *
 * @ORM\Table(name="conectaente_federative_entity")
 * @ORM\Entity
 * @ORM\entity(repositoryClass="MapasCulturais\Repository")
 */
class FederativeEntity extends \MapasCulturais\Entity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="conectaente_federative_entity_id_seq", allocationSize=1, initialValue=1)
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=true)
     */
    protected $name;

    /**
     * @var integer
     *
     * @ORM\Column(name="seal_id", type="integer", nullable=false)
     */
    protected $sealId;

    /**
     * @var string
     *
     * @ORM\Column(name="token", type="text", nullable=false)
     */
    protected $token;

    /**
     * @var string
     *
     * @ORM\Column(name="cnpj", type="string", length=14, nullable=true)
     */
    protected $cnpj;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="create_timestamp", type="datetime", nullable=false)
     */
    protected $createTimestamp;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="update_timestamp", type="datetime", nullable=true)
     */
    protected $updateTimestamp;

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getSealId()
    {
        return $this->sealId;
    }

    public function setSealId($sealId)
    {
        $this->sealId = (int) $sealId;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function getCnpj()
    {
        return $this->cnpj;
    }

    public function setCnpj($cnpj)
    {
        $this->cnpj = $cnpj;
    }

    public function setUpdateTimestamp(\DateTime $updateTimestamp)
    {
        $this->updateTimestamp = $updateTimestamp;
    }

    // o token é credencial: nunca sai na serialização da entidade (a API usa FederativeEntityService::present)
    public function jsonSerialize()
    {
        $result = parent::jsonSerialize();
        unset($result['token']);

        return $result;
    }

    // não há dono nem agente: só o saasSuperAdmin cria, edita, remove ou consulta
    public function canUser($action, $userOrAgent = null)
    {
        $app = App::i();

        if (!$app->isAccessControlEnabled()) {
            return true;
        }

        $user = $userOrAgent ?: $app->user;

        return $user && !$user->is('guest') && $user->is('saasSuperAdmin');
    }
}
