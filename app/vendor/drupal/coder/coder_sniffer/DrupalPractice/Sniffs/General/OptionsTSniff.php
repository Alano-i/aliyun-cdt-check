<?php
/**
 * \DrupalPractice\Sniffs\General\OptionsTSniff
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @link     http://pear.php.net/package/PHP_CodeSniffer
 */

namespace DrupalPractice\Sniffs\General;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Checks that values in #otions form arrays are translated.
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @link     http://pear.php.net/package/PHP_CodeSniffer
 */
class OptionsTSniff implements Sniff
{


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_CONSTANT_ENCAPSED_STRING];

    }//end register()


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the function
     *                                               name in the stack.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        // Look for the string "#options".
        $tokens = $phpcsFile->getTokens();
        if ($tokens[$stackPtr]['content'] !== '"#options"' && $tokens[$stackPtr]['content'] !== "'#options'") {
            return;
        }

        // Look for an opening array pattern that starts to define #options
        // values.
        $statementEnd = $phpcsFile->findNext(T_SEMICOLON, ($stackPtr + 1));
        $arrayString  = $phpcsFile->getTokensAsString(($stackPtr + 1), ($statementEnd - $stackPtr));
        // Cut out all the white space.
        $arrayString = preg_replace('/\s+/', '', $arrayString);

        if (strpos($arrayString, '=>array(') !== 0 && strpos($arrayString, ']=array(') !== 0) {
            return;
        }

        // We only search within the #options array.
        $arrayToken         = $phpcsFile->findNext(T_ARRAY, ($stackPtr + 1));
        $statementEnd       = $tokens[$arrayToken]['parenthesis_closer'];
        $nestestParenthesis = $tokens[$arrayToken]['nested_parenthesis'];
        $nestestParenthesis[$tokens[$arrayToken]['parenthesis_opener']] = $tokens[$arrayToken]['parenthesis_closer'];

        // Go through the array by examining stuff after "=>".
        $arrow = $phpcsFile->findNext(T_DOUBLE_ARROW, ($stackPtr + 1), $statementEnd, false, null, true);
        while ($arrow !== false) {
            $arrayValue = $phpcsFile->findNext(T_WHITESPACE, ($arrow + 1), $statementEnd, true);
            // We are only interested in string literals that are not numbers
            // and more than 3 characters long.
            if ($tokens[$arrayValue]['code'] === T_CONSTANT_ENCAPSED_STRING
                && is_numeric(substr($tokens[$arrayValue]['content'], 1, -1)) === false
                && strlen($tokens[$arrayValue]['content']) > 5
                // Make sure that we don't check stuff in nested arrays within
                // t() for example.
                && $tokens[$arrayValue]['nested_parenthesis'] === $nestestParenthesis
            ) {
                // We need to make sure that the string is the one and only part
                // of the array value.
                $afterValue = $phpcsFile->findNext(T_WHITESPACE, ($arrayValue + 1), $statementEnd, true);
                if ($tokens[$afterValue]['code'] === T_COMMA || $tokens[$afterValue]['code'] === T_CLOSE_PARENTHESIS) {
                    $warning = '#options values usually have to run through t() for translation';
                    $phpcsFile->addWarning($warning, $arrayValue, 'TforValue');
                }
            }

            $arrow = $phpcsFile->findNext(T_DOUBLE_ARROW, ($arrow + 1), $statementEnd, false, null, true);
        }//end while

    }//end process()


}//end class
