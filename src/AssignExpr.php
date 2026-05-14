<?php
declare(strict_types=1);

namespace TinyCompiler;

final class AssignExpr implements Expr
{
    public function __construct(public Expr $left, public Expr $right)
    {
    }

    public function kind(): string
    {
        return 'AssignExpr';
    }
}
