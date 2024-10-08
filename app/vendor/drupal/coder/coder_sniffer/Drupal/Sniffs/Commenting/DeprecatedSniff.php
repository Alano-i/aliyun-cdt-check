<?php
/**
 * \Drupal\Sniffs\Commenting\DeprecatedSniff.
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @link     http://pear.php.net/package/PHP_CodeSniffer
 */

namespace Drupal\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Ensures standard format of a @deprecated text.
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @link     http://pear.php.net/package/PHP_CodeSniffer
 */
class DeprecatedSniff implements Sniff
{


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_DOC_COMMENT_TAG];

    }//end register()


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Only process @deprecated tags.
        if (strcasecmp($tokens[$stackPtr]['content'], '@deprecated') !== 0) {
            return;
        }

        // Get the end point of the comment block which has the deprecated tag.
        $commentEnd = $phpcsFile->findNext(T_DOC_COMMENT_CLOSE_TAG, ($stackPtr + 1));

        // Get the full @deprecated text which may cover multiple lines.
        $depText = '';
        $depEnd  = ($stackPtr + 1);
        for ($i = ($stackPtr + 1); $i < $commentEnd; $i++) {
            if ($tokens[$i]['code'] === T_DOC_COMMENT_STRING) {
                if ($tokens[$i]['line'] <= ($tokens[$depEnd]['line'] + 1)) {
                    $depText .= ' '.$tokens[$i]['content'];
                    $depEnd   = $i;
                } else {
                    break;
                }
            }

            // Found another tag, so we have all the deprecation text.
            if ($tokens[$i]['code'] === T_DOC_COMMENT_TAG) {
                break;
            }
        }

        $depText = trim($depText);

        // The standard format for the deprecation text is:
        // @deprecated in %in-version% and will be removed from %removal-version%. %extra-info%.
        // Use (?U) 'ungreedy' before the version so that only the text up to
        // the first '. ' is matched, as there may be more than one sentence in
        // the extra-info part.
        $matches = [];
        preg_match('/in (.+) and is removed from (?U)(.+)\. (.+)$/', $depText, $matches);
        // There should be 4 items in $matches: 0 is full text, 1 = in-version,
        // 2 = removal-version, 3 = extra-info.
        if (count($matches) !== 4) {
            $error = "The text '@deprecated %s' does not match the standard format: @deprecated in %%deprecation-version%% and is removed from %%removal-version%%. %%extra-info%%.";
            $phpcsFile->addError($error, $stackPtr, 'IncorrectTextLayout', [$depText]);
        } else {
            // The text follows the basic layout. Now check that the versions
            // match drupal:n.n.n or project:n.x-n.n or project:n.x-n.n-version[n].
            // The text must be all lower case and numbers can be one or two digits.
            foreach (['deprecation-version' => $matches[1], 'removal-version' => $matches[2]] as $name => $version) {
                if (preg_match('/^drupal:\d{1,2}\.\d{1,2}\.\d{1,2}$/', $version) === 0
                    && preg_match('/^[a-z\d_]+:\d{1,2}\.x\-\d{1,2}\.\d{1,2}$/', $version) === 0
                    && preg_match('/^[a-z\d_]+:\d{1,2}\.x\-\d{1,2}\.\d{1,2}-[a-z]{1,5}\d{1,2}$/', $version) === 0
                ) {
                    $error = "The %s '%s' does not match the lower-case machine-name standard: drupal:n.n.n or project:n.x-n.n or project:n.x-n.n-version[n]";
                    $phpcsFile->addWarning($error, $stackPtr, 'DeprecatedVersionFormat', [$name, $version]);
                }
            }
        }

        // The next tag in this comment block after @deprecated must be @see.
        $seeTag = $phpcsFile->findNext(T_DOC_COMMENT_TAG, ($stackPtr + 1), $commentEnd, false, '@see');
        if ($seeTag === false) {
            $error = 'Each @deprecated tag must have a @see tag immediately following it.';
            $phpcsFile->addError($error, $stackPtr, 'DeprecatedMissingSeeTag');
            return;
        }

        // Check the format of the @see url.
        $string = $phpcsFile->findNext(T_DOC_COMMENT_STRING, ($seeTag + 1), $commentEnd);
        $crLink = $tokens[$string]['content'];
        // Allow for the alternative 'node' or 'project/aaa/issues' format.
        preg_match('[^http(s*)://www.drupal.org/(node|project/\w+/issues)/(\d+)(\.*)$]', $crLink, $matches);
        // If matches[4] is not blank it means that the url is correct but it
        // ends with a period. As this can be a common mistake give a specific
        // message to assist in fixing.
        if (isset($matches[4]) === true && empty($matches[4]) === false) {
            $error = "The @see url '%s' should not end with a period.";
            $phpcsFile->addWarning($error, $seeTag, 'DeprecatedPeriodAfterSeeUrl', [$crLink]);
        } else if (empty($matches) === true) {
            $error = "The @see url '%s' does not match the standard: http(s)://www.drupal.org/node/n or http(s)://www.drupal.org/project/aaa/issues/n";
            $phpcsFile->addWarning($error, $seeTag, 'DeprecatedWrongSeeUrlFormat', [$crLink]);
        }

    }//end process()


}//end class
