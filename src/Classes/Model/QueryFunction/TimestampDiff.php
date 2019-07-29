<?php

namespace Helio\Panel\Model\QueryFunction;

use \Doctrine\ORM\Query\Parser;
use \Doctrine\ORM\Query\QueryException;
use \Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;

/**
 * @author Przemek Sobstel <przemek@sobstel.org>
 */
class TimestampDiff extends FunctionNode
{
    /**
     * @var FunctionNode null
     */
    public $firstDatetimeExpression = null;

    /**
     * @var FunctionNode null
     */
    public $secondDatetimeExpression = null;

    /**
     * @var mixed
     */
    public $unit = null;

    /**
     * @param Parser $parser
     * @throws QueryException
     */
    public function parse(Parser $parser): void
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $parser->match(Lexer::T_IDENTIFIER);
        $lexer = $parser->getLexer();
        $this->unit = $lexer->token['value'];
        $parser->match(Lexer::T_COMMA);
        $this->firstDatetimeExpression = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_COMMA);
        $this->secondDatetimeExpression = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    /**
     * @param SqlWalker $sql_walker
     * @return string
     */
    public function getSql(SqlWalker $sql_walker): string
    {
        return sprintf(
            'TIMESTAMPDIFF(%s, %s, %s)',
            $this->unit,
            $this->firstDatetimeExpression->dispatch($sql_walker),
            $this->secondDatetimeExpression->dispatch($sql_walker)
        );
    }
}