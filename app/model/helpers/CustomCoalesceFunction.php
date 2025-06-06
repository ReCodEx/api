<?php

namespace DoctrineExtensions\Query\Functions;

use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\TokenType;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;

/**
 * "COALESCE_SUB" "(" "(" Subselect ")" {"," "(" Subselect ")" }* ")"
 *
 * This is actually a workaround since regular COALESCE statement does not
 * support nested selects in DQL. This one expects only subselects.
 *
 * Final SQL uses the same COALESCE function as the COALESCE statement in DQL.
 */
class CoalesceSubselectsFunction extends FunctionNode
{
    public $subselectExpressions = [];

    /**
     * @override
     */
    public function getSql(\Doctrine\ORM\Query\SqlWalker $sqlWalker)
    {
        $platform = $sqlWalker->getConnection()->getDatabasePlatform();

        $args = [];

        foreach ($this->subselectExpressions as $expression) {
            $args[] = $sqlWalker->walkSubselect($expression);
        }

        return 'COALESCE( (' . implode('), (', $args) . ') )';
    }

    /**
     * @override
     */
    public function parse(\Doctrine\ORM\Query\Parser $parser)
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->subselectExpressions[] = $parser->Subselect();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);

        while ($parser->getLexer()->isNextToken(TokenType::T_COMMA)) {
            $parser->match(TokenType::T_COMMA);

            $parser->match(TokenType::T_OPEN_PARENTHESIS);
            $this->subselectExpressions[] = $parser->Subselect();
            $parser->match(TokenType::T_CLOSE_PARENTHESIS);
        }

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
