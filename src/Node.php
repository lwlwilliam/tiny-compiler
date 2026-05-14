<?php
declare(strict_types=1);

namespace TinyCompiler;

interface Node
{
    public function kind(): string;
}
