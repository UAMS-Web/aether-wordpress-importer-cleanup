<?php

declare(strict_types=1);
use UAMSWeb\Aether\WordPressImporter\Cleanup\Rules\CaptionizeImageCutline;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Tests\Fixture;
use UAMSWeb\Aether\WordPressImporter\Cleanup\Transform;

/**
 * @return iterable<string, array{string, string}>
 */
dataset('fixturePairs', function () {
    $fixtureDir = __DIR__.'/../fixtures/captionize-image-cutline';
    $beforeFiles = glob($fixtureDir.'/*.before.xml');

    if ($beforeFiles === false) {
        return;
    }

    foreach ($beforeFiles as $beforeFile) {
        $afterFile = str_replace('.before.xml', '.after.xml', $beforeFile);

        if (! file_exists($afterFile)) {
            continue;
        }

        $label = basename($beforeFile, '.before.xml');

        yield $label => [$beforeFile, $afterFile];
    }
});
test('fixture round trip', function (string $beforeFile, string $afterFile) {
    $transform = new Transform([
        new CaptionizeImageCutline,
    ]);

    $before = Fixture::contents($beforeFile);
    $expectedAfter = Fixture::contents($afterFile);

    $actualAfter = $transform->apply($before);

    expect($actualAfter)->toBe($expectedAfter);
})->with('fixturePairs');

/**
 * @return string the serialized contents of the body of `$html` after the rule has run
 */
function captionizeBody(string $html): string
{
    $document = Fixture::document($html);

    (new CaptionizeImageCutline)->apply($document);

    $body = $document->getElementsByTagName('body')->item(0);

    return substr($document->saveHtml($body), 6, -7);
}

test('an image paired with a cutline becomes a caption shortcode', function (string $html, string $expected) {
    expect(captionizeBody($html))->toBe($expected);
})->with([
    'E right attr' => ['<p align="right"><img src="a" class="x" style="float:left"> Cap text</p>', "\n[caption align=\"alignright\"]<img src=\"a\"> Cap text[/caption]\n"],
    'E left via style with br' => ['<p style="text-align: left"><img src="a" alt="b" width="3" height="4"><br>Cap</p>', "\n[caption align=\"alignleft\"]<img src=\"a\" alt=\"b\" width=\"3\" height=\"4\"> Cap[/caption]\n"],
    'E uppercase attr' => ['<p align="RIGHT"><img src="a"> Cap</p>', "\n[caption align=\"alignright\"]<img src=\"a\"> Cap[/caption]\n"],
    'E leading whitespace' => ['<p align="right">   <img src="a"> Cap</p>', "\n[caption align=\"alignright\"]<img src=\"a\"> Cap[/caption]\n"],
    'E several breaks' => ['<p align="right"><img src="a"><br>One<br>Two</p>', "\n[caption align=\"alignright\"]<img src=\"a\"> One Two[/caption]\n"],
    'E style no spaces' => ['<p style="text-align:right"><img src="a"> Cap</p>', "\n[caption align=\"alignright\"]<img src=\"a\"> Cap[/caption]\n"],
    'E style spaced colon' => ['<p style="text-align : left;"><img src="a"> Cap</p>', "\n[caption align=\"alignleft\"]<img src=\"a\"> Cap[/caption]\n"],
    'E style uppercase' => ['<p style="TEXT-ALIGN: RIGHT"><img src="a"> Cap</p>', "\n[caption align=\"alignright\"]<img src=\"a\"> Cap[/caption]\n"],
    'E style other declaration' => ['<p style="color: red; text-align: right"><img src="a"> Cap</p>', "\n[caption align=\"alignright\"]<img src=\"a\"> Cap[/caption]\n"],
    'E surrounded' => ['before<p align="left"><img src="a"> Cap</p>after', "before\n[caption align=\"alignleft\"]<img src=\"a\"> Cap[/caption]\nafter"],
    'E two paragraphs' => ['<p align="left"><img src="a"> One</p><p align="right"><img src="b"> Two</p>', "\n[caption align=\"alignleft\"]<img src=\"a\"> One[/caption]\n\n[caption align=\"alignright\"]<img src=\"b\"> Two[/caption]\n"],
    'E skipped then converted' => ['<p align="left">x</p><p align="right"><img src="b"> Two</p>', "<p align=\"left\">x</p>\n[caption align=\"alignright\"]<img src=\"b\"> Two[/caption]\n"],
    'F basic' => ['<table><tbody><tr><td><img src="a" class="x"><br>Cap text</td></tr></tbody></table>', '<table><tbody><tr><td>[caption]<img src="a"> Cap text[/caption]</td></tr></tbody></table>'],
    'F whitespace' => ['<table><tbody><tr><td>  <img src="a">  <br>  Cap  </td></tr></tbody></table>', '<table><tbody><tr><td>[caption]<img src="a"> Cap[/caption]</td></tr></tbody></table>'],
    'F two cells' => ['<table><tbody><tr><td><img src="a"><br>One</td><td><img src="b"><br>Two</td></tr></tbody></table>', '<table><tbody><tr><td>[caption]<img src="a"> One[/caption]</td><td>[caption]<img src="b"> Two[/caption]</td></tr></tbody></table>'],
    'G font' => ['<font class="cutline"><img src="a" class="x"><br>Cap text</font>', '[caption]<img src="a"> Cap text[/caption]'],
    'G span' => ['<span class="cutline"><img src="a"> Cap</span>', '[caption]<img src="a"> Cap[/caption]'],
    'G div' => ['<div class="cutline"><img src="a"> Cap</div>', '[caption]<img src="a"> Cap[/caption]'],
    'G p' => ['<p class="cutline"><img src="a"> Cap</p>', '[caption]<img src="a"> Cap[/caption]'],
    'G multi class' => ['<span class="big cutline"><img src="a"> Cap</span>', '[caption]<img src="a"> Cap[/caption]'],
    'G text before img' => ['<span class="cutline">Lead <img src="a"> Cap</span>', '[caption]<img src="a"> Lead  Cap[/caption]'],
    'G surrounded' => ['pre <font class="cutline"><img src="a"><br>Cap</font> post', 'pre [caption]<img src="a"> Cap[/caption] post'],
    'G several breaks' => ['<font class="cutline"><img src="a"><br>One<br>Two</font>', '[caption]<img src="a"> One Two[/caption]'],
    'D basic' => ['<div class="wp-caption alignright" style="width: 310px;"><img src="a" width="300" height="194" class="x"><p class="wp-caption-text">Caption <b>text</b>.</p></div>', '[caption]<img src="a" width="300" height="194"> Caption <b>text</b>.[/caption]'],
    'D whitespace and breaks' => ['<div class="wp-caption">  <br>  <img src="a">  <br>  <p class="wp-caption-text">Cap</p>  </div>', '[caption]<img src="a"> Cap[/caption]'],
    'D caption class tokens' => ['<div class="wp-caption"><img src="a"><p class="x wp-caption-text y">Cap</p></div>', '[caption]<img src="a"> Cap[/caption]'],
    'D surrounded' => ['pre <div class="wp-caption"><img src="a"><p class="wp-caption-text">Cap</p></div> post', 'pre [caption]<img src="a"> Cap[/caption] post'],
    'A span' => ['<img src="a" class="x"><span class="cutline">Cap.</span>', '[caption]<img src="a"> Cap.[/caption]'],
    'A div' => ['<img src="a"><div class="cutline">Cap.</div>', '[caption]<img src="a"> Cap.[/caption]'],
    'A font' => ['<img src="a"><font class="cutline">Cap.</font>', '[caption]<img src="a"> Cap.[/caption]'],
    'A p cutline' => ['<img src="a"><p class="cutline">Cap.</p>', '[caption]<img src="a"> Cap.[/caption]'],
    'A gap' => ['<img src="a"> <br> <span class="cutline">Cap.</span>', '[caption]<img src="a"> Cap.[/caption]'],
    'A markup in cutline' => ['<img src="a"><span class="cutline">Cap <i>x</i>.</span>', '[caption]<img src="a"> Cap <i>x</i>.[/caption]'],
    'A in div' => ['<div><img src="a"><span class="cutline">Cap.</span>tail</div>', '<div>[caption]<img src="a"> Cap.[/caption]tail</div>'],
    'B wrapped' => ['<img src="a"><p><span class="cutline">Cap.</span></p>', '[caption]<img src="a"> Cap.[/caption]'],
    'B wrapped with whitespace' => ['<img src="a"><p> <br> <span class="cutline">Cap.</span> <br> </p>', '[caption]<img src="a"> Cap.[/caption]'],
    'A aligned parent right' => ['<p align="right"><img src="a"><span class="cutline">Cap.</span></p>', '<p align="right">[caption align="alignright"]<img src="a"> Cap.[/caption]</p>'],
    'A aligned parent left style' => ['<p style="text-align: left"><img src="a"><span class="cutline">Cap.</span></p>', '<p style="text-align: left">[caption align="alignleft"]<img src="a"> Cap.[/caption]</p>'],
    'A plain p parent' => ['<p><img src="a"><span class="cutline">Cap.</span>tail</p>', '<p>[caption]<img src="a"> Cap.[/caption]tail</p>'],
    'A div parent aligned' => ['<div align="right"><img src="a"><span class="cutline">Cap.</span></div>', '<div align="right">[caption]<img src="a"> Cap.[/caption]</div>'],
    'A centered p parent' => ['<p align="center"><img src="a"><span class="cutline">Cap.</span>tail</p>', '<p align="center">[caption]<img src="a"> Cap.[/caption]tail</p>'],
    'C lifted' => ['<p><img src="a" class="x"></p><span class="cutline">Cap.</span>', '[caption]<img src="a"> Cap.[/caption]'],
    'C with gaps' => ['<p> <br> <img src="a"> <br> </p> <br> <span class="cutline">Cap.</span>', '[caption]<img src="a"> Cap.[/caption]'],
    'C then wrapped p' => ['<p><img src="a"></p><p><span class="cutline">Cap.</span></p>', '[caption]<img src="a"> Cap.[/caption]'],
    'C surrounded' => ['<div>pre<p><img src="a"></p><span class="cutline">Cap.</span>post</div>', '<div>pre[caption]<img src="a"> Cap.[/caption]post</div>'],
    'C two paragraphs' => ['<p><img src="a"></p><span class="cutline">One.</span><p><img src="b"></p><span class="cutline">Two.</span>', '[caption]<img src="a"> One.[/caption][caption]<img src="b"> Two.[/caption]'],
    'two images' => ['<img src="a"><span class="cutline">One.</span><img src="b"><span class="cutline">Two.</span>', '[caption]<img src="a"> One.[/caption][caption]<img src="b"> Two.[/caption]'],
    'attrs stripped' => ['<img src="a" alt="b" width="3" height="4" class="c" style="d" longdesc="e" title="f"><span class="cutline">Cap.</span>', '[caption]<img src="a" alt="b" width="3" height="4"> Cap.[/caption]'],
    'attrs uppercase names' => ['<img SRC="a" ALT="b" CLASS="c"><span class="cutline">Cap.</span>', '[caption]<img src="a" alt="b"> Cap.[/caption]'],
]);

test('markup that does not match a cutline pattern is left alone', function (string $html) {
    expect(captionizeBody($html))->toBe($html);
})->with([
    'E non-img first' => ['<p align="right">x<img src="a"> Cap</p>'],
    'E element in text' => ['<p align="right"><img src="a"> Cap <b>x</b></p>'],
    'E no text' => ['<p align="right"><img src="a"></p>'],
    'E blank text' => ['<p align="right"><img src="a">   </p>'],
    'E no img' => ['<p align="right">text only</p>'],
    'E center' => ['<p align="center"><img src="a"><br>Cap</p>'],
    'E style prefixed property' => ['<p style="x-text-align: right"><img src="a"> Cap</p>'],
    'E style longer word' => ['<p style="text-align: rightmost"><img src="a"> Cap</p>'],
    'E empty style' => ['<p style=""><img src="a"> Cap</p>'],
    'F no br' => ['<table><tbody><tr><td><img src="a">Cap</td></tr></tbody></table>'],
    'F no img' => ['<table><tbody><tr><td>text</td></tr></tbody></table>'],
    'F img only' => ['<table><tbody><tr><td><img src="a"></td></tr></tbody></table>'],
    'F blank text' => ['<table><tbody><tr><td><img src="a"><br>   </td></tr></tbody></table>'],
    'F element in text' => ['<table><tbody><tr><td><img src="a"><br>Cap<b>x</b></td></tr></tbody></table>'],
    'F element first' => ['<table><tbody><tr><td><b>x</b><img src="a"><br>Cap</td></tr></tbody></table>'],
    'F empty cell' => ['<table><tbody><tr><td></td></tr></tbody></table>'],
    'F extra element after text' => ['<table><tbody><tr><td><img src="a"><br>One<br>Two</td></tr></tbody></table>'],
    'F text before img' => ['<table><tbody><tr><td>lead<img src="a"><br>Cap</td></tr></tbody></table>'],
    'G similar class' => ['<span class="cutlines"><img src="a"> Cap</span>'],
    'G two images' => ['<span class="cutline"><img src="a"><img src="b"> Cap</span>'],
    'G other element' => ['<span class="cutline"><img src="a"> Cap <b>x</b></span>'],
    'G no img' => ['<span class="cutline">Cap</span>'],
    'G no text' => ['<span class="cutline"><img src="a"></span>'],
    'G blank text' => ['<span class="cutline"><img src="a">   </span>'],
    'G comment child' => ['<span class="cutline"><!-- c --><img src="a"> Cap</span>'],
    'G wrong tag' => ['<b class="cutline"><img src="a"> Cap</b>'],
    'D two images' => ['<div class="wp-caption"><img src="a"><img src="b"><p class="wp-caption-text">Cap</p></div>'],
    'D two captions' => ['<div class="wp-caption"><img src="a"><p class="wp-caption-text">One</p><p class="wp-caption-text">Two</p></div>'],
    'D extra element' => ['<div class="wp-caption"><img src="a"><span>x</span><p class="wp-caption-text">Cap</p></div>'],
    'D loose text' => ['<div class="wp-caption"><img src="a">loose<p class="wp-caption-text">Cap</p></div>'],
    'D plain p' => ['<div class="wp-caption"><img src="a"><p>Cap</p></div>'],
    'D no img' => ['<div class="wp-caption"><p class="wp-caption-text">Cap</p></div>'],
    'D no caption' => ['<div class="wp-caption"><img src="a"></div>'],
    'D other div' => ['<div class="other"><img src="a"><p class="wp-caption-text">Cap</p></div>'],
    'D similar class' => ['<div class="wp-captions"><img src="a"><p class="wp-caption-text">Cap</p></div>'],
    'D empty class' => ['<div class=""><img src="a"><p class="wp-caption-text">Cap</p></div>'],
    'A text between' => ['<img src="a"> text <span class="cutline">Cap.</span>'],
    'A wrong tag' => ['<img src="a"><b class="cutline">Cap.</b>'],
    'A no class' => ['<img src="a"><span>Cap.</span>'],
    'A nothing after' => ['<img src="a">'],
    'B two children' => ['<img src="a"><p><span class="cutline">Cap.</span><b>x</b></p>'],
    'B text and cutline' => ['<img src="a"><p>text <span class="cutline">Cap.</span></p>'],
    'B plain p' => ['<img src="a"><p>Cap.</p>'],
    'B empty p' => ['<img src="a"><p></p>'],
    'B non cutline child' => ['<img src="a"><p><b>Cap.</b></p>'],
    'B comment then cutline' => ['<img src="a"><p><!-- c --><span class="cutline">Cap.</span></p>'],
    'C extra text' => ['<p>text<img src="a"></p><span class="cutline">Cap.</span>'],
    'C extra element' => ['<p><b>x</b><img src="a"></p><span class="cutline">Cap.</span>'],
    'C div wrapper' => ['<div><img src="a"></div><span class="cutline">Cap.</span>'],
    'C no cutline' => ['<p><img src="a"></p><span>Cap.</span>'],
    'C nothing after' => ['<p><img src="a"></p>'],
    'root image' => ['text <img src="a"> more'],
    'E empty aligned paragraph' => ['<p align="right"></p>'],
    'B first child is not the cutline' => ['<img src="a"><p><b>x</b><span class="cutline">Cap.</span></p>'],
]);
