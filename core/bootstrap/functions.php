<?php
/**
 * Simple functions of Application
 * be careful with this way
 * @author  Anton Shevchuk
 * @created 25.07.13 13:34
 */

// Write message to log file
if (!function_exists('errorLog')) {
    function errorLog($message)
    {
        if (is_dir(PATH_MODXCORE .'/log')
            && is_writable(PATH_MODXCORE .'/log')) {
            file_put_contents(
                PATH_MODXCORE .'/log/'.(date('Y-m-d')).'.log',
                "[".date("H:i:s")."]\t".$message."\n",
                FILE_APPEND | LOCK_EX
            );
        }
    }
}

// Error Handler
if (!function_exists('errorHandler')) {
    function errorHandler()
    {
        $e = error_get_last();
        // check error type
        if (!is_array($e)
            || !in_array($e['type'], array(E_ERROR, E_USER_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            return;
        }
        // clean all buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        // try to write log
        errorLog($e['message'] ."\n". $e['file'] ."#". $e['line'] ."\n");
    }
}

// Error Handler
if (!function_exists('errorDisplay')) {
    function errorDisplay() {
       if (!$e = error_get_last()) {
            return;
        }

        if (!is_array($e)
            || !in_array($e['type'], array(E_ERROR, E_USER_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            return;
        }
        require_once PATH_MODXCORE . '/error.php';
    }
}

if (!function_exists('modx')){
    function modx(){
        global $modx;
        return $modx;
    }
}

/**
 * Simple functions of framework
 * be careful with this way
 * @author   Anton Shevchuk
 * @created  07.09.12 11:29
 */
if (!function_exists('modx_dump')) {
    /**
     * Debug variables
     *
     * @return void
     */
    function modx_dump()
    {
        // check definition
        if (!defined('MODX_DEBUG') or !MODX_DEBUG) {
            return;
        }

        ini_set('xdebug.var_display_max_children', 512);

        if ('cli' == PHP_SAPI) {
            if (extension_loaded('xdebug')) {
                // try to enable CLI colors
                ini_set('xdebug.cli_color', 1);
                xdebug_print_function_stack();
            } else {
                debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            }
            var_dump(func_get_args());
        } else {
            echo '<div class="textleft clear"><pre>';
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            var_dump(func_get_args());
            echo '</pre></div>';
        }
    }
}


// start cms session
if(!function_exists('startCMSSession')) {
    function startCMSSession(){
        global $site_sessionname;
        session_name($site_sessionname);
        session_start();
        $cookieExpiration= 0;
        if (isset ($_SESSION['mgrValidated']) || isset ($_SESSION['webValidated'])) {
            $contextKey= isset ($_SESSION['mgrValidated']) ? 'mgr' : 'web';
            if (isset ($_SESSION['modx.' . $contextKey . '.session.cookie.lifetime']) && is_numeric($_SESSION['modx.' . $contextKey . '.session.cookie.lifetime'])) {
                $cookieLifetime= intval($_SESSION['modx.' . $contextKey . '.session.cookie.lifetime']);
            }
            if ($cookieLifetime) {
                $cookieExpiration= time() + $cookieLifetime;
            }
            if (!isset($_SESSION['modx.session.created.time'])) {
                $_SESSION['modx.session.created.time'] = time();
            }
        }
        setcookie(session_name(), session_id(), $cookieExpiration, MODX_BASE_URL);
    }
}