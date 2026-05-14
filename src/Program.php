<?php
declare(strict_types=1);

namespace TinyCompiler;

final class Program implements Node
{
    /** @param Stmt[] $stmts */
    public function __construct(public array $stmts)
    {
    }

    public function kind(): string
    {
        return 'Program';
    }
}
