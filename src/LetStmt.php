<?php
declare(strict_types=1);

namespace TinyCompiler;

final class LetStmt implements Stmt
{
    public function __construct(public string $name, public ?Expr $init)
    {
    }

    public function kind(): string
    {
        return 'LetStmt';
    }
}
