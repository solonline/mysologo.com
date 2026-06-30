<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

class DocsGuideService
{
    public function guidesFor(string $audience): array
    {
        return config("docs_guides.{$audience}", []);
    }

    public function findGuide(string $audience, string $slug): ?array
    {
        foreach ($this->guidesFor($audience) as $guide) {
            if ($guide['slug'] === $slug) {
                return $guide;
            }
        }

        return null;
    }

    public function render(string $relativeFile): string
    {
        $path = $this->guidePath($relativeFile);

        if (! File::isFile($path)) {
            abort(404);
        }

        return $this->markdownToHtml(File::get($path));
    }

    public function guidePath(string $relativeFile): string
    {
        $base = realpath(config('docs.guides_path'));
        $full = realpath($base.DIRECTORY_SEPARATOR.str_replace(['..', '\\'], ['', '/'], $relativeFile));

        if (! $full || ! str_starts_with($full, $base)) {
            abort(404);
        }

        return $full;
    }

    private function markdownToHtml(string $markdown): string
    {
        $placeholders = [];

        $markdown = preg_replace_callback(
            '/```mermaid\s*\n(.*?)\n```/s',
            function (array $matches) use (&$placeholders): string {
                $key = 'MERMAIDBLOCK'.count($placeholders);
                $placeholders[$key] = trim($matches[1]);

                return "\n\n{$key}\n\n";
            },
            $markdown,
        ) ?? $markdown;

        $html = $this->convertMarkdown($markdown);

        foreach ($placeholders as $key => $diagram) {
            $block = '<div class="mermaid">'.$diagram.'</div>';
            $html = str_replace('<p>'.$key.'</p>', $block, $html);
            $html = str_replace($key, $block, $html);
        }

        return $html;
    }

    private function convertMarkdown(string $markdown): string
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new TableExtension);

        return (string) (new MarkdownConverter($environment))->convert($markdown);
    }
}
