<?php
/**
 * \DrupalPractice\Sniffs\Objects\GlobalClassSniff.
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @link     http://pear.php.net/package/PHP_CodeSniffer
 */

namespace DrupalPractice\Sniffs\Objects;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use DrupalPractice\Project;

/**
 * Checks that Node::load() calls and friends are not used in forms, controllers or
 * services.
 *
 * @category PHP
 * @package  PHP_CodeSniffer
 * @link     http://pear.php.net/package/PHP_CodeSniffer
 */
class GlobalClassSniff implements Sniff
{

    /**
     * Class names that should not be called statically, mostly entity classes.
     *
     * @var string[]
     */
    protected $classes = [
        'File',
        'Node',
        'NodeType',
        'Role',
        'Term',
        'User',
    ];


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_STRING];

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

        // We are only interested in static class method calls, not in the global
        // scope.
        if (in_array($tokens[$stackPtr]['content'], $this->classes) === false
            || $tokens[($stackPtr + 1)]['code'] !== T_DOUBLE_COLON
            || isset($tokens[($stackPtr + 2)]) === false
            || $tokens[($stackPtr + 2)]['code'] !== T_STRING
            || in_array($tokens[($stackPtr + 2)]['content'], ['load', 'loadMultiple']) === false
            || isset($tokens[($stackPtr + 3)]) === false
            || $tokens[($stackPtr + 3)]['code'] !== T_OPEN_PARENTHESIS
            || empty($tokens[$stackPtr]['conditions']) === true
        ) {
            return;
        }

        // Check that this statement is not in a static function.
        foreach ($tokens[$stackPtr]['conditions'] as $conditionPtr => $conditionCode) {
            if ($conditionCode === T_FUNCTION && $phpcsFile->getMethodProperties($conditionPtr)['is_static'] === true) {
                return;
            }
        }

        // Check if the class extends another class and get the name of the class
        // that is extended.
        $classPtr    = key($tokens[$stackPtr]['conditions']);
        $extendsName = $phpcsFile->findExtendedClassName($classPtr);

        // Check if the class implements ContainerInjectionInterface.
        $implementedInterfaceNames = $phpcsFile->findImplementedInterfaceNames($classPtr);
        $canAccessContainer        = !empty($implementedInterfaceNames) && in_array('ContainerInjectionInterface', $implementedInterfaceNames);

        if (($extendsName === false
            || in_array($extendsName, GlobalDrupalSniff::$baseClasses) === false)
            && Project::isServiceClass($phpcsFile, $classPtr) === false
            && $canAccessContainer === false
        ) {
            return;
        }

        $warning = '%s::%s calls should be avoided in classes, use dependency injection instead';
        $data    = [
            $tokens[$stackPtr]['content'],
            $tokens[($stackPtr + 2)]['content'],
        ];
        $phpcsFile->addWarning($warning, $stackPtr, 'GlobalClass', $data);

    }//end process()


}//end class
