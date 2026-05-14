<?php
declare(strict_types=1);

namespace TinyCompiler;

final class IfStmt implements Stmt
{
    public function __construct(public Expr $cond, public Stmt $then, public ?Stmt $else)
    {
    }

    public function kind(): string
    {
        return 'IfStmt';
    }
}
