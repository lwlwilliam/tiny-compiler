<?php
declare(strict_types=1);

namespace TinyCompiler;

final class ExprStmt implements Stmt
{
    public function __construct(public Expr $expr)
    {
    }

    public function kind(): string
    {
        return 'ExprStmt';
    }
}
