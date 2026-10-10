<?php

namespace App\Support;

use App\Models\User;

/**
 * Status impersonasi darurat: id super-admin asal disimpan di sesi selama menyamar sebagai pengguna lain.
 */
class Impersonasi
{
    public const KUNCI = 'impersonator_id';

    public static function aktif(): bool
    {
        return self::idAsal() !== null;
    }

    public static function idAsal(): ?string
    {
        if (! app()->bound('session.store') || ! request()->hasSession()) {
            return null;
        }

        $id = request()->session()->get(self::KUNCI);

        return is_string($id) && $id !== '' ? $id : null;
    }

    public static function asal(): ?User
    {
        $id = self::idAsal();

        return $id === null ? null : User::query()->find($id);
    }
}
