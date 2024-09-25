<?php

namespace App\Utils;

/**
 * Classe Logger - Gerador de Logs de Erros
 *
 * Esta classe fornece uma funcionalidade de log de erros para registrar mensagens de erro em um arquivo de log.
 * Permite o registro de mensagens de erro com timestamps e quebras de linha.
 *
 * Autor: Rafael Brittes
 * Co autor: Atanael Gamarra Mendes Costa
 * Data: 06/06/2023
 */

class Logger
{
    public static function error($errorMessage, string $project, string $fileName = "logErrors") :void
    {
        $pathName = "/../logs/".$fileName.date("d-m-Y").".log";
        $dateNow = date("Y-m-d H:i:s");
        if (!file_exists($pathName)) {
            $arquivo = @fopen($pathName, 'w');
            @fclose($arquivo);
        }
        $logMessage =  "ERROR [" . $dateNow . "] ".$logMessage = $project . " " . self::arrayToJson($errorMessage) . PHP_EOL.PHP_EOL;
        error_log($logMessage, 3, __DIR__ . $pathName);
    }

    public static function warning($errorMessage, string $project, string $fileName = "logErrors") :void
    {
        $pathName = "/../logs/".$fileName.date("d-m-Y").".log";
        $dateNow = date("Y-m-d H:i:s");
        if (!file_exists($pathName)) {
            $arquivo = @fopen($pathName, 'w');
            @fclose($arquivo);
        }
        $logMessage =  "WARNING [" . $dateNow . "] ".$logMessage = $project . " " . self::arrayToJson($errorMessage) . PHP_EOL.PHP_EOL;
        error_log($logMessage, 3, __DIR__ . $pathName);
    }

    public static function info($errorMessage, string $project, string $fileName = "logErrors") :void
    {
        $pathName = "/../logs/".$fileName.date("d-m-Y").".log";
        $dateNow = date("Y-m-d H:i:s");
        if (!file_exists($pathName)) {
            $arquivo = @fopen($pathName, 'w');
            @fclose($arquivo);
        }
        $logMessage =  "INFO [" . $dateNow . "] ".$logMessage = $project . " " . self::arrayToJson($errorMessage) . PHP_EOL.PHP_EOL;
        error_log($logMessage, 3, __DIR__ . $pathName);
    }

    private static function arrayToJson($value) {
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        }
        return $value;
    }
}
