<?php

namespace ConectaEnte\Entities;

use Doctrine\ORM\Mapping as ORM;
use MapasCulturais\Entities\User;

/**
 * Um envio de edital ao CultBR, com as tentativas que ele produziu.
 *
 * @property int $id
 * @property string $uuid
 * @property int $opportunityId
 * @property int|null $parEditalId
 * @property string $outcome
 * @property User|null $triggeredByUser
 * @property \DateTime $createTimestamp
 * @property \DateTime|null $updateTimestamp
 * @property LogAttempt[] $attempts
 *
 * @ORM\Table(name="conectaente_log")
 * @ORM\Entity(repositoryClass="MapasCulturais\Repository")
 */
class Log extends \MapasCulturais\Entity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="conectaente_log_id_seq", allocationSize=1, initialValue=1)
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="uuid", type="string", length=36, nullable=false)
     */
    protected $uuid;

    /**
     * @var integer
     *
     * @ORM\Column(name="opportunity_id", type="integer", nullable=false)
     */
    protected $opportunityId;

    /**
     * @var integer|null
     *
     * @ORM\Column(name="par_edital_id", type="integer", nullable=true)
     */
    protected $parEditalId;

    /**
     * @var string
     *
     * @ORM\Column(name="outcome", type="string", length=20, nullable=false)
     */
    protected $outcome;

    /**
     * @var User|null
     *
     * @ORM\ManyToOne(targetEntity="MapasCulturais\Entities\User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="triggered_by_user_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     * })
     */
    protected $triggeredByUser;

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
     * @var LogAttempt[]
     *
     * @ORM\OneToMany(targetEntity="ConectaEnte\Entities\LogAttempt", mappedBy="log")
     * @ORM\OrderBy({"id" = "ASC"})
     */
    protected $attempts;

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
