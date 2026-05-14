<?php
declare(strict_types=1);

namespace TinyCompiler;

final class BinaryExpr implements Expr
{
    public function __construct(public string $op, public Expr $left, public Expr $right)
    {
    }

    public function kind(): string
    {
        return 'BinaryExpr';
    }
}
