<?php
declare(strict_types=1);

namespace TinyCompiler;

final class IndexExpr implements Expr
{
    public function __construct(public Expr $array, public Expr $index)
    {
    }

    public function kind(): string
    {
        return 'IndexExpr';
    }
}
