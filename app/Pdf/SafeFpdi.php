<?php

namespace App\Pdf;

use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * TCPDF normally prints an HTML error and terminates the request. This
 * prevents Laravel from returning JSON and leaves batch rows pending.
 * Convert PDF rendering errors into exceptions so the batch can record them.
 */
class SafeFpdi extends Fpdi
{
    public function Error($message)
    {
        throw new RuntimeException('PDF rendering error: '.(string) $message);
    }
}
