<?php

namespace ConectaEnte\Entities;

use Doctrine\ORM\Mapping as ORM;
use MapasCulturais\Entities\User;

/**
 * Um envio ao CultBR, do edital ou do resultado, com as tentativas que ele produziu.
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
 * @property string $action
 * @property int|null $seenResultId
 * @property bool|null $seenResultRead
 * @property string|null $contentHash
 * @property int|null $resultType
 * @property string|null $publicationDate
 * @property bool $posted
 * @property \DateTime|null $startedAt
 *
 * @ORM\Table(name="conectaente_log")
 * @ORM\Entity(repositoryClass="MapasCulturais\Repository")
 */
class Log extends \MapasCulturais\Entity
{
    const ACTION_OPPORTUNITY = 'opportunity';
    const ACTION_RESULT = 'result';

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
     * @var string
     * @ORM\Column(name="action", type="string", length=20, nullable=false)
     */
    protected $action = self::ACTION_OPPORTUNITY;

    /**
     * @var integer|null
     * @ORM\Column(name="seen_result_id", type="integer", nullable=true)
     */
    protected $seenResultId;

    /**
     * @var boolean|null
     * @ORM\Column(name="seen_result_read", type="boolean", nullable=true)
     */
    protected $seenResultRead;

    /**
     * @var string|null
     * @ORM\Column(name="content_hash", type="string", length=64, nullable=true)
     */
    protected $contentHash;

    /**
     * @var integer|null
     * @ORM\Column(name="result_type", type="smallint", nullable=true)
     */
    protected $resultType;

    /**
     * @var string|null
     * @ORM\Column(name="publication_date", type="string", length=40, nullable=true)
     */
    protected $publicationDate;

    /**
     * @var boolean
     * @ORM\Column(name="posted", type="boolean", nullable=false)
     */
    protected $posted = false;

    /**
     * @var \DateTime|null
     * @ORM\Column(name="started_at", type="datetime", nullable=true)
     */
    protected $startedAt;

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
