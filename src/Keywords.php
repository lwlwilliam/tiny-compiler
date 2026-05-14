<?php
declare(strict_types=1);

namespace TinyCompiler;

final class Keywords {
    /** @var array<string, TokenType> */
    public static array $map = [
        'let' => TokenType::LET,
        'const' => TokenType::CONST,
        'func' => TokenType::FUNC,
        'return' => TokenType::RETURN,
        'if' => TokenType::IF,
        'else' => TokenType::ELSE,
        'while' => TokenType::WHILE,
        'for' => TokenType::FOR,
        'break' => TokenType::BREAK,
        'continue' => TokenType::CONTINUE,
        'true' => TokenType::TRUE,
        'false' => TokenType::FALSE,
        'null' => TokenType::NULL,
        'include' => TokenType::INCLUDE,
    ];

    public static function lookup(string $ident): TokenType {
        return self::$map[$ident] ?? TokenType::IDENT;
    }
}
