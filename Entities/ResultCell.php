<?php

namespace ConectaEnte\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * O valor que quem controla o edital deu a uma célula da grade de contemplados.
 * @property int $id
 * @property int $opportunityId
 * @property int $registrationId
 * @property string $columnKey
 * @property string|null $value
 * @property \DateTime $updateTimestamp
 * @ORM\Table(name="conectaente_result_row")
 * @ORM\Entity(repositoryClass="MapasCulturais\Repository")
 */
class ResultCell extends \MapasCulturais\Entity
{
    /**
     * @var integer
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="conectaente_result_row_id_seq", allocationSize=1, initialValue=1)
     */
    protected $id;

    /**
     * @var integer
     * @ORM\Column(name="opportunity_id", type="integer", nullable=false)
     */
    protected $opportunityId;

    /**
     * @var integer
     * @ORM\Column(name="registration_id", type="integer", nullable=false)
     */
    protected $registrationId;

    /**
     * @var string
     * @ORM\Column(name="column_key", type="string", length=100, nullable=false)
     */
    protected $columnKey;

    /**
     * @var string|null
     * @ORM\Column(name="value", type="text", nullable=true)
     */
    protected $value;

    /**
     * @var \DateTime
     * @ORM\Column(name="update_timestamp", type="datetime", nullable=false)
     */
    protected $updateTimestamp;

    // sem dono, o genérico libera qualquer sessão autenticada — e view, remove e @control nem chegam a ele
    protected function canUserView($user)
    {
        return false;
    }

    protected function canUserRemove($user)
    {
        return false;
    }

    protected function canUser_control($user)
    {
        return false;
    }

    protected function genericPermissionVerification($user)
    {
        return false;
    }
}
