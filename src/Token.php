<?php
declare(strict_types=1);

namespace TinyCompiler;

enum TokenType: string {
    case EOF = 'EOF';
    case ILLEGAL = 'ILLEGAL';

    case IDENT = 'IDENT'; // 标识符
    case NUMBER = 'NUMBER'; // 数字
    case STRING = 'STRING'; // 字符串

    case LET = 'LET'; // let
    case CONST = 'CONST'; // const
    case FUN = 'FUN'; // fun
    case RETURN = 'RETURN'; // return
    case IF = 'IF'; // if
    case ELSE = 'ELSE'; // else
    case WHILE = 'WHILE'; // while
    case FOR = 'FOR'; // for
    case TRUE = 'TRUE'; // true
    case FALSE = 'FALSE'; // false
    case NULL = 'NULL'; // null
    case INCLUDE = 'INCLUDE'; // include

    case ASSIGN = '=';
    case PLUS = '+';
    case MINUS = '-';
    case ASTERISK = '*';
    case SLASH = '/';
    case MOD = '%';

    case BANG = '!';
    case LT = '<';
    case GT = '>';

    case EQ = '==';
    case NE = '!=';
    case LE = '<=';
    case GE = '>=';

    case AND = '&&';
    case OR = '||';

    case LPAREN = '(';
    case RPAREN = ')';
    case LBRACE = '{';
    case RBRACE = '}';
    case LBRACKET = '[';
    case RBRACKET = ']';

    case COMMA = ',';
    case SEMICOLON = ';';
    case COLON = ':';
}

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

final class Keywords {
    /** @var array<string, TokenType> */
    public static array $map = [
        'let' => TokenType::LET,
        'const' => TokenType::CONST,
        'fun' => TokenType::FUN,
        'return' => TokenType::RETURN,
        'if' => TokenType::IF,
        'else' => TokenType::ELSE,
        'while' => TokenType::WHILE,
        'for' => TokenType::FOR,
        'true' => TokenType::TRUE,
        'false' => TokenType::FALSE,
        'null' => TokenType::NULL,
        'include' => TokenType::INCLUDE,
    ];

    public static function lookup(string $ident): TokenType {
        return self::$map[$ident] ?? TokenType::IDENT;
    }
}
