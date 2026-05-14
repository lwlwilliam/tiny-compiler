<?php
declare(strict_types=1);

namespace TinyCompiler;

final class ContinueStmt implements Stmt
{
    public function __construct()
    {
    }

    public function kind(): string
    {
        return 'ContinueStmt';
    }
}
