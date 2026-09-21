<?php

namespace ConectaEnte\Auth;

use MapasCulturais\Entities\User;

/**
 * Confere a senha local do usuário: o mesmo hash que o MultipleLocalAuth grava.
 */
final class PasswordCheck
{
    const PASSWORD_METADATA = 'localAuthenticationPassword';

    function hasPassword(User $user): bool
    {
        return (bool) $user->getMetadata(self::PASSWORD_METADATA);
    }

    function verify(User $user, string $password): bool
    {
        $hash = (string) $user->getMetadata(self::PASSWORD_METADATA);

        return $hash !== '' && password_verify($password, $hash);
    }
}
