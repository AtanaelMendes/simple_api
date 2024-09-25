<?php

namespace App\Core;

class Response
{
    private $statusCode;
    private $headers;
    private $content;
    private $sent;

    public function __construct()
    {
        $this->statusCode = 200;
        $this->headers = [];
        $this->content = null;
        $this->sent = false;
    }

    /**
     * Set HTTP status code
     */
    public function setStatusCode($code)
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Get HTTP status code
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Set header
     */
    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set multiple headers
     */
    public function setHeaders($headers)
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
        return $this;
    }

    /**
     * Get header
     */
    public function getHeader($name, $default = null)
    {
        return $this->headers[$name] ?? $default;
    }

    /**
     * Get all headers
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Set content type
     */
    public function setContentType($contentType)
    {
        return $this->setHeader('Content-Type', $contentType);
    }

    /**
     * Set JSON content type and data
     */
    public function json($data, $statusCode = 200)
    {
        $this->setStatusCode($statusCode);
        $this->setContentType('application/json; charset=utf-8');
        $this->content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return $this;
    }

    /**
     * Set HTML content
     */
    public function html($content, $statusCode = 200)
    {
        $this->setStatusCode($statusCode);
        $this->setContentType('text/html; charset=utf-8');
        $this->content = $content;
        return $this;
    }

    /**
     * Set plain text content
     */
    public function text($content, $statusCode = 200)
    {
        $this->setStatusCode($statusCode);
        $this->setContentType('text/plain; charset=utf-8');
        $this->content = $content;
        return $this;
    }

    /**
     * Set raw content
     */
    public function setContent($content, $statusCode = 200)
    {
        $this->setStatusCode($statusCode);
        $this->content = $content;
        return $this;
    }

    /**
     * Get content
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Send success response
     */
    public function success($data = null, $message = 'Success', $statusCode = 200)
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], $statusCode);
    }

    /**
     * Send error response
     */
    public function error($message = 'Error', $statusCode = 400, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return $this->json($response, $statusCode);
    }

    /**
     * Send validation error response
     */
    public function validationError($errors, $message = 'Validation failed')
    {
        return $this->error($message, 422, $errors);
    }

    /**
     * Send not found response
     */
    public function notFound($message = 'Resource not found')
    {
        return $this->error($message, 404);
    }

    /**
     * Send unauthorized response
     */
    public function unauthorized($message = 'Unauthorized')
    {
        return $this->error($message, 401);
    }

    /**
     * Send forbidden response
     */
    public function forbidden($message = 'Forbidden')
    {
        return $this->error($message, 403);
    }

    /**
     * Send internal server error response
     */
    public function serverError($message = 'Internal server error')
    {
        return $this->error($message, 500);
    }

    /**
     * Send created response
     */
    public function created($data = null, $message = 'Resource created successfully')
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Send no content response
     */
    public function noContent()
    {
        $this->setStatusCode(204);
        $this->content = '';
        return $this;
    }

    /**
     * Set CORS headers
     */
    public function cors($origin = '*', $methods = 'GET,POST,PUT,DELETE,OPTIONS', $headers = 'Content-Type,Authorization')
    {
        $this->setHeader('Access-Control-Allow-Origin', $origin);
        $this->setHeader('Access-Control-Allow-Methods', $methods);
        $this->setHeader('Access-Control-Allow-Headers', $headers);
        return $this;
    }

    /**
     * Check if response has been sent
     */
    public function isSent()
    {
        return $this->sent;
    }

    /**
     * Send the response
     */
    public function send()
    {
        if ($this->sent) {
            return $this;
        }

        // Set status code
        http_response_code($this->statusCode);

        // Send headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Send content
        if ($this->content !== null) {
            echo $this->content;
        }

        $this->sent = true;
        return $this;
    }

    /**
     * Send and exit
     */
    public function sendAndExit()
    {
        $this->send();
        exit;
    }

    /**
     * Magic method to automatically send response when used as string
     */
    public function __toString()
    {
        $this->send();
        return '';
    }
}