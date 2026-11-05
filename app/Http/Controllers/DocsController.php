<?php

namespace App\Http\Controllers;

use App\Support\DocsGuideService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class DocsController extends Controller
{
    public function __construct(
        private DocsGuideService $guides,
    ) {}

    public function internal(): View
    {
        abort_unless(config('docs.enabled', true), 404);

        return view('docs.scalar', $this->scalarViewData(
            title: 'Yoop API — Documentação Interna',
            specPath: 'internal.yaml',
            description: 'Referência completa para equipa interna (mobile, backend, QA).',
            preferredSecurityScheme: 'bearerAuth',
            navActive: 'api-internal',
        ));
    }

    public function partners(): View
    {
        abort_unless(config('docs.enabled', true), 404);

        return view('docs.scalar', $this->scalarViewData(
            title: 'Yoop API — Integrações Parceiros',
            specPath: 'partners.yaml',
            description: 'Endpoints expostos a merchants e integradores externos.',
            preferredSecurityScheme: 'merchantKey',
            navActive: 'api-partners',
        ));
    }

    public function guidesInternal(): View
    {
        abort_unless(config('docs.enabled', true), 404);

        return view('docs.guides.index', [
            'title' => 'Guias — Equipa interna',
            'description' => 'Fluxos de negócio, estados e integrações.',
            'navActive' => 'guides-internal',
            'audience' => 'internal',
            'guides' => $this->guides->guidesFor('internal'),
        ]);
    }

    public function guidesPartners(): View
    {
        abort_unless(config('docs.enabled', true), 404);

        return view('docs.guides.index', [
            'title' => 'Guias — Parceiros',
            'description' => 'Integração merchant.',
            'navActive' => 'guides-partners',
            'audience' => 'partners',
            'guides' => $this->guides->guidesFor('partners'),
        ]);
    }

    public function guideInternal(string $slug): View
    {
        return $this->guideShow('internal', $slug);
    }

    public function guidePartners(string $slug): View
    {
        return $this->guideShow('partners', $slug);
    }

    public function spec(string $path): Response
    {
        abort_unless(config('docs.enabled', true), 404);

        $base = realpath(config('docs.openapi_path'));
        $full = realpath($base.DIRECTORY_SEPARATOR.str_replace(['..', '\\'], ['', '/'], $path));

        if (! $full || ! str_starts_with($full, $base) || ! File::isFile($full)) {
            abort(404);
        }

        return response(File::get($full), 200, [
            'Content-Type' => 'text/yaml; charset=utf-8',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function guideShow(string $audience, string $slug): View
    {
        abort_unless(config('docs.enabled', true), 404);

        $guide = $this->guides->findGuide($audience, $slug);

        if (! $guide) {
            abort(404);
        }

        $navActive = $audience === 'partners' ? 'guides-partners' : 'guides-internal';

        return view('docs.guides.show', [
            'title' => $guide['title'],
            'description' => $guide['description'] ?? null,
            'navActive' => $navActive,
            'audience' => $audience,
            'slug' => $slug,
            'guides' => $this->guides->guidesFor($audience),
            'content' => $this->guides->render($guide['file']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function scalarViewData(
        string $title,
        string $specPath,
        string $description,
        string $preferredSecurityScheme,
        string $navActive,
    ): array {
        return [
            'title' => $title,
            'description' => $description,
            'navActive' => $navActive,
            'specUrl' => url('/docs/openapi/'.$specPath),
            'scalarConfiguration' => [
                'theme' => 'kepler',
                'layout' => 'modern',
                'darkMode' => true,
                'hideDownloadButton' => false,
                'hideModels' => false,
                'defaultOpenAllTags' => false,
                'searchHotKey' => 'k',
                'favicon' => url('/yoop.jpeg'),
                'metaData' => ['title' => $title],
                'authentication' => ['preferredSecurityScheme' => $preferredSecurityScheme],
            ],
        ];
    }
}
