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
        fwrite(STDERR, "Usage: php compile.php [--php] <source.lang> [output]\n");
        exit(1);
    }

    $asPhp = false;
    $args = array_slice($argv, 1);
    if ($args[0] === '--php') {
        $asPhp = true;
        array_shift($args);
    }
    if (empty($args)) {
        fwrite(STDERR, "Usage: php compile.php [--php] <source.lang> [output]\n");
        exit(1);
    }

    $srcPath = $args[0];
    if (!is_file($srcPath)) {
        fwrite(STDERR, "file not found: $srcPath\n");
        exit(1);
    }

    $defaultExt = $asPhp ? '.bc.php' : '.bc';
    $outPath = $args[1] ?? (dirname($srcPath) . '/' . basename($srcPath, '.lang') . $defaultExt);

    $code = file_get_contents($srcPath);
    $lexer = new Lexer($srcPath, $code);

    $included = [];
    $parser = new Parser($lexer, dirname($srcPath), $included);
    $prog = $parser->parseProgram();

    $cg = new CodeGen();
    $module = $cg->emitModule($prog);

    if ($asPhp) {
        $module->saveToPhpFile($outPath);
    } else {
        $module->saveToFile($outPath);
    }
    $size = filesize($outPath);
    $format = $asPhp ? 'PHP (human-readable)' : 'binary';
    echo "Compiled: $srcPath -> $outPath ($format)\n";
    echo "  size: " . number_format($size) . " bytes\n";
    echo "  consts: " . count($module->consts) . "\n";
    echo "  globals: " . count($module->globals) . "\n";
    echo "  functions: " . count($module->functions) . "\n";
    echo "  entry ops: " . count($module->entry) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'compile error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . "\n");
    exit(2);
}
