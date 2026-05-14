<?php
declare(strict_types=1);

namespace TinyCompiler;

final class UnaryExpr implements Expr
{
    public function __construct(public string $op, public Expr $expr)
    {
    }

    public function kind(): string
    {
        return 'UnaryExpr';
    }
}
