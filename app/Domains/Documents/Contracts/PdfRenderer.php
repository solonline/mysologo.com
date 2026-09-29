<?php

namespace App\Domains\Documents\Contracts;

interface PdfRenderer
{
    public function render(string $html): string;
}
