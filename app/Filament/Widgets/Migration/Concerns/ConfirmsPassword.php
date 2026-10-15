<?php

namespace App\Filament\Widgets\Migration\Concerns;

use App\Security\ReauthGate;
use Filament\Forms\Components\TextInput;
use Illuminate\Validation\ValidationException;

/**
 * "Type your password again" for the migration panels. ReauthGate (the shared check, with its wrong-guess limit) reports its
 * error on a field called `password`; inside a modal the field lives at `mountedActions.0.data.password`, so the error is moved
 * there or the person would see nothing happen.
 */
trait ConfirmsPassword
{
    protected function passwordField(): TextInput
    {
        return TextInput::make('password')->label(__('migration.ui.password'))->password()->revealable()->required();
    }

    /**
     * @param  array<string, mixed>  $data  the modal's form state
     * @param  string  $statePath  where the password field lives
     *
     * @throws ValidationException
     */
    protected function confirmPassword(array $data, string $statePath = 'mountedActions.0.data.password'): void
    {
        try {
            ReauthGate::confirm($data['password'] ?? null, 'password', __('migration.ui.password_incorrect'));
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([$statePath => $e->errors()['password'] ?? [__('migration.ui.password_incorrect')]]);
        }
    }
}
