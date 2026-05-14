<?php
declare(strict_types=1);

namespace TinyCompiler;

final class BlockStmt implements Stmt
{
    /** @param Stmt[] $stmts */
    public function __construct(public array $stmts)
    {
    }

    public function kind(): string
    {
        return 'BlockStmt';
    }
}
