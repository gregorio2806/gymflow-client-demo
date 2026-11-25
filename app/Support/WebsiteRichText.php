<?php

namespace App\Support;

use DOMDocument;
use DOMNode;

final class WebsiteRichText
{
    // Rebuild approved elements from scratch: no attributes, URLs, CSS, or foreign namespaces.
    public static function sanitize(?string $html): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><body>'.($html ?? '').'</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $body = $document->getElementsByTagName('body')->item(0);

            return $body ? self::children($body) : '';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function children(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $html .= htmlspecialchars($child->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($child->nodeName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template'], true)) {
                    continue;
                }
                $content = self::children($child);
                $html .= in_array($tag, ['p', 'strong', 'em', 'ul', 'ol', 'li', 'h2', 'h3', 'blockquote'], true)
                    ? '<'.$tag.'>'.$content.'</'.$tag.'>' : ($tag === 'br' ? '<br>' : $content);
            }
        }

        return $html;
    }
}
