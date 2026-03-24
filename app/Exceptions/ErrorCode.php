<?php
namespace App\Exceptions;

class ErrorCode
{
    const INVALID_PIN = 'invalid_pin';
    const DEVICE_BLOCKED = 'device_blocked';
    const DEVICE_NOT_FOUND = 'device_not_found';
    const INVALID_CREDENTIALS = 'invalid_credentials';
    const INTERNAL_ERROR = 'internal_error';
}
