<?php
declare(strict_types=1);

namespace TinyCompiler;

final class ArrayLiteral implements Expr
{
    /** @param Expr[] $elements */
    public function __construct(public array $elements)
    {
    }

    public function kind(): string
    {
        return 'ArrayLiteral';
    }
}
