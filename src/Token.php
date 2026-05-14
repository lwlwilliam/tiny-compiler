<?php
declare(strict_types=1);

namespace TinyCompiler;

final class Token {
    public function __construct(
        public TokenType $type,
        public string $literal,
        public string $file,
        public int $line,
        public int $col,
    ) {}

    public function __toString(): string {
        return sprintf('%s(%s:%s)@%d:%d', $this->file, $this->type->name, $this->literal, $this->line, $this->col);
    }
}
