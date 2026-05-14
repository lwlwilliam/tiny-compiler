<?php
declare(strict_types=1);

namespace TinyCompiler;

final class Ident implements Expr
{
    public function __construct(public string $name)
    {
    }

    public function kind(): string
    {
        return 'Ident';
    }
}
