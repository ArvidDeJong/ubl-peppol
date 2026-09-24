<?php

declare(strict_types=1);

/**
 * Guards the rules of the GitHub Pages site in docs/: front matter, Liquid use,
 * single-source facts and the developer credit. Nothing here breaks loudly otherwise.
 */
function docsPath(string $path = ''): string
{
    return dirname(__DIR__).'/docs'.($path === '' ? '' : '/'.$path);
}

/**
 * @return array<string, string>
 */
function frontMatter(string $file): array
{
    preg_match('/\A---\n(.*?)\n---\n/s', (string) file_get_contents($file), $match);

    $values = [];
    foreach (explode("\n", $match[1] ?? '') as $line) {
        if (preg_match('/^(\w+):\s*(.*)$/', $line, $pair)) {
            $value = trim($pair[2]);

            // An unquoted ": " makes the YAML invalid, and Jekyll then silently ignores all front matter.
            expect(str_contains($value, ': ') && ! str_starts_with($value, '"'))
                ->toBeFalse(basename($file).': quote the value of '.$pair[1]);

            $values[$pair[1]] = trim($value, '"');
        }
    }

    return $values;
}

test('every page has a title, a unique description and a unique nav order', function () {
    $pages = glob(docsPath('*.md'));
    $descriptions = [];
    $navOrders = [];

    foreach ($pages as $page) {
        $meta = frontMatter($page);

        expect($meta)->toHaveKeys(['title', 'description', 'nav_order'], basename($page));
        $descriptions[] = $meta['description'];
        $navOrders[] = $meta['nav_order'];
    }

    expect(count($pages))->toBeGreaterThan(5);
    expect(array_unique($descriptions))->toHaveCount(count($pages));
    expect(array_unique($navOrders))->toHaveCount(count($pages));
});

test('pages use Liquid only where it is intended', function () {
    foreach (glob(docsPath('*.md')) as $page) {
        if (basename($page) === 'faq.md') {
            continue;
        }

        expect(file_get_contents($page))->not->toMatch('/\{\{|\{%/', basename($page));
    }
});

test('the home page links every page, and every link between pages resolves', function () {
    $home = (string) file_get_contents(docsPath('index.md'));

    foreach (glob(docsPath('*.md')) as $page) {
        $name = basename($page);

        if ($name !== 'index.md') {
            expect($home)->toContain('('.$name.')');
        }

        preg_match_all('/\]\(([a-z0-9-]+\.md)(#[^)]*)?\)/', (string) file_get_contents($page), $links);

        foreach ($links[1] as $target) {
            expect(is_file(docsPath($target)))->toBeTrue($name.' links to '.$target.', which does not exist');
        }
    }
});

test('the FAQ stays between six and ten questions', function () {
    $questions = substr_count("\n".file_get_contents(docsPath('_data/faq.yml')), "\n- q: ");

    expect($questions)->toBeGreaterThanOrEqual(6)->toBeLessThanOrEqual(10);
});

test('inline scripts survive the theme compressing each page to one line', function () {
    $pages = glob(docsPath('*.md'));
    expect($pages)->not->toBeEmpty();

    foreach ($pages as $page) {
        preg_match_all('/<script>(.*?)<\/script>/s', (string) file_get_contents($page), $scripts);

        foreach ($scripts[1] as $script) {
            expect($script)->not->toMatch('/^\s*\/\//m', basename($page).': use /* */ instead of // comments');
        }
    }
});

test('the FAQ, structured data and llms.txt read from the shared data', function () {
    $faq = (string) file_get_contents(docsPath('_data/faq.yml'));

    expect(substr_count($faq, "\n- q: ") + (str_starts_with(ltrim($faq), '- q: ') ? 1 : 0))->toBeGreaterThanOrEqual(6);
    expect(substr_count($faq, '- q: '))->toBe(substr_count($faq, '  a: '));

    expect(file_get_contents(docsPath('faq.md')))->toContain('site.data.faq');
    expect(file_get_contents(docsPath('llms.txt')))
        ->toContain('permalink: /llms.txt')
        ->toContain('site.data.faq')
        ->toContain('site.pages');
    expect(file_get_contents(docsPath('_includes/head_custom.html')))
        ->toContain('"FAQPage"')
        ->toContain('"SoftwareSourceCode"')
        ->toContain('site.data.faq');
});

test('the YAML files build, because one bad value fails the whole Pages build', function () {
    // An unquoted ": " makes the YAML invalid. Jekyll then aborts the build and GitHub keeps
    // serving the last version that did build, so the site looks fine while it is months old.
    foreach (['_config.yml', '_data/faq.yml'] as $file) {
        $path = docsPath($file);

        if (! is_file($path)) {
            continue;
        }

        $blockIndent = null;

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $number => $line) {
            $indent = strlen($line) - strlen(ltrim($line));

            if ($blockIndent !== null) {
                // Inside a > or | block every line is text, whatever it contains.
                if (trim($line) === '' || $indent > $blockIndent) {
                    continue;
                }

                $blockIndent = null;
            }

            if (! preg_match('/^(\s*)(?:-\s+)?(\w+):(?:\s+(\S.*))?$/', $line, $pair)) {
                continue;
            }

            $value = trim($pair[3] ?? '');

            if ($value === '') {
                continue;
            }

            if (str_starts_with($value, '>') || str_starts_with($value, '|')) {
                $blockIndent = strlen($pair[1]);

                continue;
            }

            $quoted = str_starts_with($value, '"')
                || str_starts_with($value, "'")
                || str_starts_with($value, '[');

            expect(str_contains($value, ': ') && ! $quoted)
                ->toBeFalse($file.' line '.($number + 1).': quote the value of '.$pair[2]);
        }
    }
});

test('the config holds the package facts and the sitemap plugin', function () {
    expect(file_get_contents(docsPath('_config.yml')))
        ->toContain('- jekyll-sitemap')
        ->toContain('name: darvis/ubl-peppol')
        ->toContain('company: ARVID.NL')
        ->toContain('url: https://arvid.nl')
        ->not->toContain('footer_content');
});

test('the footer credits ARVID.NL without a personal name', function () {
    $footer = (string) file_get_contents(docsPath('_includes/footer_custom.html'));

    expect($footer)->toContain('site.developer.company')
        ->toContain('site.developer.url')
        ->not->toContain('Arvid de Jong')
        ->not->toMatch('/developed by|made by/i');

    expect(file_get_contents(docsPath('_includes/head_custom.html')))->not->toContain('"Person"');
});

test('an element id on a page never equals the anchor the theme gives a heading', function () {
    // The theme gives every heading an id made from its text. An element with the same id loses:
    // getElementById() returns the heading, as the validator form found out in 1.11.0.
    foreach (glob(docsPath('*.md')) as $page) {
        $content = (string) file_get_contents($page);

        preg_match_all('/^#{1,6}\s+(.+)$/m', $content, $headings);
        $anchors = array_map(
            fn (string $heading) => trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(str_replace('`', '', $heading))), '-'),
            $headings[1]
        );

        preg_match_all('/\sid="([^"]+)"/', $content, $ids);

        foreach ($ids[1] as $id) {
            expect(in_array($id, $anchors, true))->toBeFalse(basename($page).': the id "'.$id.'" is also the anchor of a heading');
        }
    }
});
