<?php
declare(strict_types=1);

namespace TinyCompiler;

final class StringLiteral implements Expr
{
    public function __construct(public string $value)
    {
    }

    public function kind(): string
    {
        return 'StringLiteral';
    }
}
