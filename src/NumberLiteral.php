<?php
declare(strict_types=1);

namespace TinyCompiler;

final class NumberLiteral implements Expr
{
    public function __construct(public string $raw)
    {
    }

    public function kind(): string
    {
        return 'NumberLiteral';
    }
}
