<?php

namespace App\Exceptions;

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
$path = __DIR__ . '/../../../logs/php_error_' . date('d-m-Y') . '.log';
ini_set('log_errors', 1);
ini_set('error_log', $path);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

use \Exception;
use App\Utils\Logger;

/**
 * Classe de Exceção personalizada para erros de sql
 */
class SqlException extends Exception {
    public function __construct($message = "Erro", $code = 500, Exception $previous = null) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $trace["ERROR"] = $this->stringMinifyer($message);

        Logger::error($trace, basename(__FILE__) . " linha-> " . __LINE__, "SQL_SOFTDOC_");
        parent::__construct($trace["ERROR"], $code, $previous);
    }

    private function stringMinifyer($string) :string
    {
        if (empty($string)) return "";
        // Remove espaços em branco, tabulações e quebras de linha
        $string = preg_replace('/\s+/', ' ', $string);
        // Remove espaços em branco antes e depois de parênteses
        $string = preg_replace('/\s+\(/', '(', $string);
        $string = preg_replace('/\)\s+/', ')', $string);
        return stripslashes($string);
    }
}
