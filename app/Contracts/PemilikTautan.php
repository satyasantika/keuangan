<?php

namespace App\Contracts;

/**
 * Model yang dapat memiliki tautan berkas; menentukan kapan tautannya masih boleh diubah.
 */
interface PemilikTautan
{
    public function tautanDapatDiubah(): bool;
}
