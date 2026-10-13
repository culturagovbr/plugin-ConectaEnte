<?php

namespace ConectaEnte\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * Uma troca HTTP com o CultBR dentro de um envio.
 *
 * @property int $id
 * @property Log $log
 * @property int $attempt
 * @property int $maxAttempts
 * @property string $endpoint
 * @property string $httpMethod
 * @property int|null $httpStatus
 * @property string|null $requestId
 * @property string|null $requestPayload
 * @property string|null $responseBody
 * @property string|null $responseHeaders
 * @property string|null $errorMessage
 * @property string $outcome
 * @property \DateTime $sentAt
 * @property int|null $durationMs
 *
 * @ORM\Table(name="conectaente_log_attempt")
 * @ORM\Entity(repositoryClass="MapasCulturais\Repository")
 */
class LogAttempt extends \MapasCulturais\Entity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="conectaente_log_attempt_id_seq", allocationSize=1, initialValue=1)
     */
    protected $id;

    /**
     * @var Log
     *
     * @ORM\ManyToOne(targetEntity="ConectaEnte\Entities\Log", inversedBy="attempts")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="log_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * })
     */
    protected $log;

    /**
     * @var integer
     *
     * @ORM\Column(name="attempt", type="smallint", nullable=false)
     */
    protected $attempt;

    /**
     * @var integer
     *
     * @ORM\Column(name="max_attempts", type="smallint", nullable=false)
     */
    protected $maxAttempts;

    /**
     * @var string
     *
     * @ORM\Column(name="endpoint", type="text", nullable=false)
     */
    protected $endpoint;

    /**
     * @var string
     *
     * @ORM\Column(name="http_method", type="string", length=10, nullable=false)
     */
    protected $httpMethod;

    /**
     * @var integer|null
     *
     * @ORM\Column(name="http_status", type="smallint", nullable=true)
     */
    protected $httpStatus;

    /**
     * @var string|null
     *
     * @ORM\Column(name="request_id", type="string", length=255, nullable=true)
     */
    protected $requestId;

    /**
     * @var string|null
     *
     * @ORM\Column(name="request_payload", type="text", nullable=true)
     */
    protected $requestPayload;

    /**
     * @var string|null
     *
     * @ORM\Column(name="response_body", type="text", nullable=true)
     */
    protected $responseBody;

    /**
     * @var string|null
     *
     * @ORM\Column(name="response_headers", type="text", nullable=true)
     */
    protected $responseHeaders;

    /**
     * @var string|null
     *
     * @ORM\Column(name="error_message", type="text", nullable=true)
     */
    protected $errorMessage;

    /**
     * @var string
     *
     * @ORM\Column(name="outcome", type="string", length=20, nullable=false)
     */
    protected $outcome;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="sent_at", type="datetime", nullable=false)
     */
    protected $sentAt;

    /**
     * @var integer|null
     *
     * @ORM\Column(name="duration_ms", type="integer", nullable=true)
     */
    protected $durationMs;

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
