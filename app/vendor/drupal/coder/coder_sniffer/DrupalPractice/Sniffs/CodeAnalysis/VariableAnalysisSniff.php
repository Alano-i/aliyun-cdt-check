<?php
/**
 * This file is part of the VariableAnalysis addon for PHP_CodeSniffer.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Sam Graham <php-codesniffer-variableanalysis BLAHBLAH illusori.co.uk>
 * @copyright 2011-2012 Sam Graham <php-codesniffer-variableanalysis BLAHBLAH illusori.co.uk>
 * @license   http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

namespace DrupalPractice\Sniffs\CodeAnalysis;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Holds details of a scope.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Sam Graham <php-codesniffer-variableanalysis BLAHBLAH illusori.co.uk>
 * @copyright 2011-2012 Sam Graham <php-codesniffer-plugins BLAHBLAH illusori.co.uk>
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class ScopeInfo
{
    /**
     * Token stackpointer to the owner of the scope.
     *
     * @var integer
     */
    public $owner;

    /**
     * Variable information within this scope.
     *
     * @var VariableInfo[]
     */
    public $variables = [];


    /**
     * Constructor.
     *
     * @param int $currScope
     */
    public function __construct($currScope)
    {
        // TODO: extract opener/closer.
        $this->owner = $currScope;

    }//end __construct()


}//end class


/**
 * Holds details of a variable within a scope.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Sam Graham <php-codesniffer-variableanalysis BLAHBLAH illusori.co.uk>
 * @copyright 2011 Sam Graham <php-codesniffer-variableanalysis BLAHBLAH illusori.co.uk>
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class VariableInfo
{
    /**
     * The name of this variable.
     *
     * @var string
     */
    public $name;

    /**
     * What scope the variable has: local, param, static, global, bound
     *
     * @var string
     */
    public $scopeType;

    /**
     * The type hint associated with this variable.
     *
     * @var string
     */
    public $typeHint;

    /**
     * Indicates whether this variable is passed by reference.
     *
     * @var boolean
     */
    public $passByReference = false;

    /**
     * Token stack pointer where the variable was first declared.
     *
     * @var integer
     */
    public $firstDeclared;

    /**
     * Token stack pointer where the variable was first initialized.
     *
     * @var integer
     */
    public $firstInitialized;

    /**
     * Token stack pointer where the variable was first read from.
     *
     * @var integer
     */
    public $firstRead;

    /**
     * Indicates if the usage of the variable should be ignored.
     *
     * @var boolean
     */
    public $ignoreUnused = false;

    /**
     * Token stack pointer where the variable was last assigned a value.
     *
     * @var integer
     */
    public $lastAssignment;

    /**
     * Description mapping for the scope of the variable.
     *
     * @var string[]
     */
    public static $scopeTypeDescriptions = [
        'local'  => 'variable',
        'param'  => 'function parameter',
        'static' => 'static variable',
        'global' => 'global variable',
        'bound'  => 'bound variable',
    ];


    /**
     * Constructor.
     *
     * @param string $varName
     */
    public function __construct($varName)
    {
        $this->name = $varName;

    }//end __construct()


}//end class


/**
 * Checks the for undefined function variables.
 *
 * This sniff checks that all function variables
 * are defined in the function body.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Sam Graham <php-codesniffer-variableanalysis BLAHBLAH illusori.co.uk>
 * @copyright 2011 Sam Graham <php-codesniffer-variableanalysis BLAHBLAH illusori.co.uk>
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class VariableAnalysisSniff implements Sniff
{
    /**
     * The current phpcsFile being checked.
     *
     * @var File
     */
    protected $currentFile = null;

    /**
     * A list of scopes encountered so far and the variables within them.
     *
     * @var array
     */
    private $scopes = [];

    /**
     * A regexp for matching variable names in double-quoted strings.
     *
     * @var string
     */
    private $doubleQuotedVariableRegexp = '|(?<!\\\\)(?:\\\\{2})*\${?([a-zA-Z0-9_]+)}?|';

    /**
     *  Array of known pass-by-reference functions and the argument(s) which are passed
     *  by reference, the arguments are numbered starting from 1 and an elipsis '...'
     *  means all argument numbers after the previous should be considered pass-by-reference.
     *
     * @var array
     */
    private $passByRefFunctions = [
        '__soapCall'                  => [5],
        'addFunction'                 => [3],
        'addTask'                     => [3],
        'addTaskBackground'           => [3],
        'addTaskHigh'                 => [3],
        'addTaskHighBackground'       => [3],
        'addTaskLow'                  => [3],
        'addTaskLowBackground'        => [3],
        'addTaskStatus'               => [2],
        'apc_dec'                     => [3],
        'apc_fetch'                   => [2],
        'apc_inc'                     => [3],
        'areConfusable'               => [3],
        'array_multisort'             => [1],
        'array_pop'                   => [1],
        'array_push'                  => [1],
        'array_replace'               => [1],
        'array_replace_recursive'     => [
            1,
            2,
            3,
            '...',
        ],
        'array_shift'                 => [1],
        'array_splice'                => [1],
        'array_unshift'               => [1],
        'array_walk'                  => [1],
        'array_walk_recursive'        => [1],
        'arsort'                      => [1],
        'asort'                       => [1],
        'asort'                       => [1],
        'bindColumn'                  => [2],
        'bindParam'                   => [2],
        'bind_param'                  => [
            2,
            3,
            '...',
        ],
        'bind_result'                 => [
            1,
            2,
            '...',
        ],
        'call_user_method'            => [2],
        'call_user_method_array'      => [2],
        'curl_multi_exec'             => [2],
        'curl_multi_info_read'        => [2],
        'current'                     => [1],
        'dbplus_curr'                 => [2],
        'dbplus_first'                => [2],
        'dbplus_info'                 => [3],
        'dbplus_last'                 => [2],
        'dbplus_next'                 => [2],
        'dbplus_prev'                 => [2],
        'dbplus_tremove'              => [3],
        'dns_get_record'              => [
            3,
            4,
        ],
        'domxml_open_file'            => [3],
        'domxml_open_mem'             => [3],
        'each'                        => [1],
        'enchant_dict_quick_check'    => [3],
        'end'                         => [1],
        'ereg'                        => [3],
        'eregi'                       => [3],
        'exec'                        => [
            2,
            3,
        ],
        'exif_thumbnail'              => [
            1,
            2,
            3,
        ],
        'expect_expectl'              => [3],
        'extract'                     => [1],
        'filter'                      => [3],
        'flock'                       => [
            2,
            3,
        ],
        'fscanf'                      => [
            2,
            3,
            '...',
        ],
        'fsockopen'                   => [
            3,
            4,
        ],
        'ftp_alloc'                   => [3],
        'get'                         => [
            2,
            3,
        ],
        'getByKey'                    => [4],
        'getMulti'                    => [2],
        'getMultiByKey'               => [3],
        'getimagesize'                => [2],
        'getmxrr'                     => [
            2,
            3,
        ],
        'gnupg_decryptverify'         => [3],
        'gnupg_verify'                => [4],
        'grapheme_extract'            => [5],
        'headers_sent'                => [
            1,
            2,
        ],
        'http_build_url'              => [4],
        'http_get'                    => [3],
        'http_head'                   => [3],
        'http_negotiate_charset'      => [2],
        'http_negotiate_content_type' => [2],
        'http_negotiate_language'     => [2],
        'http_post_data'              => [4],
        'http_post_fields'            => [5],
        'http_put_data'               => [4],
        'http_put_file'               => [4],
        'http_put_stream'             => [4],
        'http_request'                => [5],
        'isSuspicious'                => [2],
        'is_callable'                 => [3],
        'key'                         => [1],
        'krsort'                      => [1],
        'ksort'                       => [1],
        'ldap_get_option'             => [3],
        'ldap_parse_reference'        => [3],
        'ldap_parse_result'           => [
            3,
            4,
            5,
            6,
        ],
        'localtime'                   => [2],
        'm_completeauthorizations'    => [2],
        'maxdb_stmt_bind_param'       => [
            3,
            4,
            '...',
        ],
        'maxdb_stmt_bind_result'      => [
            2,
            3,
            '...',
        ],
        'mb_convert_variables'        => [
            3,
            4,
            '...',
        ],
        'mb_parse_str'                => [2],
        'mqseries_back'               => [
            2,
            3,
        ],
        'mqseries_begin'              => [
            3,
            4,
        ],
        'mqseries_close'              => [
            4,
            5,
        ],
        'mqseries_cmit'               => [
            2,
            3,
        ],
        'mqseries_conn'               => [
            2,
            3,
            4,
        ],
        'mqseries_connx'              => [
            2,
            3,
            4,
            5,
        ],
        'mqseries_disc'               => [
            2,
            3,
        ],
        'mqseries_get'                => [
            3,
            4,
            5,
            6,
            7,
            8,
            9,
        ],
        'mqseries_inq'                => [
            6,
            8,
            9,
            10,
        ],
        'mqseries_open'               => [
            2,
            4,
            5,
            6,
        ],
        'mqseries_put'                => [
            3,
            4,
            6,
            7,
        ],
        'mqseries_put1'               => [
            2,
            3,
            4,
            6,
            7,
        ],
        'mqseries_set'                => [
            9,
            10,
        ],
        'msg_receive'                 => [
            3,
            5,
            8,
        ],
        'msg_send'                    => [6],
        'mssql_bind'                  => [3],
        'natcasesort'                 => [1],
        'natsort'                     => [1],
        'ncurses_color_content'       => [
            2,
            3,
            4,
        ],
        'ncurses_getmaxyx'            => [
            2,
            3,
        ],
        'ncurses_getmouse'            => [1],
        'ncurses_getyx'               => [
            2,
            3,
        ],
        'ncurses_instr'               => [1],
        'ncurses_mouse_trafo'         => [
            1,
            2,
        ],
        'ncurses_mousemask'           => [2],
        'ncurses_pair_content'        => [
            2,
            3,
        ],
        'ncurses_wmouse_trafo'        => [
            2,
            3,
        ],
        'newt_button_bar'             => [1],
        'newt_form_run'               => [2],
        'newt_get_screen_size'        => [
            1,
            2,
        ],
        'newt_grid_get_size'          => [
            2,
            3,
        ],
        'newt_reflow_text'            => [
            5,
            6,
        ],
        'newt_win_entries'            => [7],
        'newt_win_menu'               => [8],
        'next'                        => [1],
        'oci_bind_array_by_name'      => [3],
        'oci_bind_by_name'            => [3],
        'oci_define_by_name'          => [3],
        'oci_fetch_all'               => [2],
        'ocifetchinto'                => [2],
        'odbc_fetch_into'             => [2],
        'openssl_csr_export'          => [2],
        'openssl_csr_new'             => [2],
        'openssl_open'                => [2],
        'openssl_pkcs12_export'       => [2],
        'openssl_pkcs12_read'         => [2],
        'openssl_pkey_export'         => [2],
        'openssl_private_decrypt'     => [2],
        'openssl_private_encrypt'     => [2],
        'openssl_public_decrypt'      => [2],
        'openssl_public_encrypt'      => [2],
        'openssl_random_pseudo_bytes' => [2],
        'openssl_seal'                => [
            2,
            3,
        ],
        'openssl_sign'                => [2],
        'openssl_x509_export'         => [2],
        'ovrimos_fetch_into'          => [2],
        'parse'                       => [
            2,
            3,
        ],
        'parseCurrency'               => [
            2,
            3,
        ],
        'parse_str'                   => [2],
        'parsekit_compile_file'       => [2],
        'parsekit_compile_string'     => [2],
        'passthru'                    => [2],
        'pcntl_sigprocmask'           => [3],
        'pcntl_sigtimedwait'          => [2],
        'pcntl_sigwaitinfo'           => [2],
        'pcntl_wait'                  => [1],
        'pcntl_waitpid'               => [2],
        'pfsockopen'                  => [
            3,
            4,
        ],
        'php_check_syntax'            => [2],
        'poll'                        => [
            1,
            2,
            3,
        ],
        'preg_filter'                 => [5],
        'preg_match'                  => [3],
        'preg_match_all'              => [3],
        'preg_replace'                => [5],
        'preg_replace_callback'       => [5],
        'prev'                        => [1],
        'proc_open'                   => [3],
        'query'                       => [3],
        'queryExec'                   => [2],
        'reset'                       => [1],
        'rsort'                       => [1],
        'settype'                     => [1],
        'shuffle'                     => [1],
        'similar_text'                => [3],
        'socket_create_pair'          => [4],
        'socket_getpeername'          => [
            2,
            3,
        ],
        'socket_getsockname'          => [
            2,
            3,
        ],
        'socket_recv'                 => [2],
        'socket_recvfrom'             => [
            2,
            5,
            6,
        ],
        'socket_select'               => [
            1,
            2,
            3,
        ],
        'sort'                        => [1],
        'sortWithSortKeys'            => [1],
        'sqlite_exec'                 => [3],
        'sqlite_factory'              => [3],
        'sqlite_open'                 => [3],
        'sqlite_popen'                => [3],
        'sqlite_query'                => [4],
        'sqlite_query'                => [4],
        'sqlite_unbuffered_query'     => [4],
        'sscanf'                      => [
            3,
            '...',
        ],
        'str_ireplace'                => [4],
        'str_replace'                 => [4],
        'stream_open'                 => [4],
        'stream_select'               => [
            1,
            2,
            3,
        ],
        'stream_socket_accept'        => [3],
        'stream_socket_client'        => [
            2,
            3,
        ],
        'stream_socket_recvfrom'      => [4],
        'stream_socket_server'        => [
            2,
            3,
        ],
        'system'                      => [2],
        'uasort'                      => [1],
        'uksort'                      => [1],
        'unbufferedQuery'             => [3],
        'usort'                       => [1],
        'wincache_ucache_dec'         => [3],
        'wincache_ucache_get'         => [2],
        'wincache_ucache_inc'         => [3],
        'xdiff_string_merge3'         => [4],
        'xdiff_string_patch'          => [4],
        'xml_parse_into_struct'       => [
            3,
            4,
        ],
        'xml_set_object'              => [2],
        'xmlrpc_decode_request'       => [2],
        'xmlrpc_set_type'             => [1],
        'xslt_set_object'             => [2],
        'yaml_parse'                  => [3],
        'yaml_parse_file'             => [3],
        'yaml_parse_url'              => [3],
        'yaz_ccl_parse'               => [3],
        'yaz_hits'                    => [2],
        'yaz_scan_result'             => [2],
        'yaz_wait'                    => [1],
    ];

    /**
     *  Allows an install to extend the list of known pass-by-reference functions
     *  by defining generic.codeanalysis.variableanalysis.sitePassByRefFunctions.
     *
     * @var string
     */
    public $sitePassByRefFunctions = '';

    /**
     *  Allows exceptions in a catch block to be unused without provoking unused-var warning.
     *  Set generic.codeanalysis.variableanalysis.allowUnusedCaughtExceptions to a true value.
     *
     * @var boolean
     */
    public $allowUnusedCaughtExceptions = true;

    /**
     *  Allow function parameters to be unused without provoking unused-var warning.
     *  Set generic.codeanalysis.variableanalysis.allowUnusedFunctionParameters to a true value.
     *
     * @var boolean
     */
    public $allowUnusedFunctionParameters = true;

    /**
     *  A list of names of placeholder variables that you want to ignore from
     *  unused variable warnings, ie things like $junk.
     *
     * @var string
     */
    public $validUnusedVariableNames = '';


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        // Magic to modfy $passByRefFunctions with any site-specific settings.
        if (empty($this->sitePassByRefFunctions) === false) {
            foreach (preg_split('/\s+/', trim($this->sitePassByRefFunctions)) as $line) {
                list ($function, $args) = explode(':', $line);
                $this->passByRefFunctions[$function] = explode(',', $args);
            }
        }

        if (empty($this->validUnusedVariableNames) === false) {
            $this->validUnusedVariableNames = preg_split('/\s+/', trim($this->validUnusedVariableNames));
        }

        return [
            T_VARIABLE,
            T_DOUBLE_QUOTED_STRING,
            T_HEREDOC,
            T_CLOSE_CURLY_BRACKET,
            T_STRING,
        ];

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
        $token  = $tokens[$stackPtr];

        // Debug code.
        // if ($token['content'] == '$param') {
        // echo "Found token on line {$token['line']}.\n" . print_r($token, true);
        // }
        // End: Debug code.
        if ($this->currentFile !== $phpcsFile) {
            $this->currentFile = $phpcsFile;
        }

        if ($token['code'] === T_VARIABLE) {
            return $this->processVariable($phpcsFile, $stackPtr);
        }

        if (($token['code'] === T_DOUBLE_QUOTED_STRING)
            || ($token['code'] === T_HEREDOC)
        ) {
            return $this->processVariableInString($phpcsFile, $stackPtr);
        }

        if (($token['code'] === T_STRING) && ($token['content'] === 'compact')) {
            return $this->processCompact($phpcsFile, $stackPtr);
        }

        if (($token['code'] === T_CLOSE_CURLY_BRACKET)
            && isset($token['scope_condition']) === true
        ) {
            return $this->processScopeClose($phpcsFile, $token['scope_condition']);
        }

    }//end process()


    /**
     * Remove special characters from the variable name.
     *
     * @param string $varName
     *
     * @return string
     */
    public function normalizeVarName($varName)
    {
        $varName = preg_replace('/[{}$]/', '', $varName);
        return $varName;

    }//end normalizeVarName()


    /**
     * Generate a scope key based on the current file.
     *
     * @param string $currScope
     *
     * @return string
     */
    public function scopeKey($currScope)
    {
        if ($currScope === false) {
            $currScope = 'file';
        }

        if (isset($this->currentFile) === true) {
            return ($this->currentFile->getFilename()).':'.$currScope;
        } else {
            return ('unknown file').':'.$currScope;
        }

    }//end scopeKey()


    /**
     * Warning: this is an autovivifying get.
     *
     * @param string|false $currScope
     * @param bool         $autoCreate
     *
     * @return ScopeInfo
     */
    public function getScopeInfo($currScope, $autoCreate=true)
    {
        $scopeKey = $this->scopeKey($currScope);
        if (isset($this->scopes[$scopeKey]) === false) {
            if ($autoCreate === false) {
                return null;
            }

            $this->scopes[$scopeKey] = new ScopeInfo($currScope);
        }

        return $this->scopes[$scopeKey];

    }//end getScopeInfo()


    /**
     * Get variable information for a given variable name.
     *
     * @param string $varName
     *   Name of the variable.
     * @param int    $currScope
     *   Token stack pointer of the current scope.
     * @param bool   $autoCreate
     *   TRUE if the variable should be auto created.
     *
     * @return VariableInfo|null
     *   Information about the variable.
     */
    public function getVariableInfo($varName, $currScope, $autoCreate=true)
    {
        $scopeInfo = $this->getScopeInfo($currScope, $autoCreate);
        if (isset($scopeInfo->variables[$varName]) === false) {
            if ($autoCreate === false) {
                return null;
            }

            $scopeInfo->variables[$varName] = new VariableInfo($varName);
            if (is_array($this->validUnusedVariableNames) === true
                && in_array($varName, $this->validUnusedVariableNames) === true
            ) {
                $scopeInfo->variables[$varName]->ignoreUnused = true;
            }
        }

        return $scopeInfo->variables[$varName];

    }//end getVariableInfo()


    /**
     * Mark the given variable as being assigned.
     *
     * @param string $varName
     * @param int    $stackPtr
     * @param string $currScope
     *
     * @return void
     */
    public function markVariableAssignment($varName, $stackPtr, $currScope)
    {
        $varInfo = $this->getVariableInfo($varName, $currScope);
        if (isset($varInfo->scopeType) === false) {
            $varInfo->scopeType = 'local';
        }

        if (isset($varInfo->firstInitialized) === true && ($varInfo->firstInitialized <= $stackPtr)) {
            $varInfo->lastAssignment = $stackPtr;
            return;
        }

        $varInfo->firstInitialized = $stackPtr;

    }//end markVariableAssignment()


    /**
     * Mark the given variable as being declared.
     *
     * @param string $varName
     * @param string $scopeType
     * @param string $typeHint
     * @param int    $stackPtr
     * @param string $currScope
     * @param bool   $permitMatchingRedeclaration
     *
     * @return void
     */
    public function markVariableDeclaration($varName, $scopeType, $typeHint, $stackPtr, $currScope, $permitMatchingRedeclaration=false)
    {
        $varInfo = $this->getVariableInfo($varName, $currScope);
        if (isset($varInfo->scopeType) === true) {
            if (($permitMatchingRedeclaration === false)
                || ($varInfo->scopeType !== $scopeType)
            ) {
                // Issue redeclaration/reuse warning
                // Note: we check off scopeType not firstDeclared, this is so that
                // we catch declarations that come after implicit declarations like
                // use of a variable as a local.
                $this->currentFile->addWarning(
                    "Redeclaration of %s %s as %s.",
                    $stackPtr,
                    'VariableRedeclaration',
                    [
                        VariableInfo::$scopeTypeDescriptions[$varInfo->scopeType],
                        "\${$varName}",
                        VariableInfo::$scopeTypeDescriptions[$scopeType],
                    ]
                );
            }
        }

        $varInfo->scopeType = $scopeType;
        if (isset($typeHint) === true) {
            $varInfo->typeHint = $typeHint;
        }

        if (isset($varInfo->firstDeclared) === true && ($varInfo->firstDeclared <= $stackPtr)) {
            return;
        }

        // When a global variable is declared it also means we can consider it as
        // being initialized.
        if ($scopeType === 'global') {
            $varInfo->firstInitialized = $stackPtr;
        }

        $varInfo->firstDeclared = $stackPtr;

    }//end markVariableDeclaration()


    /**
     * Mark the given variable as being read.
     *
     * @param string $varName
     * @param int    $stackPtr
     * @param string $currScope
     *
     * @return void
     */
    public function markVariableRead($varName, $stackPtr, $currScope)
    {
        $varInfo = $this->getVariableInfo($varName, $currScope);
        if (isset($varInfo->firstRead) === true && ($varInfo->firstRead <= $stackPtr)) {
            return;
        }

        $varInfo->firstRead = $stackPtr;

    }//end markVariableRead()


    /**
     * Checks if a variable has been initialized.
     *
     * @param string $varName
     * @param int    $stackPtr
     * @param string $currScope
     *
     * @return bool
     */
    public function isVariableInitialized($varName, $stackPtr, $currScope)
    {
        $varInfo = $this->getVariableInfo($varName, $currScope);
        if (isset($varInfo->firstInitialized) === true && $varInfo->firstInitialized <= $stackPtr) {
            return true;
        }

        return false;

    }//end isVariableInitialized()


    /**
     * Checks if the given variable is undefined.
     *
     * @param string $varName
     * @param int    $stackPtr
     * @param string $currScope
     *
     * @return bool
     */
    public function isVariableUndefined($varName, $stackPtr, $currScope)
    {
        $varInfo = $this->getVariableInfo($varName, $currScope, false);
        if (isset($varInfo->firstDeclared) === true && $varInfo->firstDeclared <= $stackPtr) {
            // TODO: do we want to check scopeType here?
            return false;
        }

        if (isset($varInfo->firstInitialized) === true && $varInfo->firstInitialized <= $stackPtr) {
            return false;
        }

        return true;

    }//end isVariableUndefined()


    /**
     * Marks a variable as read and throws a PHPCS warning if it is undefined.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param string                      $varName
     * @param int                         $stackPtr
     * @param string                      $currScope
     *
     * @return bool
     */
    public function markVariableReadAndWarnIfUndefined(File $phpcsFile, $varName, $stackPtr, $currScope)
    {
        $this->markVariableRead($varName, $stackPtr, $currScope);

        if ($this->isVariableUndefined($varName, $stackPtr, $currScope) === true) {
            // We haven't been defined by this point.
            $phpcsFile->addWarning(
                "Variable %s is undefined.",
                $stackPtr,
                'UndefinedVariable',
                ["\${$varName}"]
            );
        }

        return true;

    }//end markVariableReadAndWarnIfUndefined()


    /**
     * Returns the function declaration pointer.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     *
     * @return int|false
     */
    public function findFunctionPrototype(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if (($openPtr = $this->findContainingBrackets($phpcsFile, $stackPtr)) === false) {
            return false;
        }

        // Function names are T_STRING, and return-by-reference is T_BITWISE_AND,
        // so we look backwards from the opening bracket for the first thing that
        // isn't a function name, reference sigil or whitespace and check if
        // it's a function keyword.
        $functionPtr = $phpcsFile->findPrevious(
            [
                T_STRING,
                T_WHITESPACE,
                T_BITWISE_AND,
            ],
            ($openPtr - 1),
            null,
            true,
            null,
            true
        );
        if (($functionPtr !== false)
            && ($tokens[$functionPtr]['code'] === T_FUNCTION)
        ) {
            return $functionPtr;
        }

        return false;

    }//end findFunctionPrototype()


    /**
     * Find the scope the given pointer is in.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     *
     * @return int|false
     */
    public function findVariableScope(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];

        $inClass = false;
        if (empty($token['conditions']) === false) {
            foreach (array_reverse($token['conditions'], true) as $scopePtr => $scopeCode) {
                if (($scopeCode === T_FUNCTION) || ($scopeCode === T_CLOSURE)) {
                    return $scopePtr;
                }

                if (($scopeCode === T_CLASS) || ($scopeCode === T_INTERFACE) || ($scopeCode === T_TRAIT)) {
                    $inClass = true;
                }
            }
        }

        if (($scopePtr = $this->findFunctionPrototype($phpcsFile, $stackPtr)) !== false) {
            return $scopePtr;
        }

        if ($inClass === true) {
            // Member var of a class, we don't care.
            return false;
        }

        // File scope, hmm, lets use first token of file?
        return 0;

    }//end findVariableScope()


    /**
     * Checks if the next token is an assignment.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     *
     * @return bool
     */
    public function isNextThingAnAssign(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Is the next non-whitespace an assignment?
        $nextPtr = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true, null, true);
        if ($nextPtr !== false) {
            if ($tokens[$nextPtr]['code'] === T_EQUAL) {
                return $nextPtr;
            }

            // Special handling for initializing arrays on the fly, which is
            // also an assignment.
            if ($tokens[$nextPtr]['code'] === T_OPEN_SQUARE_BRACKET) {
                return $this->isNextThingAnAssign($phpcsFile, $tokens[$nextPtr]['bracket_closer']);
            }
        }

        return false;

    }//end isNextThingAnAssign()


    /**
     * Find the end of the assignment.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     *
     * @return int
     */
    public function findWhereAssignExecuted(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Write should be recorded at the next statement to ensure we treat
        // the assign as happening after the RHS execution.
        // eg: $var = $var + 1; -> RHS could still be undef.
        // However, if we're within a bracketed expression,
        // eg: echo (($var = 12) && ($var == 12));
        // we take place at the closing bracket, if that's first.
        $semicolonPtr = $phpcsFile->findNext(T_SEMICOLON, ($stackPtr + 1), null, false, null, true);
        $closePtr     = false;
        if (($openPtr = $this->findContainingBrackets($phpcsFile, $stackPtr)) !== false) {
            if (isset($tokens[$openPtr]['parenthesis_closer']) === true) {
                $closePtr = $tokens[$openPtr]['parenthesis_closer'];
            }
        }

        if ($semicolonPtr === false) {
            if ($closePtr === false) {
                // TODO: panic.
                return $stackPtr;
            }

            return $closePtr;
        }

        if ($closePtr !== false && $closePtr < $semicolonPtr) {
            return $closePtr;
        }

        return $semicolonPtr;

    }//end findWhereAssignExecuted()


    /**
     * Find the parenthesis if the pointer is in some.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     *
     * @return int|false
     */
    public function findContainingBrackets(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['nested_parenthesis']) === true) {
            $openPtrs = array_keys($tokens[$stackPtr]['nested_parenthesis']);
            return end($openPtrs);
        }

        return false;

    }//end findContainingBrackets()


    /**
     * Checks if the given pointer is in a function call.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     *
     * @return int|false
     */
    public function findFunctionCall(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if (($openPtr = $this->findContainingBrackets($phpcsFile, $stackPtr)) !== false) {
            // First non-whitespace thing and see if it's a T_STRING function name.
            $functionPtr = $phpcsFile->findPrevious(
                T_WHITESPACE,
                ($openPtr - 1),
                null,
                true,
                null,
                true
            );
            if ($tokens[$functionPtr]['code'] === T_STRING) {
                return $functionPtr;
            }
        }

        return false;

    }//end findFunctionCall()


    /**
     * Get the arguments of a function call.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     *
     * @return array|false
     */
    public function findFunctionCallArguments(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Slight hack: also allow this to find args for array constructor.
        // TODO: probably should refactor into three functions: arg-finding and bracket-finding.
        if (($tokens[$stackPtr]['code'] !== T_STRING) && ($tokens[$stackPtr]['code'] !== T_ARRAY)) {
            // Assume $stackPtr is something within the brackets, find our function call.
            if (($stackPtr = $this->findFunctionCall($phpcsFile, $stackPtr)) === false) {
                return false;
            }
        }

        // $stackPtr is the function name, find our brackets after it.
        $openPtr = $phpcsFile->findNext(
            T_WHITESPACE,
            ($stackPtr + 1),
            null,
            true,
            null,
            true
        );
        if (($openPtr === false) || ($tokens[$openPtr]['code'] !== T_OPEN_PARENTHESIS)) {
            return false;
        }

        if (isset($tokens[$openPtr]['parenthesis_closer']) === false) {
            return false;
        }

        $closePtr = $tokens[$openPtr]['parenthesis_closer'];

        $argPtrs      = [];
        $lastPtr      = $openPtr;
        $lastArgComma = $openPtr;
        while (($nextPtr = $phpcsFile->findNext(T_COMMA, ($lastPtr + 1), $closePtr)) !== false) {
            if ($this->findContainingBrackets($phpcsFile, $nextPtr) === $openPtr) {
                // Comma is at our level of brackets, it's an argument delimiter.
                array_push($argPtrs, range(($lastArgComma + 1), ($nextPtr - 1)));
                $lastArgComma = $nextPtr;
            }

            $lastPtr = $nextPtr;
        }

        array_push($argPtrs, range(($lastArgComma + 1), ($closePtr - 1)));

        return $argPtrs;

    }//end findFunctionCallArguments()


    /**
     * Checks the function prototype.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     * @param string                      $varName
     * @param string                      $currScope
     *
     * @return bool
     */
    protected function checkForFunctionPrototype(
        File $phpcsFile,
        $stackPtr,
        $varName,
        $currScope
    ) {
        $tokens = $phpcsFile->getTokens();

        // Are we a function or closure parameter?
        // It would be nice to get the list of function parameters from watching for
        // T_FUNCTION, but AbstractVariableSniff and AbstractScopeSniff define everything
        // we need to do that as private or final, so we have to do it this hackish way.
        if (($openPtr = $this->findContainingBrackets($phpcsFile, $stackPtr)) === false) {
            return false;
        }

        // Function names are T_STRING, and return-by-reference is T_BITWISE_AND,
        // so we look backwards from the opening bracket for the first thing that
        // isn't a function name, reference sigil or whitespace and check if
        // it's a function keyword.
        $functionPtr = $phpcsFile->findPrevious(
            [
                T_STRING,
                T_WHITESPACE,
                T_BITWISE_AND,
            ],
            ($openPtr - 1),
            null,
            true,
            null,
            true
        );
        if (($functionPtr !== false)
            && (($tokens[$functionPtr]['code'] === T_FUNCTION)
            || ($tokens[$functionPtr]['code'] === T_CLOSURE))
        ) {
            // TODO: typeHint.
            $this->markVariableDeclaration($varName, 'param', null, $stackPtr, $functionPtr);
            // Are we pass-by-reference?
            $referencePtr = $phpcsFile->findPrevious(
                T_WHITESPACE,
                ($stackPtr - 1),
                null,
                true,
                null,
                true
            );
            if (($referencePtr !== false) && ($tokens[$referencePtr]['code'] === T_BITWISE_AND)) {
                $varInfo = $this->getVariableInfo($varName, $functionPtr);
                $varInfo->passByReference = true;
            }

            // Are we optional with a default?
            if ($this->isNextThingAnAssign($phpcsFile, $stackPtr) !== false) {
                $this->markVariableAssignment($varName, $stackPtr, $functionPtr);
            }

            return true;
        }//end if

        // Is it a use keyword?  Use is both a read and a define, fun!
        if (($functionPtr !== false) && ($tokens[$functionPtr]['code'] === T_USE)) {
            $this->markVariableRead($varName, $stackPtr, $currScope);
            if ($this->isVariableUndefined($varName, $stackPtr, $currScope) === true) {
                // We haven't been defined by this point.
                $phpcsFile->addWarning(
                    "Variable %s is undefined.",
                    $stackPtr,
                    'UndefinedVariable',
                    ["\${$varName}"]
                );
                return true;
            }

            // $functionPtr is at the use, we need the function keyword for start of scope.
            $functionPtr = $phpcsFile->findPrevious(
                T_CLOSURE,
                ($functionPtr - 1),
                ($currScope + 1),
                false,
                null,
                true
            );
            if ($functionPtr !== false) {
                // TODO: typeHints in use?
                $this->markVariableDeclaration($varName, 'bound', null, $stackPtr, $functionPtr);
                $this->markVariableAssignment($varName, $stackPtr, $functionPtr);

                // Are we pass-by-reference?
                $referencePtr = $phpcsFile->findPrevious(
                    T_WHITESPACE,
                    ($stackPtr - 1),
                    null,
                    true,
                    null,
                    true
                );
                if (($referencePtr !== false) && ($tokens[$referencePtr]['code'] === T_BITWISE_AND)) {
                    $varInfo = $this->getVariableInfo($varName, $functionPtr);
                    $varInfo->passByReference = true;
                }

                return true;
            }//end if
        }//end if

        return false;

    }//end checkForFunctionPrototype()


    /**
     * Checks if we are in a catch() block.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     * @param string                      $varName
     * @param string                      $currScope
     *
     * @return bool
     */
    protected function checkForCatchBlock(
        File $phpcsFile,
        $stackPtr,
        $varName,
        $currScope
    ) {
        $tokens = $phpcsFile->getTokens();

        // Are we a catch block parameter?
        if (($openPtr = $this->findContainingBrackets($phpcsFile, $stackPtr)) === false) {
            return false;
        }

        // Function names are T_STRING, and return-by-reference is T_BITWISE_AND,
        // so we look backwards from the opening bracket for the first thing that
        // isn't a function name, reference sigil or whitespace and check if
        // it's a function keyword.
        $catchPtr = $phpcsFile->findPrevious(
            T_WHITESPACE,
            ($openPtr - 1),
            null,
            true,
            null,
            true
        );
        if (($catchPtr !== false)
            && ($tokens[$catchPtr]['code'] === T_CATCH)
        ) {
            // Scope of the exception var is actually the function, not just the catch block.
            // TODO: typeHint.
            $this->markVariableDeclaration($varName, 'local', null, $stackPtr, $currScope, true);
            $this->markVariableAssignment($varName, $stackPtr, $currScope);
            if ($this->allowUnusedCaughtExceptions !== false) {
                $varInfo = $this->getVariableInfo($varName, $currScope);
                $varInfo->ignoreUnused = true;
            }

            return true;
        }

        return false;

    }//end checkForCatchBlock()


    /**
     * Checks if $this is used within a class.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     * @param string                      $varName
     * @param string                      $currScope
     *
     * @return bool
     */
    protected function checkForThisWithinClass(
        File $phpcsFile,
        $stackPtr,
        $varName,
        $currScope
    ) {
        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];

        // Are we $this within a class?
        if (($varName !== 'this') || empty($token['conditions']) === true) {
            return false;
        }

        foreach (array_reverse($token['conditions'], true) as $scopePtr => $scopeCode) {
            if ($scopeCode === T_CLASS || $scopeCode === T_TRAIT) {
                return true;
            }
        }

        return false;

    }//end checkForThisWithinClass()


    /**
     * Checks if the variable is a PHP super global.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     * @param string                      $varName
     * @param string                      $currScope
     *
     * @return bool
     */
    protected function checkForSuperGlobal(
        File $phpcsFile,
        $stackPtr,
        $varName,
        $currScope
    ) {
        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];

        // Are we a superglobal variable?
        if (in_array(
            $varName,
            [
                'GLOBALS',
                '_SERVER',
                '_GET',
                '_POST',
                '_FILES',
                '_COOKIE',
                '_SESSION',
                '_REQUEST',
                '_ENV',
                'argv',
                'argc',
            ]
        ) === true
        ) {
            return true;
        }

        return false;

    }//end checkForSuperGlobal()


    /**
     * Checks if the variable is a static class member.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     * @param string                      $varName
     * @param string                      $currScope
     *
     * @return bool
     */
    protected function checkForStaticMember(
        File $phpcsFile,
        $stackPtr,
        $varName,
        $currScope
    ) {
        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];

        // Are we a static member?
        $doubleColonPtr = ($stackPtr - 1);
        if ($tokens[$doubleColonPtr]['code'] !== T_DOUBLE_COLON) {
            return false;
        }

        $classNamePtr = ($stackPtr - 2);
        if (($tokens[$classNamePtr]['code'] !== T_STRING)
            && ($tokens[$classNamePtr]['code'] !== T_SELF)
            && ($tokens[$classNamePtr]['code'] !== T_STATIC)
        ) {
            return false;
        }

        // Are we referring to self:: outside a class?
        // TODO: not sure this is our business or should be some other sniff.
        if (($tokens[$classNamePtr]['code'] === T_SELF)
            || ($tokens[$classNamePtr]['code'] === T_STATIC)
        ) {
            if ($tokens[$classNamePtr]['code'] === T_SELF) {
                $errClass = 'SelfOutsideClass';
                $errDesc  = 'self::';
            } else {
                $errClass = 'StaticOutsideClass';
                $errDesc  = 'static::';
            }

            if (empty($token['conditions']) === false) {
                foreach (array_reverse($token['conditions'], true) as $scopePtr => $scopeCode) {
                    // Self within a closure is invalid.
                    // Note: have to fetch code from $tokens, T_CLOSURE isn't set for conditions codes.
                    if ($tokens[$scopePtr]['code'] === T_CLOSURE) {
                        $phpcsFile->addError(
                            "Use of {$errDesc}%s inside closure.",
                            $stackPtr,
                            $errClass,
                            ["\${$varName}"]
                        );
                        return true;
                    }

                    if ($scopeCode === T_CLASS || $scopeCode === T_TRAIT) {
                        return true;
                    }
                }
            }

            $phpcsFile->addError(
                "Use of {$errDesc}%s outside class definition.",
                $stackPtr,
                $errClass,
                ["\${$varName}"]
            );
            return true;
        }//end if

        return true;

    }//end checkForStaticMember()


    /**
     * Checks if the variable is being assigned to.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     * @param string                      $varName
     * @param string                      $currScope
     *
     * @return bool
     */
    protected function checkForAssignment(
        File $phpcsFile,
        $stackPtr,
        $varName,
        $currScope
    ) {
        $tokens = $phpcsFile->getTokens();

        // Is the next non-whitespace an assignment?
        if (($assignPtr = $this->isNextThingAnAssign($phpcsFile, $stackPtr)) === false) {
            return false;
        }

        // Plain ol' assignment. Simpl(ish).
        if (($writtenPtr = $this->findWhereAssignExecuted($phpcsFile, $assignPtr)) === false) {
            $writtenPtr = $stackPtr;
            // I dunno.
        }

        // Check for the ampersand '&' after the assignment, which means this
        // variable is taken by reference.
        $refPtr = $phpcsFile->findNext(T_WHITESPACE, ($assignPtr + 1), null, true);
        if ($tokens[$refPtr]['code'] === T_BITWISE_AND) {
            $varInfo = $this->getVariableInfo($varName, $currScope);
            $varInfo->passByReference = true;
        }

        $this->markVariableAssignment($varName, $writtenPtr, $currScope);
        return true;

    }//end checkForAssignment()


    /**
     * Check if this is a list language construct assignment.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     * @param string                      $varName
     * @param string                      $currScope
     *
     * @return bool
     */
    protected function checkForListAssignment(
        File $phpcsFile,
        $stackPtr,
        $varName,
        $currScope
    ) {
        $tokens = $phpcsFile->getTokens();

        // OK, are we within a list (...) construct?
        if (($openPtr = $this->findContainingBrackets($phpcsFile, $stackPtr)) === false) {
            return false;
        }

        $prevPtr = $phpcsFile->findPrevious(T_WHITESPACE, ($openPtr - 1), null, true, null, true);
        if (($prevPtr === false) || ($tokens[$prevPtr]['code'] !== T_LIST)) {
            return false;
        }

        // OK, we're a list (...) construct... are we being assigned to?
        $closePtr = $tokens[$openPtr]['parenthesis_closer'];
        if (($assignPtr = $this->isNextThingAnAssign($phpcsFile, $closePtr)) === false) {
            return false;
        }

        // Yes, we're being assigned.
        $writtenPtr = $this->findWhereAssignExecuted($phpcsFile, $assignPtr);
        $this->markVariableAssignment($varName, $writtenPtr, $currScope);
        return true;

    }//end checkForListAssignment()


    /**
     * Check if this variable is declared globally.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     * @param string                      $varName
     * @param string                      $currScope
     *
     * @return bool
     */
    protected function checkForGlobalDeclaration(
        File $phpcsFile,
        $stackPtr,
        $varName,
        $currScope
    ) {
        $tokens = $phpcsFile->getTokens();

        // Are we a global declaration?
        // Search backwards for first token that isn't whitespace, comma or variable.
        $globalPtr = $phpcsFile->findPrevious(
            [
                T_WHITESPACE,
                T_VARIABLE,
                T_COMMA,
            ],
            ($stackPtr - 1),
            null,
            true,
            null,
            true
        );
        if (($globalPtr === false) || ($tokens[$globalPtr]['code'] !== T_GLOBAL)) {
            return false;
        }

        // It's a global declaration.
        $this->markVariableDeclaration($varName, 'global', null, $stackPtr, $currScope);
        // Also mark this variable as being a reference, so that we don't get
        // unused variable warnings if it is never read.
        $varInfo = $this->getVariableInfo($varName, $currScope);
        $varInfo->passByReference = true;
        return true;

    }//end checkForGlobalDeclaration()


    /**
     * Check is this is a static variable declaration.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     * @param string                      $varName
     * @param string                      $currScope
     *
     * @return bool
     */
    protected function checkForStaticDeclaration(
        File $phpcsFile,
        $stackPtr,
        $varName,
        $currScope
    ) {
        $tokens = $phpcsFile->getTokens();

        // Are we a static declaration?
        // Static declarations are a bit more complicated than globals, since they
        // can contain assignments. The assignment is compile-time however so can
        // only be constant values, which makes life manageable.
        //
        // Just to complicate matters further, late static binding constants
        // take the form static::CONSTANT and are invalid within static variable
        // assignments, but we don't want to accidentally match their use of the
        // static keyword.
        //
        // Valid values are:
        // number         T_MINUS T_LNUMBER T_DNUMBER
        // string         T_CONSTANT_ENCAPSED_STRING
        // heredoc        T_START_HEREDOC T_HEREDOC T_END_HEREDOC
        // nowdoc         T_START_NOWDOC T_NOWDOC T_END_NOWDOC
        // define         T_STRING
        // class constant T_STRING T_DOUBLE_COLON T_STRING
        // Search backwards for first token that isn't whitespace, comma, variable,
        // equals, or on the list of assignable constant values above.
        $staticPtr = $phpcsFile->findPrevious(
            [
                T_WHITESPACE,
                T_VARIABLE,
                T_COMMA,
                T_EQUAL,
                T_MINUS,
                T_LNUMBER,
                T_DNUMBER,
                T_CONSTANT_ENCAPSED_STRING,
                T_STRING,
                T_DOUBLE_COLON,
                T_START_HEREDOC,
                T_HEREDOC,
                T_END_HEREDOC,
                T_START_NOWDOC,
                T_NOWDOC,
                T_END_NOWDOC,
            ],
            ($stackPtr - 1),
            null,
            true,
            null,
            true
        );
        if (($staticPtr === false) || ($tokens[$staticPtr]['code'] !== T_STATIC)) {
            // Debug code.
            // if ($varName == 'static4') {
            // echo "Failing token:\n" . print_r($tokens[$staticPtr], true);
            // }
            // End: Debug code.
            return false;
        }

            // Is it a late static binding static::?
            // If so, this isn't the static keyword we're looking for, but since
            // static:: isn't allowed in a compile-time constant, we also know
            // we can't be part of a static declaration anyway, so there's no
            // need to look any further.
            $lateStaticBindingPtr = $phpcsFile->findNext(T_WHITESPACE, ($staticPtr + 1), null, true, null, true);
        if (($lateStaticBindingPtr !== false) && ($tokens[$lateStaticBindingPtr]['code'] === T_DOUBLE_COLON)) {
            return false;
        }

            // It's a static declaration.
            $this->markVariableDeclaration($varName, 'static', null, $stackPtr, $currScope);
        if ($this->isNextThingAnAssign($phpcsFile, $stackPtr) !== false) {
            $this->markVariableAssignment($varName, $stackPtr, $currScope);
        }

            return true;

    }//end checkForStaticDeclaration()


    /**
     * Check if this is a foreach loop variable.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     * @param string                      $varName
     * @param string                      $currScope
     *
     * @return bool
     */
    protected function checkForForeachLoopVar(
        File $phpcsFile,
        $stackPtr,
        $varName,
        $currScope
    ) {
        $tokens = $phpcsFile->getTokens();

        // Are we a foreach loopvar?
        if (($openPtr = $this->findContainingBrackets($phpcsFile, $stackPtr)) === false) {
            return false;
        }

        // Is there an 'as' token between us and the opening bracket?
        if (($asPtr = $phpcsFile->findPrevious(T_AS, ($stackPtr - 1), $openPtr)) === false
            || $this->findContainingBrackets($phpcsFile, $asPtr) !== $openPtr
        ) {
            return false;
        }

        $this->markVariableAssignment($varName, $stackPtr, $currScope);

        // Workaround: We want to allow foreach ($array as $key => $value) where
        // $value is never read, so we just mark it read immediately here.
        if ($phpcsFile->findPrevious(T_DOUBLE_ARROW, ($stackPtr - 1), $openPtr) !== false) {
            $this->markVariableRead($varName, $stackPtr, $currScope);
        }

        // Foreach variables that are read as references like
        // foreach ($array as &$value) should not throw unused variable errors.
        if (($refPtr = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true)) !== false
            && $tokens[$refPtr]['code'] === T_BITWISE_AND
        ) {
            $varInfo = $this->getVariableInfo($varName, $currScope);
            $varInfo->passByReference = true;
        }

        return true;

    }//end checkForForeachLoopVar()


    /**
     * Check if this is a "&" function call.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     * @param string                      $varName
     * @param string                      $currScope
     *
     * @return bool
     */
    protected function checkForPassByReferenceFunctionCall(
        File $phpcsFile,
        $stackPtr,
        $varName,
        $currScope
    ) {
        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];

        // Are we pass-by-reference to known pass-by-reference function?
        if (($functionPtr = $this->findFunctionCall($phpcsFile, $stackPtr)) === false) {
            return false;
        }

        // Is our function a known pass-by-reference function?
        $functionName = $tokens[$functionPtr]['content'];
        if (isset($this->passByRefFunctions[$functionName]) === false) {
            return false;
        }

        $refArgs = $this->passByRefFunctions[$functionName];

        if (($argPtrs = $this->findFunctionCallArguments($phpcsFile, $stackPtr)) === false) {
            return false;
        }

        // We're within a function call arguments list, find which arg we are.
        $argPos = false;
        foreach ($argPtrs as $idx => $ptrs) {
            if (in_array($stackPtr, $ptrs) === true) {
                $argPos = ($idx + 1);
                break;
            }
        }

        if ($argPos === false) {
            return false;
        }

        if (in_array($argPos, $refArgs) === false) {
            // Our arg wasn't mentioned explicitly, are we after an elipsis catch-all?
            if (($elipsis = array_search('...', $refArgs)) === false) {
                return false;
            }

            if ($argPos < $refArgs[($elipsis - 1)]) {
                return false;
            }
        }

        // Our argument position matches that of a pass-by-ref argument,
        // check that we're the only part of the argument expression.
        foreach ($argPtrs[($argPos - 1)] as $ptr) {
            if ($ptr === $stackPtr) {
                continue;
            }

            if ($tokens[$ptr]['code'] !== T_WHITESPACE) {
                return false;
            }
        }

        // Just us, we can mark it as a write.
        $this->markVariableAssignment($varName, $stackPtr, $currScope);
        // It's a read as well for purposes of used-variables.
        $this->markVariableRead($varName, $stackPtr, $currScope);
        return true;

    }//end checkForPassByReferenceFunctionCall()


    /**
     * Check if the variable is an object property.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     * @param string                      $varName
     * @param string                      $currScope
     *
     * @return bool
     */
    protected function checkForSymbolicObjectProperty(
        File $phpcsFile,
        $stackPtr,
        $varName,
        $currScope
    ) {
        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];

        // Are we a symbolic object property/function derefeference?
        // Search backwards for first token that isn't whitespace, is it a "->" operator?
        $objectOperatorPtr = $phpcsFile->findPrevious(
            T_WHITESPACE,
            ($stackPtr - 1),
            null,
            true,
            null,
            true
        );
        if (($objectOperatorPtr === false) || ($tokens[$objectOperatorPtr]['code'] !== T_OBJECT_OPERATOR)) {
            return false;
        }

        $this->markVariableReadAndWarnIfUndefined($phpcsFile, $varName, $stackPtr, $currScope);
        return true;

    }//end checkForSymbolicObjectProperty()


    /**
     * Called to process class member vars.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where this
     *                                               token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void
     */
    protected function processMemberVar(
        File $phpcsFile,
        $stackPtr
    ) {
        // TODO: don't care for now.
        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];

    }//end processMemberVar()


    /**
     * Called to process normal member vars.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where this
     *                                               token was found.
     * @param int                         $stackPtr  The position where the token was found.
     *
     * @return void
     */
    protected function processVariable(
        File $phpcsFile,
        $stackPtr
    ) {
        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];

        $varName = $this->normalizeVarName($token['content']);
        if (($currScope = $this->findVariableScope($phpcsFile, $stackPtr)) === false) {
            return;
        }

        // Debug code.
        // static $dump_token = false;
        // if ($varName == 'property') {
        // $dump_token = true;
        // }
        // if ($dump_token) {
        // echo "Found variable {$varName} on line {$token['line']} in scope {$currScope}.\n" . print_r($token, true);
        // echo "Prev:\n" . print_r($tokens[$stackPtr - 1], true);
        // }
        // Determine if variable is being assigned or read.
        // Read methods that preempt assignment:
        // Are we a $object->$property type symbolic reference?
        // Possible assignment methods:
        // Is a mandatory function/closure parameter
        // Is an optional function/closure parameter with non-null value
        // Is closure use declaration of a variable defined within containing scope
        // catch (...) block start
        // $this within a class (but not within a closure).
        // $GLOBALS, $_REQUEST, etc superglobals.
        // $var part of class::$var static member
        // Assignment via =
        // Assignment via list (...) =
        // Declares as a global
        // Declares as a static
        // Assignment via foreach (... as ...) { }
        // Pass-by-reference to known pass-by-reference function
        // Are we a $object->$property type symbolic reference?
        if ($this->checkForSymbolicObjectProperty($phpcsFile, $stackPtr, $varName, $currScope) === true) {
            return;
        }

        // Are we a function or closure parameter?
        if ($this->checkForFunctionPrototype($phpcsFile, $stackPtr, $varName, $currScope) === true) {
            return;
        }

        // Are we a catch parameter?
        if ($this->checkForCatchBlock($phpcsFile, $stackPtr, $varName, $currScope) === true) {
            return;
        }

        // Are we $this within a class?
        if ($this->checkForThisWithinClass($phpcsFile, $stackPtr, $varName, $currScope) === true) {
            return;
        }

        // Are we a $GLOBALS, $_REQUEST, etc superglobal?
        if ($this->checkForSuperGlobal($phpcsFile, $stackPtr, $varName, $currScope) === true) {
            return;
        }

        // $var part of class::$var static member
        if ($this->checkForStaticMember($phpcsFile, $stackPtr, $varName, $currScope) === true) {
            return;
        }

        // Is the next non-whitespace an assignment?
        if ($this->checkForAssignment($phpcsFile, $stackPtr, $varName, $currScope) === true) {
            return;
        }

        // OK, are we within a list (...) = construct?
        if ($this->checkForListAssignment($phpcsFile, $stackPtr, $varName, $currScope) === true) {
            return;
        }

        // Are we a global declaration?
        if ($this->checkForGlobalDeclaration($phpcsFile, $stackPtr, $varName, $currScope) === true) {
            return;
        }

        // Are we a static declaration?
        if ($this->checkForStaticDeclaration($phpcsFile, $stackPtr, $varName, $currScope) === true) {
            return;
        }

        // Are we a foreach loopvar?
        if ($this->checkForForeachLoopVar($phpcsFile, $stackPtr, $varName, $currScope) === true) {
            return;
        }

        // Are we pass-by-reference to known pass-by-reference function?
        if ($this->checkForPassByReferenceFunctionCall($phpcsFile, $stackPtr, $varName, $currScope) === true) {
            return;
        }

        // OK, we don't appear to be a write to the var, assume we're a read.
        $this->markVariableReadAndWarnIfUndefined($phpcsFile, $varName, $stackPtr, $currScope);

    }//end processVariable()


    /**
     * Called to process variables found in double quoted strings.
     *
     * Note that there may be more than one variable in the string, which will
     * result only in one call for the string.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where this
     *                                               token was found.
     * @param int                         $stackPtr  The position where the double quoted
     *                                               string was found.
     *
     * @return void
     */
    protected function processVariableInString(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];

        $runMatch = preg_match_all($this->doubleQuotedVariableRegexp, $token['content'], $matches);
        if ($runMatch === 0 || $runMatch === false) {
            return;
        }

        $currScope = $this->findVariableScope($phpcsFile, $stackPtr);
        foreach ($matches[1] as $varName) {
            $varName = $this->normalizeVarName($varName);
            // Are we $this within a class?
            if ($this->checkForThisWithinClass($phpcsFile, $stackPtr, $varName, $currScope) === true) {
                continue;
            }

            if ($this->checkForSuperGlobal($phpcsFile, $stackPtr, $varName, $currScope) === true) {
                continue;
            }

            $this->markVariableReadAndWarnIfUndefined($phpcsFile, $varName, $stackPtr, $currScope);
        }

    }//end processVariableInString()


    /**
     * Check variables in a compact() call.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile
     * @param int                         $stackPtr
     * @param array                       $arguments
     * @param string                      $currScope
     *
     * @return void
     */
    protected function processCompactArguments(File $phpcsFile, $stackPtr, $arguments, $currScope)
    {
        $tokens = $phpcsFile->getTokens();

        foreach ($arguments as $argumentPtrs) {
            $argumentPtrs = array_values(
                array_filter(
                    $argumentPtrs,
                    function ($argumentPtr) use ($tokens) {
                        return $tokens[$argumentPtr]['code'] !== T_WHITESPACE;
                    }
                )
            );
            if (empty($argumentPtrs) === true) {
                continue;
            }

            if (isset($tokens[$argumentPtrs[0]]) === false) {
                continue;
            }

            $argumentFirstToken = $tokens[$argumentPtrs[0]];
            if ($argumentFirstToken['code'] === T_ARRAY) {
                // It's an array argument, recurse.
                if (($arrayArguments = $this->findFunctionCallArguments($phpcsFile, $argumentPtrs[0])) !== false) {
                    $this->processCompactArguments($phpcsFile, $stackPtr, $arrayArguments, $currScope);
                }

                continue;
            }

            if (count($argumentPtrs) > 1) {
                // Complex argument, we can't handle it, ignore.
                continue;
            }

            if ($argumentFirstToken['code'] === T_CONSTANT_ENCAPSED_STRING) {
                // Single-quoted string literal, ie compact('whatever').
                // Substr is to strip the enclosing single-quotes.
                $varName = substr($argumentFirstToken['content'], 1, -1);
                $this->markVariableReadAndWarnIfUndefined($phpcsFile, $varName, $argumentPtrs[0], $currScope);
                continue;
            }

            if ($argumentFirstToken['code'] === T_DOUBLE_QUOTED_STRING) {
                // Double-quoted string literal.
                if (preg_match($this->doubleQuotedVariableRegexp, $argumentFirstToken['content']) === 1) {
                    // Bail if the string needs variable expansion, that's runtime stuff.
                    continue;
                }

                // Substr is to strip the enclosing double-quotes.
                $varName = substr($argumentFirstToken['content'], 1, -1);
                $this->markVariableReadAndWarnIfUndefined($phpcsFile, $varName, $argumentPtrs[0], $currScope);
                continue;
            }
        }//end foreach

    }//end processCompactArguments()


    /**
     * Called to process variables named in a call to compact().
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where this
     *                                               token was found.
     * @param int                         $stackPtr  The position where the call to compact()
     *                                               was found.
     *
     * @return void
     */
    protected function processCompact(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];

        $currScope = $this->findVariableScope($phpcsFile, $stackPtr);

        if (($arguments = $this->findFunctionCallArguments($phpcsFile, $stackPtr)) !== false) {
            $this->processCompactArguments($phpcsFile, $stackPtr, $arguments, $currScope);
        }

    }//end processCompact()


    /**
     * Called to process the end of a scope.
     *
     * Note that although triggered by the closing curly brace of the scope, $stackPtr is
     * the scope conditional, not the closing curly brace.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The PHP_CodeSniffer file where this
     *                                               token was found.
     * @param int                         $stackPtr  The position of the scope conditional.
     *
     * @return void
     */
    protected function processScopeClose(File $phpcsFile, $stackPtr)
    {
        $scopeInfo = $this->getScopeInfo($stackPtr, false);
        if ($scopeInfo === null) {
            return;
        }

        foreach ($scopeInfo->variables as $varInfo) {
            if (($varInfo->ignoreUnused === true) || (isset($varInfo->firstRead) === true)) {
                continue;
            }

            if (($this->allowUnusedFunctionParameters === true) && ($varInfo->scopeType === 'param')) {
                continue;
            }

            if (($varInfo->passByReference === true) && isset($varInfo->lastAssignment) === true) {
                // If we're pass-by-reference then it's a common pattern to
                // use the variable to return data to the caller, so any
                // assignment also counts as "variable use" for the purposes
                // of "unused variable" warnings.
                continue;
            }

            if (isset($varInfo->firstDeclared) === true) {
                $phpcsFile->addWarning(
                    "Unused %s %s.",
                    $varInfo->firstDeclared,
                    'UnusedVariable',
                    [
                        VariableInfo::$scopeTypeDescriptions[$varInfo->scopeType],
                        "\${$varInfo->name}",
                    ]
                );
            } else if (isset($varInfo->firstInitialized) === true) {
                $phpcsFile->addWarning(
                    "Unused %s %s.",
                    $varInfo->firstInitialized,
                    'UnusedVariable',
                    [
                        VariableInfo::$scopeTypeDescriptions[$varInfo->scopeType],
                        "\${$varInfo->name}",
                    ]
                );
            }//end if
        }//end foreach

    }//end processScopeClose()


}//end class
