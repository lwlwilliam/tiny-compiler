<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TinyCompiler\Lexer;
use TinyCompiler\Parser;
use TinyCompiler\CodeGen;
use TinyCompiler\ModuleBC;

try {
    $srcPath = $argv[1] ?? null;
    if ($srcPath === null) {
        fwrite(STDERR, "Usage: php compile.php <source.lang> [output.bc]\n");
        exit(1);
    }

    if (!is_file($srcPath)) {
        fwrite(STDERR, "file not found: $srcPath\n");
        exit(1);
    }

    $outPath = $argv[2] ?? (dirname($srcPath) . '/' . basename($srcPath, '.lang') . '.bc');

    $code = file_get_contents($srcPath);
    $lexer = new Lexer($srcPath, $code);

    $included = [];
    $parser = new Parser($lexer, dirname($srcPath), $included);
    $prog = $parser->parseProgram();

    $cg = new CodeGen();
    $module = $cg->emitModule($prog);

    $module->saveToFile($outPath);
    echo "Compiled: $srcPath -> $outPath\n";
    echo "  consts: " . count($module->consts) . "\n";
    echo "  globals: " . count($module->globals) . "\n";
    echo "  functions: " . count($module->functions) . "\n";
    echo "  entry ops: " . count($module->entry) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'compile error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . "\n");
    exit(2);
}
