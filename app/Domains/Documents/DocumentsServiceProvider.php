<?php

namespace App\Domains\Documents;

use App\Domains\Documents\Contracts\PdfRenderer;
use App\Infrastructure\Documents\Pdf\HtmlPdfRenderer;
use Illuminate\Support\ServiceProvider;

class DocumentsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(PdfRenderer::class, HtmlPdfRenderer::class);
    }
}
