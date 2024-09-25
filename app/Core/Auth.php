<?php

namespace App\Core;

/**
 * Gerenciamento de sessões para autenticação do admin
 */
class Auth
{
    /**
     * Inicia a sessão se ainda não estiver ativa
     */
    private static function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Armazena o usuário na sessão
     */
    public static function login($user)
    {
        self::startSession();
        
        $_SESSION['admin_user'] = $user;
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time();
        
        // Gera um token CSRF para proteção de formulários
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return true;
    }
    
    /**
     * Verifica se o usuário está autenticado
     */
    public static function check()
    {
        self::startSession();
        
        if (
            !isset($_SESSION['admin_logged_in']) || 
            $_SESSION['admin_logged_in'] !== true ||
            !isset($_SESSION['admin_user'])
        ) {
            return false;
        }
        
        // Verificar se a sessão expirou (4 horas)
        if (time() - $_SESSION['admin_login_time'] > 14400) {
            self::logout();
            return false;
        }
        
        // Atualiza o tempo de login para renovar a sessão
        $_SESSION['admin_login_time'] = time();
        
        return true;
    }
    
    /**
     * Retorna o usuário atual
     */
    public static function user()
    {
        self::startSession();
        
        return $_SESSION['admin_user'] ?? null;
    }
    
    /**
     * Encerra a sessão do usuário
     */
    public static function logout()
    {
        self::startSession();
        
        unset($_SESSION['admin_user']);
        unset($_SESSION['admin_logged_in']);
        unset($_SESSION['admin_login_time']);
        
        return true;
    }
    
    /**
     * Obter token CSRF para proteção de formulários
     */
    public static function getCsrfToken()
    {
        self::startSession();
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verifica se o token CSRF é válido
     */
    public static function validateCsrfToken($token)
    {
        self::startSession();
        
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            return false;
        }
        
        return true;
    }
}