<?php
declare(strict_types=1);

namespace TinyCompiler;

final class CallExpr implements Expr
{
    /** @param Expr[] $args */
    public function __construct(public Expr $callee, public array $args)
    {
    }

    public function kind(): string
    {
        return 'CallExpr';
    }
}
