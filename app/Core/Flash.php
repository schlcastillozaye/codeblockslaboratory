<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session flash messages, form errors and old input.
 */
final class Flash
{
    public static function set(string $type, string $message): void
    {
        $messages = Session::get('_flash', []);
        $messages[] = ['type' => $type, 'message' => $message];
        Session::set('_flash', $messages);
    }

    /** @return array<int, array{type:string,message:string}> */
    public static function pullAll(): array
    {
        return (array) Session::pull('_flash', []);
    }

    /** @param array<string, string> $errors */
    public static function setErrors(array $errors): void
    {
        Session::set('_errors', $errors);
    }

    /** @return array<string, string> */
    public static function errors(): array
    {
        return (array) Session::pull('_errors', []);
    }

    /** @param array<string, mixed> $input */
    public static function setOld(array $input): void
    {
        unset($input['_token'], $input['password'], $input['password_confirm'], $input['current_password']);
        Session::set('_old', $input);
    }

    /** @return array<string, mixed> */
    public static function old(): array
    {
        return (array) Session::pull('_old', []);
    }

    public static function has(string $type): bool
    {
        foreach ((array) Session::get('_flash', []) as $message) {
            if ($message['type'] === $type) {
                return true;
            }
        }
        return false;
    }
}
