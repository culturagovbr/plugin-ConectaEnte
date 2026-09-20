<?php

namespace ConectaEnte\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * Ente federado integrado ao CultBR.
 *
 * @property int $id
 * @property string $name
 * @property string $document
 * @property string $token
 * @property \DateTime $createTimestamp
 * @property \DateTime|null $updateTimestamp
 * @property FederativeEntitySeal[] $seals
 *
 * @ORM\Table(name="conectaente_federative_entity")
 * @ORM\Entity(repositoryClass="MapasCulturais\Repository")
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
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="document", type="string", length=14, nullable=false)
     */
    protected $document;

    /**
     * @var string
     *
     * @ORM\Column(name="token", type="text", nullable=false)
     */
    protected $token;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="create_timestamp", type="datetime", nullable=false)
     */
    protected $createTimestamp;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="update_timestamp", type="datetime", nullable=true)
     */
    protected $updateTimestamp;

    /**
     * @var FederativeEntitySeal[]
     *
     * @ORM\OneToMany(targetEntity="ConectaEnte\Entities\FederativeEntitySeal", mappedBy="federativeEntity")
     */
    protected $seals;

    /**
     * CNPJ com a pontuação que o administrador espera ler.
     */
    function getFormattedDocument(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->document);

        if (strlen($digits) !== 14) {
            return (string) $this->document;
        }

        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits);
    }

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
