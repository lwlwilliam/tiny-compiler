<?php
declare(strict_types=1);

namespace TinyCompiler;

require_once __DIR__ . '/Token.php';

final class Lexer {
    private string $file;
    private string $input;
    private int $pos = 0; // 当前索引
    private int $readPos = 0;  // 预读索引
    private string $ch = "\0"; // 当前字符
    private int $line = 1;
    private int $col = 0;

    public function __construct(string $file, string $input) {
        $this->file = $file;
        $this->input = $input;
        $this->readChar();
    }

    private function readChar(): void {
        if ($this->readPos >= strlen($this->input)) {
            $this->ch = "\0";
        } else {
            $this->ch = $this->input[$this->readPos];
        }
        $this->pos = $this->readPos;
        $this->readPos++;
        if ($this->ch === "\n") {
            $this->line++;
            $this->col = 0;
        } else {
            $this->col++;
        }
    }

    private function peekChar(): string {
        if ($this->readPos >= strlen($this->input)) { return "\0"; }
        return $this->input[$this->readPos];
    }

    public function nextToken(): Token {
        $this->skipWhitespaceAndComments();
        $line = $this->line; $col = $this->col;
        $ch = $this->ch;

        switch ($ch) {
            case '=':
                if ($this->peekChar() === '=') {
                    $this->readChar();
                    $this->readChar();
                    return new Token(TokenType::EQ, '==', $this->file, $line, $col);
                }
                $this->readChar();
                return new Token(TokenType::ASSIGN, '=', $this->file, $line, $col);
            case '+':
                $this->readChar();
                return new Token(TokenType::PLUS, '+', $this->file, $line, $col);
            case '-':
                $this->readChar();
                return new Token(TokenType::MINUS, '-', $this->file, $line, $col);
            case '*':
                $this->readChar();
                return new Token(TokenType::ASTERISK, '*', $this->file, $line, $col);
            case '/':
                $this->readChar();
                return new Token(TokenType::SLASH, '/', $this->file, $line, $col);
            case '%':
                $this->readChar();
                return new Token(TokenType::MOD, '%', $this->file, $line, $col);
            case '!':
                if ($this->peekChar() === '=') {
                    $this->readChar();
                    $this->readChar();
                    return new Token(TokenType::NE, '!=', $this->file, $line, $col);
                }
                $this->readChar();
                return new Token(TokenType::BANG, '!', $this->file, $line, $col);
            case '<':
                if ($this->peekChar() === '=') {
                    $this->readChar();
                    $this->readChar();
                    return new Token(TokenType::LE, '<=', $this->file, $line, $col);
                }
                $this->readChar();
                return new Token(TokenType::LT, '<', $this->file, $line, $col);
            case '>':
                if ($this->peekChar() === '=') {
                    $this->readChar();
                    $this->readChar();
                    return new Token(TokenType::GE, '>=', $this->file, $line, $col);
                }
                $this->readChar();
                return new Token(TokenType::GT, '>', $this->file, $line, $col);
            case '&':
                if ($this->peekChar() === '&') {
                    $this->readChar();
                    $this->readChar();
                    return new Token(TokenType::AND, '&&', $this->file, $line, $col);
                }
                $this->readChar();
                return new Token(TokenType::ILLEGAL, '&', $this->file, $line, $col);
            case '|':
                if ($this->peekChar() === '|') {
                    $this->readChar();
                    $this->readChar();
                    return new Token(TokenType::OR, '||', $this->file, $line, $col);
                }
                $this->readChar();
                return new Token(TokenType::ILLEGAL, '|', $this->file, $line, $col);
            case '(':
                $this->readChar();
                return new Token(TokenType::LPAREN, '(', $this->file, $line, $col);
            case ')':
                $this->readChar();
                return new Token(TokenType::RPAREN, ')', $this->file, $line, $col);
            case '{':
                $this->readChar();
                return new Token(TokenType::LBRACE, '{', $this->file, $line, $col);
            case '}':
                $this->readChar();
                return new Token(TokenType::RBRACE, '}', $this->file, $line, $col);
            case '[':
                $this->readChar();
                return new Token(TokenType::LBRACKET, '[', $this->file, $line, $col);
            case ']':
                $this->readChar();
                return new Token(TokenType::RBRACKET, ']', $this->file, $line, $col);
            case ',':
                $this->readChar();
                return new Token(TokenType::COMMA, ',', $this->file, $line, $col);
            case ';':
                $this->readChar();
                return new Token(TokenType::SEMICOLON, ';', $this->file, $line, $col);
            case ':':
                $this->readChar();
                return new Token(TokenType::COLON, ':', $this->file, $line, $col);
            case '"':
            case "'":
                $lit = $this->readString($ch);
                return new Token(TokenType::STRING, $lit, $this->file, $line, $col);
            case "\0":
                return new Token(TokenType::EOF, '', $this->file, $line, $col);
            default:
                // 标识符&关键词
                if ($this->isLetter($ch)) {
                    $ident = $this->readIdentifier();
                    $type = Keywords::lookup($ident);
                    return new Token($type, $ident, $this->file, $line, $col);
                // 数字
                } elseif ($this->isDigit($ch)) {
                    $num = $this->readNumber();
                    return new Token(TokenType::NUMBER, $num, $this->file, $line, $col);
                }
                // 非法 token
                $this->readChar();
                return new Token(TokenType::ILLEGAL, $ch, $this->file, $line, $col);
        }
    }

    private function skipWhitespaceAndComments(): void {
        while (true) {
            // 忽略空白符
            while (ctype_space($this->ch)) { $this->readChar(); }
            // 行注释
            if ($this->ch === '/' && $this->peekChar() === '/') {
                while ($this->ch !== "\n" && $this->ch !== "\0") { $this->readChar(); }
                continue;
            }
            // 块注释
            if ($this->ch === '/' && $this->peekChar() === '*') {
                $this->readChar();
                $this->readChar();
                while (!($this->ch === '*' && $this->peekChar() === '/') && $this->ch !== "\0") {
                    $this->readChar();
                }
                if ($this->ch === '*') {
                    $this->readChar();
                    $this->readChar();
                }
                continue;
            }
            break;
        }
    }

    private function readIdentifier(): string {
        $start = $this->pos;
        while ($this->isLetter($this->ch) || $this->isDigit($this->ch) || $this->ch === '_') {
            $this->readChar();
        }
        return substr($this->input, $start, $this->pos - $start);
    }

    private function readNumber(): string {
        $start = $this->pos;
        while ($this->isDigit($this->ch)) { $this->readChar(); }
        if ($this->ch === '.' && $this->isDigit($this->peekChar())) {
            $this->readChar();
            while ($this->isDigit($this->ch)) { $this->readChar(); }
        }
        return substr($this->input, $start, $this->pos - $start);
    }

    private function readString(string $quote): string {
        $this->readChar(); // 跳过引号
        $start = $this->pos;
        $buf = '';
        while ($this->ch !== $quote && $this->ch !== "\0") {
            if ($this->ch === '\\') {
                $this->readChar();
                $esc = $this->ch;
                $map = [
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    '"' => '"',
                    '\'' => '\'',
                    '\\' => '\\',
                ];
                $buf .= $map[$esc] ?? $esc;
                $this->readChar();
                continue;
            }
            $buf .= $this->ch;
            $this->readChar();
        }
        if ($this->ch === $quote) {
            $this->readChar();
        }
        return $buf;
    }

    private function isLetter(string $ch): bool {
        return ctype_alpha($ch) || $ch === '_' ;
    }

    private function isDigit(string $ch): bool {
        return $ch >= '0' && $ch <= '9';
    }
}
