<?php
/**
 * Sanitização de conteúdo por lista de permissão (seção 12).
 *
 * Estratégia allowlist (não blocklist): faz o parse do HTML e reconstrói
 * mantendo apenas as tags e atributos permitidos. Remove <script>, <iframe>,
 * <object>, <embed>, atributos on* e esquemas javascript:/vbscript:/data:.
 *
 * Usado tanto na gravação quanto na exibição (seção 12.2). Não depende de
 * biblioteca externa — só de ext-dom, presente no GLPI.
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginAvisosSanitizer
{
    /**
     * Tags permitidas e, para cada uma, os atributos permitidos.
     * '*' aplica-se a todas as tags.
     *
     * @var array<string,string[]>
     */
    private static $allowed = [
        '*'          => ['style', 'class', 'title'],
        'p'          => [],
        'br'         => [],
        'hr'         => [],
        'b'          => [],
        'strong'     => [],
        'i'          => [],
        'em'         => [],
        'u'          => [],
        's'          => [],
        'sub'        => [],
        'sup'        => [],
        'span'       => [],
        'div'        => [],
        'h1'         => [],
        'h2'         => [],
        'h3'         => [],
        'h4'         => [],
        'h5'         => [],
        'h6'         => [],
        'ul'         => [],
        'ol'         => ['start'],
        'li'         => [],
        'blockquote' => [],
        'pre'        => [],
        'code'       => [],
        'a'          => ['href', 'target', 'rel'],
        'img'        => ['src', 'alt', 'width', 'height'],
        'table'      => ['border', 'cellpadding', 'cellspacing'],
        'thead'      => [],
        'tbody'      => [],
        'tfoot'      => [],
        'tr'         => [],
        'td'         => ['colspan', 'rowspan', 'align', 'valign'],
        'th'         => ['colspan', 'rowspan', 'align', 'valign'],
        'caption'    => [],
    ];

    /**
     * Esquemas de URL bloqueados em href/src.
     *
     * @var string[]
     */
    private static $blocked_schemes = ['javascript', 'vbscript', 'data', 'file'];

    /**
     * Retorna o HTML sanitizado por lista de permissão.
     *
     * @param string $html Conteúdo bruto.
     *
     * @return string HTML seguro.
     */
    public static function getSafeHtml($html)
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        // Falha segura (seção 12): qualquer erro inesperado no parsing devolve
        // texto escapado em vez de derrubar a gravação.
        try {
            return self::doGetSafeHtml($html);
        } catch (\Throwable $e) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Implementação interna da sanitização (ver getSafeHtml).
     *
     * @param string $html Conteúdo bruto não vazio.
     *
     * @return string
     */
    private static function doGetSafeHtml($html)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        // A meta charset força o libxml a interpretar o conteúdo como UTF-8;
        // <body> serve de âncora confiável (getElementById exigiria DTD).
        $meta = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        $loaded = $dom->loadHTML(
            $meta . '<body>' . $html . '</body>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            // Falha de parsing: devolve texto escapado (fail-safe).
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }

        self::cleanNode($body, $dom);

        // Serializa apenas os filhos do <body>.
        $out = '';
        foreach (iterator_to_array($body->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }
        return trim($out);
    }

    /**
     * Percorre e limpa recursivamente um nó e seus filhos.
     *
     * @param DOMNode     $node Nó a limpar.
     * @param DOMDocument $dom  Documento dono.
     *
     * @return void
     */
    private static function cleanNode(DOMNode $node, DOMDocument $dom)
    {
        // Copia a lista pois vamos remover nós durante a iteração.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->nodeName);

                if (!isset(self::$allowed[$tag])) {
                    // Tag não permitida: remove o elemento inteiro (com filhos).
                    $node->removeChild($child);
                    continue;
                }

                self::cleanAttributes($child, $tag);
                self::cleanNode($child, $dom); // recursão nos filhos mantidos
            } elseif (
                $child instanceof DOMComment
                || ($child instanceof DOMProcessingInstruction)
            ) {
                // Remove comentários e instruções de processamento.
                $node->removeChild($child);
            }
            // DOMText permanece intacto — o serializador escapa entidades.
        }
    }

    /**
     * Remove atributos não permitidos, on* e URLs com esquema perigoso.
     *
     * @param DOMElement $el  Elemento.
     * @param string     $tag Nome da tag (minúsculo).
     *
     * @return void
     */
    private static function cleanAttributes(DOMElement $el, $tag)
    {
        $allowed = array_merge(self::$allowed['*'], self::$allowed[$tag] ?? []);

        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->nodeName);

            // Qualquer manipulador de evento inline.
            if (strpos($name, 'on') === 0) {
                $el->removeAttribute($attr->nodeName);
                continue;
            }

            if (!in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->nodeName);
                continue;
            }

            if ($name === 'href' || $name === 'src') {
                if (self::hasBlockedScheme($attr->nodeValue)) {
                    $el->removeAttribute($attr->nodeName);
                }
            }

            if ($name === 'style' && self::hasDangerousStyle($attr->nodeValue)) {
                $el->removeAttribute($attr->nodeName);
            }

            if ($name === 'target') {
                // Sempre acompanha rel=noopener para links _blank.
                $el->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    /**
     * Detecta esquema de URL bloqueado, ignorando espaços/quebras injetados.
     *
     * @param string $value Valor do atributo.
     *
     * @return boolean
     */
    private static function hasBlockedScheme($value)
    {
        // Remove caracteres de controle e espaços usados para burlar o filtro.
        $normalized = strtolower(preg_replace('/[\s\x00-\x1F]+/', '', (string) $value));
        foreach (self::$blocked_schemes as $scheme) {
            if (strpos($normalized, $scheme . ':') === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detecta CSS inline perigoso (expression, url externo, import, js).
     *
     * @param string $value Valor do atributo style.
     *
     * @return boolean
     */
    private static function hasDangerousStyle($value)
    {
        $normalized = strtolower((string) $value);
        foreach (['expression', 'javascript:', 'vbscript:', '@import', 'url('] as $needle) {
            if (strpos($normalized, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}
