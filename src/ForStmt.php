<?php
declare(strict_types=1);

namespace TinyCompiler;

final class ForStmt implements Stmt
{
    public function __construct(public ?Stmt $init, public ?Expr $cond, public ?Expr $step, public Stmt $body)
    {
    }

    public function kind(): string
    {
        return 'ForStmt';
    }
}
