<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Membersihkan HTML dari editor rich text (Trix) agar aman ditampilkan (cegah XSS).
 * Hanya tag format dasar yang dipertahankan; semua atribut dibuang kecuali href aman pada <a>.
 */
class HtmlSanitizer
{
    private const ALLOWED = [
        'p', 'div', 'br', 'strong', 'b', 'em', 'i', 'u', 'del', 's', 'h1', 'h2', 'h3',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'a',
    ];

    /** Tag gambar yang diizinkan hanya untuk dokumentasi (upload lokal). */
    private const IMAGE_TAGS = ['figure', 'figcaption', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td'];

    private static bool $allowImages = false;

    public static function clean(?string $html, bool $allowImages = false): ?string
    {
        if ($html === null || (trim(strip_tags($html)) === '' && ! ($allowImages && str_contains($html, '<img')))) {
            return null;
        }

        self::$allowImages = $allowImages;

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root') ?? $doc->documentElement;
        self::walk($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out) === '' ? null : trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'button', 'select'], true)) {
                    $node->removeChild($child);

                    continue;
                }

                self::walk($child);

                $allowed = in_array($tag, self::ALLOWED, true)
                    || (self::$allowImages && in_array($tag, self::IMAGE_TAGS, true));

                if (! $allowed) {
                    // Pertahankan isinya, buang tag-nya.
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);

                    continue;
                }

                $href = $tag === 'a' ? trim($child->getAttribute('href')) : null;
                $src = $tag === 'img' ? trim($child->getAttribute('src')) : null;

                foreach (iterator_to_array($child->attributes) as $attr) {
                    $child->removeAttribute($attr->nodeName);
                }

                if ($href !== null && preg_match('#^(https?://|mailto:|/)#i', $href)) {
                    $child->setAttribute('href', $href);
                    $child->setAttribute('rel', 'noopener noreferrer');
                    $child->setAttribute('target', '_blank');
                }

                if ($tag === 'img') {
                    // Hanya gambar yang di-upload ke storage aplikasi sendiri.
                    if ($src !== null && self::isLocalUpload($src)) {
                        $child->setAttribute('src', $src);
                        $child->setAttribute('alt', '');
                        $child->setAttribute('loading', 'lazy');
                    } else {
                        $node->removeChild($child);
                    }
                }
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private static function isLocalUpload(string $src): bool
    {
        $path = parse_url($src, PHP_URL_PATH) ?? '';
        $host = parse_url($src, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return str_starts_with($path, '/storage/docs/')
            && ! str_contains($path, '..')
            && ($host === null || $host === $appHost || $host === request()->getHost());
    }
}
