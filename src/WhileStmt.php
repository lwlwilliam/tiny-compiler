<?php
declare(strict_types=1);

namespace TinyCompiler;

final class WhileStmt implements Stmt
{
    public function __construct(public Expr $cond, public Stmt $body)
    {
    }

    public function kind(): string
    {
        return 'WhileStmt';
    }
}
