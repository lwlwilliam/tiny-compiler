<?php
declare(strict_types=1);

namespace TinyCompiler;

enum TokenType: string {
    case EOF = 'EOF';
    case ILLEGAL = 'ILLEGAL';

    case IDENT = 'IDENT';
    case NUMBER = 'NUMBER';
    case STRING = 'STRING';

    case LET = 'LET';
    case CONST = 'CONST';
    case FUNC = 'FUNC';
    case RETURN = 'RETURN';
    case IF = 'IF';
    case ELSE = 'ELSE';
    case WHILE = 'WHILE';
    case FOR = 'FOR';
    case BREAK = 'BREAK';
    case CONTINUE = 'CONTINUE';
    case TRUE = 'TRUE';
    case FALSE = 'FALSE';
    case NULL = 'NULL';
    case INCLUDE = 'INCLUDE';

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
