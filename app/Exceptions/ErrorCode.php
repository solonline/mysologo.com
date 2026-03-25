<?php
namespace App\Exceptions;

class ErrorCode
{
    const INVALID_PIN = 'invalid_pin';
    const DEVICE_BLOCKED = 'device_blocked';
    const DEVICE_NOT_FOUND = 'device_not_found';
    const INVALID_CREDENTIALS = 'invalid_credentials';
    const INTERNAL_ERROR = 'internal_error';
    const KYC_REQUIRED = 'kyc_required';
    const CORPORATE_WITHOUT_RESPONSIBLE = 'corporate_without_responsible';
    const CORPORATE_DOCUMENT_INVALID = 'corporate_document_invalid';
    const CORPORATE_DOCUMENT_MISSING = 'corporate_document_missing';
}
