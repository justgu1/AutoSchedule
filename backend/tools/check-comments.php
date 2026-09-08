#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Checa as regras de comentário do docs/code-style.md que nenhuma ferramenta pronta cobre.
 * Linha de tipo (@param/@return/@var/@template) não conta como prosa.
 */

const MAX_PROSE_LINES = 2;

const FORBIDDEN = [
    '#\b(GET|POST|PUT|PATCH|DELETE)\s+/#' => 'nomeia rota (rota muda, comentário não acompanha)',
    '#/api/#' => 'nomeia rota (rota muda, comentário não acompanha)',
    '#\bautoschedule_[a-z_]+#' => 'nomeia role de banco',
    '#(ANTES DE|DEPOIS DE|SEMPRE|NUNCA|PRIMEIRO) [A-Z]#u' => 'afirma ordem/garantia em caixa alta (a ordem vive no pipeline, não aqui)',
];

$roots = ['src', 'tests', 'routes', 'config', 'database', 'bin', 'public'];
$failures = [];

foreach ($roots as $root) {
    $dir = __DIR__ . '/../' . $root;

    if (!is_dir($dir)) {
        continue;
    }

    /** @var iterable<\SplFileInfo> $files */
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace(realpath(__DIR__ . '/..') . '/', '', (string) $file->getRealPath());
        check((string) $file->getRealPath(), $relative, $failures);
    }
}

if ($failures !== []) {
    echo "Regras de comentário violadas (docs/code-style.md):\n\n";
    echo implode("\n", $failures), "\n\n", count($failures), " problema(s).\n";
    exit(1);
}

echo "Comentários OK.\n";
exit(0);

/** @param list<string> $failures */
function check(string $absolute, string $relative, array &$failures): void
{
    $lines = file($absolute, FILE_IGNORE_NEW_LINES);

    if ($lines === false) {
        return;
    }

    $blockStart = null;
    $proseLines = 0;

    foreach ($lines as $index => $raw) {
        $line = trim($raw);
        $isComment = str_starts_with($line, '//') || str_starts_with($line, '/*') || str_starts_with($line, '*');

        if ($isComment) {
            $text = trim(preg_replace('#^(//+|/\*+|\*+/?)#', '', $line) ?? '');
            $isType = $text === '' || str_starts_with($text, '@');

            if (!$isType) {
                $blockStart ??= $index + 1;
                ++$proseLines;

                // Só em src/: teste nomeia rota e role porque é o que ele exercita, e nome errado ali quebra o teste.
                if (str_starts_with($relative, 'src/')) {
                    flagForbidden($text, $relative, $index + 1, $failures);
                }
            }

            continue;
        }

        if ($proseLines > MAX_PROSE_LINES) {
            $failures[] = sprintf('%s:%d  bloco de prosa com %d linhas (máximo %d)', $relative, $blockStart, $proseLines, MAX_PROSE_LINES);
        }

        $blockStart = null;
        $proseLines = 0;
    }
}

/** @param list<string> $failures */
function flagForbidden(string $text, string $relative, int $line, array &$failures): void
{
    foreach (FORBIDDEN as $pattern => $reason) {
        if (preg_match($pattern, $text) === 1) {
            $failures[] = sprintf('%s:%d  %s', $relative, $line, $reason);

            return;
        }
    }
}
