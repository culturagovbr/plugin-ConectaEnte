<?php

namespace ConectaEnte\Auth;

/**
 * Janela em que a senha confirmada há pouco dispensa nova digitação. A marca vive só na sessão.
 */
final class PasswordWindow
{
    const SESSION_KEY = 'conectaente.password.confirmed_at';

    public function __construct(private int $seconds)
    {
    }

    function isOpen(): bool
    {
        $confirmedAt = $_SESSION[self::SESSION_KEY] ?? null;

        // com zero segundos nada é menor que zero: a janela nunca abre
        return $confirmedAt !== null && time() - $confirmedAt < $this->seconds;
    }

    function open(): void
    {
        $_SESSION[self::SESSION_KEY] = time();
    }

    function close(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }
}
