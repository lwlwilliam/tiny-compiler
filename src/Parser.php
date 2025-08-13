<?php
declare(strict_types=1);

namespace TinyCompiler;

require_once __DIR__ . '/Token.php';
require_once __DIR__ . '/AST.php';
require_once __DIR__ . '/Lexer.php';

final class ParseError extends \Exception
{
}

final class Parser
{
    private Lexer $lex;
    private Token $cur;
    private Token $peek;
    private string $baseDir;
    /** @var array<string,bool> */
    private array $included = [];

    public function __construct(Lexer $lex, string $baseDir = '.', ?array &$included = null)
    {
        $this->lex = $lex;
        $this->cur = $this->lex->nextToken();
        $this->peek = $this->lex->nextToken();
        $this->baseDir = $baseDir;
        if ($included !== null) {
            $this->included =& $included;
        }
    }

    private function wrapException(string $message)
    {
        return $message. ' at '. $this->cur->file. ' line '. $this->cur->line. ' column '. $this->cur->col;
    }

    private function next(): void
    {
        $this->cur = $this->peek;
        $this->peek = $this->lex->nextToken();
    }

    /**
     * @throws ParseError
     */
    private function expect(TokenType $t, string $msg = ''): Token
    {
        if ($this->cur->type !== $t) {
            $m = $msg !== '' ? $msg :
                sprintf(
                    "expected %s, got %s at %s line %d column %d"
                    , $t->name
                    , $this->cur->type->name
                    , $this->cur->file
                    , $this->cur->line
                    , $this->cur->col
                );
            throw new ParseError($m);
        }
        $tok = $this->cur;
        $this->next();
        return $tok;
    }

    /**
     * @throws ParseError
     */
    public function parseProgram(): Program
    {
        $stmts = [];
        while ($this->cur->type !== TokenType::EOF) {
            $stmts[] = $this->parseStatement();
        }
        return new Program($stmts);
    }

    /**
     * @throws ParseError
     */
    private function parseStatement(): Stmt
    {
        return match ($this->cur->type) {
            TokenType::LET => $this->parseLet(),
            TokenType::CONST => $this->parseConst(),
            TokenType::FUN => $this->parseFunDecl(),
            TokenType::IF => $this->parseIf(),
            TokenType::WHILE => $this->parseWhile(),
            TokenType::FOR => $this->parseFor(),
            TokenType::RETURN => $this->parseReturn(),
            TokenType::INCLUDE => $this->parseInclude(),
            TokenType::LBRACE => $this->parseBlockStmt(),
            default => $this->parseExprStmt(),
        };
    }

    /**
     * @throws ParseError
     */
    private function parseInclude(): Stmt
    {
        $this->expect(TokenType::INCLUDE);
        $tok = $this->expect(TokenType::STRING);
        $this->expect(TokenType::SEMICOLON);
        $path = $tok->literal;
        $full = $this->resolvePath($path);
        if (isset($this->included[$full])) { // todo: 这里以后可能要修改，除非像 c 语言一样，只有一个入口。像 php 这种，多次引入可以多次执行。其实就连 c 语言也需要搞 #ifndef xxx.h xxx #endif
            // 已引入，视为空语句
            return new BlockStmt([]);
        }
        if (!is_file($full)) {
            throw new ParseError($this->wrapException('file not found: ' . $full));
        }
        $this->included[$full] = true; // 标记是否已引入，已引入的就不会执行这里的代码
        $code = file_get_contents($full);
        $sub = new Parser(new Lexer($full, $code), dirname($full), $this->included); // 如果有引入的代码，先执行引入文件中的
        $prog = $sub->parseProgram();
        return new BlockStmt($prog->stmts);
    }

    /**
     * @throws ParseError
     */
    private function parseBlockStmt(): BlockStmt
    {
        $this->expect(TokenType::LBRACE);
        $stmts = [];
        while ($this->cur->type !== TokenType::RBRACE) {
            if ($this->cur->type === TokenType::EOF) {
                throw new ParseError($this->wrapException('unexpected EOF in block statement'));
            }
            $stmts[] = $this->parseStatement();
        }
        $this->expect(TokenType::RBRACE);
        return new BlockStmt($stmts);
    }

    /**
     * @throws ParseError
     */
    private function parseLet(): LetStmt
    {
        $this->expect(TokenType::LET);
        $nameTok = $this->expect(TokenType::IDENT);
        $init = null;
        if ($this->cur->type === TokenType::ASSIGN) {
            $this->next();
            $init = $this->parseExpression();
        }
        $this->expect(TokenType::SEMICOLON);
        return new LetStmt($nameTok->literal, $init);
    }

    /**
     * @throws ParseError
     */
    private function parseConst(): ConstStmt
    {
        $this->expect(TokenType::CONST);
        $nameTok = $this->expect(TokenType::IDENT);
        $this->expect(TokenType::ASSIGN);
        $init = $this->parseExpression();
        $this->expect(TokenType::SEMICOLON);
        return new ConstStmt($nameTok->literal, $init);
    }

    /**
     * @throws ParseError
     */
    private function parseFunDecl(): FunDecl
    {
        $this->expect(TokenType::FUN);
        $nameTok = $this->expect(TokenType::IDENT);
        $this->expect(TokenType::LPAREN);
        $params = [];
        if ($this->cur->type !== TokenType::RPAREN) {
            do {
                $tok = $this->expect(TokenType::IDENT);
                $params[] = $tok->literal;
                if ($this->cur->type !== TokenType::COMMA) {
                    break;
                }
                $this->next();
            } while (true);
        }
        $this->expect(TokenType::RPAREN);
        $body = $this->parseBlockStmt();
        return new FunDecl($nameTok->literal, $params, $body);
    }

    /**
     * @throws ParseError
     */
    private function parseIf(): IfStmt
    {
        $this->expect(TokenType::IF);
        $this->expect(TokenType::LPAREN);
        $cond = $this->parseExpression();
        $this->expect(TokenType::RPAREN);
        $then = $this->parseStatement();
        $else = null;
        if ($this->cur->type === TokenType::ELSE) {
            $this->next();
            $else = $this->parseStatement();
        }
        return new IfStmt($cond, $then, $else);
    }

    /**
     * @throws ParseError
     */
    private function parseWhile(): WhileStmt
    {
        $this->expect(TokenType::WHILE);
        $this->expect(TokenType::LPAREN);
        $cond = $this->parseExpression();
        $this->expect(TokenType::RPAREN);
        $body = $this->parseStatement();
        return new WhileStmt($cond, $body);
    }

    /**
     * @throws ParseError
     */
    private function parseFor(): ForStmt
    {
        $this->expect(TokenType::FOR);
        $this->expect(TokenType::LPAREN);
        $init = null;
        if ($this->cur->type !== TokenType::SEMICOLON) {
            if ($this->cur->type === TokenType::LET) {
                $init = $this->parseLet();
            } elseif ($this->cur->type === TokenType::CONST) {
                $init = $this->parseConst();
            } else {
                $expr = $this->parseExprStmtNoConsumeSemicolon();
                $this->expect(TokenType::SEMICOLON);
                $init = new ExprStmt($expr);
            }
        } else {
            $this->expect(TokenType::SEMICOLON);
        }
        $cond = null;
        if ($this->cur->type !== TokenType::SEMICOLON) {
            $cond = $this->parseExpression();
        }
        $this->expect(TokenType::SEMICOLON);
        $step = null;
        if ($this->cur->type !== TokenType::RPAREN) {
            $step = $this->parseExpression();
        }
        $this->expect(TokenType::RPAREN);
        $body = $this->parseStatement();
        return new ForStmt($init, $cond, $step, $body);
    }

    /**
     * @throws ParseError
     */
    private function parseReturn(): ReturnStmt
    {
        $this->expect(TokenType::RETURN);
        if ($this->cur->type === TokenType::SEMICOLON) {
            $this->next();
            return new ReturnStmt(null);
        }
        $expr = $this->parseExpression();
        $this->expect(TokenType::SEMICOLON);
        return new ReturnStmt($expr);
    }

    /**
     * @throws ParseError
     */
    private function parseExprStmt(): ExprStmt
    {
        $expr = $this->parseExpression();
        $this->expect(TokenType::SEMICOLON);
        return new ExprStmt($expr);
    }

    private function parseExprStmtNoConsumeSemicolon(): Expr
    {
        return $this->parseExpression();
    }

    // 表达式优先级
    private const array PREC = [
        'LOWEST' => 0,
        'ASSIGN' => 1, // =
        'OR' => 2, // ||
        'AND' => 3, // &&
        'EQUAL' => 4, // ==, !=
        'COMPARE' => 5, // <, <=, >, >=
        'SUM' => 6, // +, -
        'PRODUCT' => 7, // *, /, %
        'PREFIX' => 8, // !, -
        'CALL_INDEX' => 9, // (, [
    ];

    private function precedenceOf(TokenType $t): int
    {
        return match ($t) {
            TokenType::ASSIGN => self::PREC['ASSIGN'], // ==
            TokenType::OR => self::PREC['OR'], // ||
            TokenType::AND => self::PREC['AND'], // &&
            TokenType::EQ, TokenType::NE => self::PREC['EQUAL'], // ==, !=
            TokenType::LT, TokenType::LE, TokenType::GT, TokenType::GE => self::PREC['COMPARE'], // <, <=, >, >=
            TokenType::PLUS, TokenType::MINUS => self::PREC['SUM'], // +, -
            TokenType::ASTERISK, TokenType::SLASH, TokenType::MOD => self::PREC['PRODUCT'], // *, /, %
            TokenType::LPAREN, TokenType::LBRACKET => self::PREC['CALL_INDEX'], // (, [
            default => self::PREC['LOWEST'],
        };
    }

    /**
     * @throws ParseError
     */
    private function parseExpression(int $prec = 0): Expr
    {
        $left = $this->parsePrefix();
        while (true) {
            $t = $this->cur->type;
            // 在这些分隔符处结束当前表达式（用于数组元素、函数参数、语句等场景）
            if ($t === TokenType::SEMICOLON || $t === TokenType::COMMA || $t === TokenType::RPAREN || $t === TokenType::RBRACKET || $t === TokenType::RBRACE) {
                break;
            }
            $curPrec = $this->precedenceOf($t);
            if ($curPrec < $prec) {
                break;
            }
            if ($t === TokenType::ASSIGN) {
                // 右结合
                $this->next();
                $right = $this->parseExpression(self::PREC['ASSIGN']);
                $left = new AssignExpr($left, $right);
                continue;
            }
            if ($t === TokenType::LPAREN) {
                $left = $this->finishCall($left);
                continue;
            }
            if ($t === TokenType::LBRACKET) {
                $this->next();
                $index = $this->parseExpression();
                $this->expect(TokenType::RBRACKET);
                $left = new IndexExpr($left, $index);
                continue;
            }
            $left = $this->parseInfix($left, $curPrec);
        }
        return $left;
    }

    /**
     * @throws ParseError
     */
    private function parsePrefix(): Expr
    {
        return match ($this->cur->type) {
            TokenType::IDENT => (function () {
                $name = $this->cur->literal;
                $this->next();
                return new Ident($name);
            })(),
            TokenType::NUMBER => (function () {
                $v = $this->cur->literal;
                $this->next();
                return new NumberLiteral($v);
            })(),
            TokenType::STRING => (function () {
                $v = $this->cur->literal;
                $this->next();
                return new StringLiteral($v);
            })(),
            TokenType::TRUE => (function () {
                $this->next();
                return new BoolLiteral(true);
            })(),
            TokenType::FALSE => (function () {
                $this->next();
                return new BoolLiteral(false);
            })(),
            TokenType::NULL => (function () {
                $this->next();
                return new NullLiteral();
            })(),
            TokenType::LPAREN => $this->parseGrouped(),
            TokenType::LBRACKET => $this->parseArrayLiteral(),
            TokenType::MINUS => $this->parseUnary('-'),
            TokenType::BANG => $this->parseUnary('!'),
            default => throw new ParseError($this->wrapException('unexpected token in expression')),
        };
    }

    /**
     * @throws ParseError
     */
    private function parseUnary(string $op): Expr
    {
        $this->next();
        $expr = $this->parseExpression(self::PREC['PREFIX']);
        return new UnaryExpr($op, $expr);
    }

    /**
     * @throws ParseError
     */
    private function parseGrouped(): Expr
    {
        $this->expect(TokenType::LPAREN);
        $expr = $this->parseExpression();
        $this->expect(TokenType::RPAREN);
        return $expr;
    }

    /**
     * @throws ParseError
     */
    private function parseArrayLiteral(): ArrayLiteral
    {
        $this->expect(TokenType::LBRACKET);
        $elements = [];
        if ($this->cur->type !== TokenType::RBRACKET) {
            do {
                $elements[] = $this->parseExpression();
                if ($this->cur->type !== TokenType::COMMA) {
                    break;
                }
                $this->next();
            } while (true);
        }
        $this->expect(TokenType::RBRACKET);
        return new ArrayLiteral($elements);
    }

    /**
     * @throws ParseError
     */
    private function parseInfix(Expr $left, int $prec): Expr
    {
        $opTok = $this->cur;
        $this->next();
        $right = $this->parseExpression($prec + 1);
        $op = match ($opTok->type) {
            TokenType::PLUS => '+',
            TokenType::MINUS => '-',
            TokenType::ASTERISK => '*',
            TokenType::SLASH => '/',
            TokenType::MOD => '%',
            TokenType::EQ => '==',
            TokenType::NE => '!=',
            TokenType::LT => '<',
            TokenType::LE => '<=',
            TokenType::GT => '>',
            TokenType::GE => '>=',
            TokenType::AND => '&&',
            TokenType::OR => '||',
            default => throw new ParseError($this->wrapException('unknown infix operator: ' . $opTok->literal)),
        };
        return new BinaryExpr($op, $left, $right);
    }

    /**
     * @throws ParseError
     */
    private function finishCall(Expr $callee): CallExpr
    {
        $this->expect(TokenType::LPAREN);
        $args = [];
        if ($this->cur->type !== TokenType::RPAREN) {
            do {
                $args[] = $this->parseExpression();
                if ($this->cur->type !== TokenType::COMMA) {
                    break;
                }
                $this->next();
            } while (true);
        }
        $this->expect(TokenType::RPAREN);
        return new CallExpr($callee, $args);
    }

    private function resolvePath(string $path): string
    {
        if ($this->isAbsPath($path)) {
            return $path;
        }
        // 规范化 .. 与 .
        $full = $this->baseDir . DIRECTORY_SEPARATOR . $path;
        $real = realpath($full);
        return $real !== false ? $real : $full;
    }

    private function isAbsPath(string $p): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return preg_match('/^[A-Za-z]:\\\\|^\\\\\\\\/', $p) === 1;
        }
        return str_starts_with($p, '/');
    }
}
