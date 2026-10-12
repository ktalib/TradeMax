<?php

declare(strict_types=1);
use Tests\TestCase;

arch('every application class declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('money primitives are final')
    ->expect('App\Support\Money')
    ->classes()
    ->toBeFinal();

arch('domain and money code never uses floats')
    ->expect(['App\Domain', 'App\Support\Money'])
    ->not->toUse(['floatval', 'round', 'floor', 'ceil', 'number_format']);

it('has no float types or casts in domain and money code', function (): void {
    $roots = [app_path('Domain'), app_path('Support/Money')];
    $offenders = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $tokens = PhpToken::tokenize((string) file_get_contents($file->getPathname()));

            foreach ($tokens as $token) {
                $isFloatType = $token->is(T_STRING) && strtolower($token->text) === 'float';
                $isFloatCast = $token->is(T_DOUBLE_CAST);

                if ($isFloatType || $isFloatCast) {
                    $offenders[] = $file->getPathname().':'.$token->line;
                }
            }
        }
    }

    expect($offenders)->toBe([]);
})->uses(TestCase::class);
