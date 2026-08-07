<?php

declare(strict_types=1);

namespace App\Core\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * docs/design/administration/Phase-8-Document-Design.md
 * A stateless wrapper around dompdf — the one place any Service renders
 * HTML to PDF bytes, so no module couples directly to the dompdf API
 * (ADR-012 §3).
 */
class PdfRenderer
{
    public function render(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
