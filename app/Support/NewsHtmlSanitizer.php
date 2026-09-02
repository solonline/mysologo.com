<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class NewsHtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'h2', 'h3', 'blockquote'];

    public function sanitize(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="news-root">'.$html.'</div></body></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('news-root');

        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->cleanNode($root);

        $inner = '';
        foreach ($root->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return trim($inner);
    }

    private function cleanNode(DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);

                    continue;
                }

                $this->stripAttributes($child, $tag);
                $this->cleanNode($child);

                continue;
            }

            if ($child->nodeType === XML_CDATA_SECTION_NODE || $child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private function stripAttributes(DOMElement $element, string $tag): void
    {
        $allowed = [];

        if ($tag === 'a') {
            $href = trim($element->getAttribute('href'));
            if ($this->isSafeHref($href)) {
                $allowed['href'] = $href;
            }
        }

        $toRemove = [];
        foreach ($element->attributes ?? [] as $attribute) {
            $toRemove[] = $attribute->nodeName;
        }

        foreach ($toRemove as $name) {
            $element->removeAttribute($name);
        }

        foreach ($allowed as $name => $value) {
            $element->setAttribute($name, $value);
        }
    }

    private function isSafeHref(string $href): bool
    {
        if ($href === '' || str_starts_with($href, '#')) {
            return $href !== '';
        }

        if (str_starts_with($href, '/')) {
            return ! str_starts_with($href, '//');
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }
}
