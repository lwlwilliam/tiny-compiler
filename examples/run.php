<?php

declare(strict_types=1);

require_once __DIR__. '/../vendor/autoload.php';

use TinyCompiler\Lexer;
use TinyCompiler\Parser;
use TinyCompiler\CodeGen;
use TinyCompiler\VM;

try {
    $srcPath = $argv[1] ?? __DIR__ . '/codes/demo.lang';

    if (!is_file($srcPath)) {
        fwrite(STDERR, "file not found: $srcPath\n");
        exit(1);
    }

    $code = file_get_contents($srcPath);
    $lexer = new Lexer($srcPath, $code);

    $included = [];
    $parser = new Parser($lexer, dirname($srcPath), $included);
    $prog = $parser->parseProgram();

    $cg = new CodeGen();
    $module = $cg->emitModule($prog);

    $vm = new VM($module->consts, $module->globals, $module->functions);
    $vm->run($module->entry);
} catch (Throwable $e) {
    fwrite(STDERR, 'runtime error: ' . $e->getMessage() . ":". $e->getFile(). " ". $e->getLine(). "\n");
    exit(2);
}
