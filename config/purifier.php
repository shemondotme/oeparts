<?php
/**
 * App-level override of vendor/mews/purifier/config/purifier.php.
 *
 * The vendor default's 'default' profile only allows
 * div,b,strong,i,em,u,a,ul,ol,li,p,br,span,img — no headings, no `id`
 * attribute. clean()/{{ trans_field($page->content) }} (resources/views/
 * frontend/page.blade.php, plus the Impressum/Shipping/Returns pages built
 * alongside it) is used to render CMS Page content that legitimately
 * contains <h2>/<h3>/<h4> section headings and <div class="..."> callout
 * boxes styled by that view's `prose-h2:*` etc. Tailwind typography classes.
 * Verified directly (not assumed): with the vendor default,
 * clean('<h2>Section</h2><h3>Sub</h3>') collapses to a single mangled
 * <p>SectionSub</p> — both tags stripped AND their text runs together with
 * no separator, and any `id` attribute (needed for in-page anchor links,
 * e.g. the Returns & Withdrawal Policy page's "model withdrawal form"
 * anchor) is dropped everywhere, silently, with zero error. This override
 * adds h1–h4 (with id, for anchors), blockquote/code/pre/table markup, and
 * `class`+`id` on div/p so headings render as real headings and anchor
 * links actually land. Every other profile ('test','youtube',
 * 'custom_definition', etc.) is left byte-for-byte identical to the vendor
 * default — Laravel's mergeConfigFrom() only shallow-merges the top-level
 * 'settings' key, so a partial override here would silently drop those
 * other profiles for any code that still requests them.
 */

return [
    'encoding'           => 'UTF-8',
    'finalize'           => true,
    'ignoreNonStrings'   => false,
    'cachePath'          => storage_path('app/purifier'),
    'cacheFileMode'      => 0755,
    'settings'      => [
        'default' => [
            'HTML.Doctype'             => 'HTML 4.01 Transitional',
            'HTML.Allowed'             => 'div[class|id],b,strong,i,em,u,a[href|title|target],ul,ol,li,p[style|id],br,span[style],img[width|height|alt|src],h1[id],h2[id],h3[id],h4[id],blockquote,code,pre,table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan]',
            'CSS.AllowedProperties'    => 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align',
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty'   => true,
            'Attr.EnableID'            => true,
        ],
        'test'    => [
            'Attr.EnableID' => 'true',
        ],
        "youtube" => [
            "HTML.SafeIframe"      => 'true',
            "URI.SafeIframeRegexp" => "%^(http://|https://|//)(www.youtube.com/embed/|player.vimeo.com/video/)%",
        ],
        'custom_definition' => [
            'id'  => 'html5-definitions',
            'rev' => 1,
            'debug' => false,
            'elements' => [
                // http://developers.whatwg.org/sections.html
                ['section', 'Block', 'Flow', 'Common'],
                ['nav',     'Block', 'Flow', 'Common'],
                ['article', 'Block', 'Flow', 'Common'],
                ['aside',   'Block', 'Flow', 'Common'],
                ['header',  'Block', 'Flow', 'Common'],
                ['footer',  'Block', 'Flow', 'Common'],

                // Content model actually excludes several tags, not modelled here
                ['address', 'Block', 'Flow', 'Common'],
                ['hgroup', 'Block', 'Required: h1 | h2 | h3 | h4 | h5 | h6', 'Common'],

                // http://developers.whatwg.org/grouping-content.html
                ['figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common'],
                ['figcaption', 'Inline', 'Flow', 'Common'],

                // http://developers.whatwg.org/the-video-element.html#the-video-element
                ['video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', [
                    'src' => 'URI',
                    'type' => 'Text',
                    'width' => 'Length',
                    'height' => 'Length',
                    'poster' => 'URI',
                    'preload' => 'Enum#auto,metadata,none',
                    'controls' => 'Bool',
                ]],
                ['source', 'Block', 'Flow', 'Common', [
                    'src' => 'URI',
                    'type' => 'Text',
                ]],

                // http://developers.whatwg.org/text-level-semantics.html
                ['s',    'Inline', 'Inline', 'Common'],
                ['var',  'Inline', 'Inline', 'Common'],
                ['sub',  'Inline', 'Inline', 'Common'],
                ['sup',  'Inline', 'Inline', 'Common'],
                ['mark', 'Inline', 'Inline', 'Common'],
                ['wbr',  'Inline', 'Empty', 'Core'],

                // http://developers.whatwg.org/edits.html
                ['ins', 'Block', 'Flow', 'Common', ['cite' => 'URI', 'datetime' => 'CDATA']],
                ['del', 'Block', 'Flow', 'Common', ['cite' => 'URI', 'datetime' => 'CDATA']],
            ],
            'attributes' => [
                ['iframe', 'allowfullscreen', 'Bool'],
                ['table', 'height', 'Text'],
                ['td', 'border', 'Text'],
                ['th', 'border', 'Text'],
                ['tr', 'width', 'Text'],
                ['tr', 'height', 'Text'],
                ['tr', 'border', 'Text'],
            ],
        ],
        'custom_attributes' => [
            ['a', 'target', 'Enum#_blank,_self,_target,_top'],
        ],
        'custom_elements' => [
            ['u', 'Inline', 'Inline', 'Common'],
        ],
    ],

];
