<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis. Pesan berbahasa Indonesia dan aman ditampilkan ke pengguna.
 */
class KesalahanDomain extends RuntimeException {}
