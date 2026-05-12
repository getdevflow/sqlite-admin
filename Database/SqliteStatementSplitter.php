<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Database;

final class SqliteStatementSplitter
{
    /** @return list<string> */
    public function split(string $sql, string $delimiter = ';'): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $quote = null;
        $lineComment = false;
        $blockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($lineComment) {
                $buffer .= $char;
                if ($char === "\n") {
                    $lineComment = false;
                }
                continue;
            }

            if ($blockComment) {
                $buffer .= $char;
                if ($char === '*' && $next === '/') {
                    $buffer .= $next;
                    $i++;
                    $blockComment = false;
                }
                continue;
            }

            if ($quote !== null) {
                $buffer .= $char;
                if ($char === $quote) {
                    if ($next === $quote) {
                        $buffer .= $next;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($char === '-' && $next === '-') {
                $buffer .= $char . $next;
                $i++;
                $lineComment = true;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $buffer .= $char . $next;
                $i++;
                $blockComment = true;
                continue;
            }

            if ($char === "'" || $char === '"') {
                $buffer .= $char;
                $quote = $char;
                continue;
            }

            if ($char === $delimiter) {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }
}
