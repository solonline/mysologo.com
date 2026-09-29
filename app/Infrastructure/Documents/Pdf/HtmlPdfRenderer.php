<?php

namespace App\Infrastructure\Documents\Pdf;

use App\Domains\Documents\Contracts\PdfRenderer;
use Barryvdh\DomPDF\Facade\Pdf;

class HtmlPdfRenderer implements PdfRenderer
{
    public function render(string $html): string
    {
        return Pdf::loadHTML($html)
            ->output();
    }
}
