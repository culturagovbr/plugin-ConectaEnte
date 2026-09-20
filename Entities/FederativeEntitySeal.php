<?php

namespace ConectaEnte\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * Vínculo entre um ente federado e um selo: é ele que traz a oportunidade para a integração.
 *
 * @property int $id
 * @property FederativeEntity $federativeEntity
 * @property \MapasCulturais\Entities\Seal $seal
 * @property \DateTime $createTimestamp
 *
 * @ORM\Table(name="conectaente_federative_entity_seal")
 * @ORM\Entity(repositoryClass="MapasCulturais\Repository")
 */
class FederativeEntitySeal extends \MapasCulturais\Entity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="conectaente_federative_entity_seal_id_seq", allocationSize=1, initialValue=1)
     */
    protected $id;

    /**
     * @var FederativeEntity
     *
     * @ORM\ManyToOne(targetEntity="ConectaEnte\Entities\FederativeEntity", inversedBy="seals")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="federative_entity_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * })
     */
    protected $federativeEntity;

    /**
     * @var \MapasCulturais\Entities\Seal
     *
     * @ORM\ManyToOne(targetEntity="MapasCulturais\Entities\Seal", fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="seal_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * })
     */
    protected $seal;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="create_timestamp", type="datetime", nullable=false)
     */
    protected $createTimestamp;

    protected function canUserCreate($user)
    {
        return $user->is('saasSuperAdmin');
    }

    protected function canUserModify($user)
    {
        return $user->is('saasSuperAdmin');
    }

    protected function canUserRemove($user)
    {
        return $user->is('saasSuperAdmin');
    }
}
