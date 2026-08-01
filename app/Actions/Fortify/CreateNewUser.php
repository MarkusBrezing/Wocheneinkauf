<?php

namespace App\Actions\Fortify;

use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Services\InvitationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(
        protected FamilyProvisioner $provisioner,
        protected InvitationService $invitations,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * Zwei Wege enden hier:
     *
     * - Ohne Einladung entsteht mit dem Konto immer auch eine Familie, deren
     *   Owner der neue Nutzer wird (A-01). Ohne Mandant gaebe es sonst einen
     *   angemeldeten Nutzer, der nichts sehen und nichts anlegen kann.
     * - Mit gueltiger Einladung tritt er stattdessen der einladenden Familie
     *   bei (A-07, Variante "neues Konto").
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'email',
                'max:190',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'family_name' => ['nullable', 'string', 'max:120'],
            'einladung' => ['nullable', 'string'],
        ])->validate();

        $einladung = isset($input['einladung'])
            ? $this->invitations->findRedeemable($input['einladung'])
            : null;

        if (($input['einladung'] ?? '') !== '' && $einladung === null) {
            throw ValidationException::withMessages([
                'einladung' => 'Diese Einladung ist nicht mehr gültig.',
            ]);
        }

        return DB::transaction(function () use ($input, $einladung) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            if ($einladung) {
                $this->invitations->redeem($einladung, $user);
            } else {
                $this->provisioner->createFor($user, $input['family_name'] ?? null);
            }

            return $user->refresh();
        });
    }
}
