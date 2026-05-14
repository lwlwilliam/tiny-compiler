<?php
declare(strict_types=1);

namespace TinyCompiler;

final class BoolLiteral implements Expr
{
    public function __construct(public bool $value)
    {
    }

    public function kind(): string
    {
        return 'BoolLiteral';
    }
}
