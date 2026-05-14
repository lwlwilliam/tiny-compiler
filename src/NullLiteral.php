<?php
declare(strict_types=1);

namespace TinyCompiler;

final class NullLiteral implements Expr
{
    public function __construct()
    {
    }

    public function kind(): string
    {
        return 'NullLiteral';
    }
}
