<?php
namespace App\Exceptions;

class ErrorCode
{
    const INVALID_PIN = 'invalid_pin';
    const DEVICE_BLOCKED = 'device_blocked';
    const DEVICE_NOT_FOUND = 'device_not_found';
    const ACCOUNT_NOT_ACTIVE = 'account_not_active';
    const EMAIL_NOT_VERIFIED = 'email_not_verified';
    const DOCUMENT_NOT_APPROVED = 'document_not_approved';
    const INVALID_CREDENTIALS = 'invalid_credentials';
    const INTERNAL_ERROR = 'internal_error';
    const KYC_REQUIRED = 'kyc_required';
    const CORPORATE_WITHOUT_RESPONSIBLE = 'corporate_without_responsible';
    const CORPORATE_DOCUMENT_INVALID = 'corporate_document_invalid';
    const CORPORATE_DOCUMENT_MISSING = 'corporate_document_missing';
    const DIDIT_SESSION_CREATION_FAILED = 'didit_session_creation_failed';
    const FILE_UPLOAD_FAILED = 'file_upload_failed';
    const STRIPE_CUSTOMER_CREATION_FAILED = 'stripe_customer_creation_failed';
    const STRIPE_PAYMENT_INTENT_FAILED = 'stripe_payment_intent_failed';
    const STRIPE_SUBSCRIPTION_FAILED = 'stripe_subscription_failed';
    const STRIPE_SUBSCRIPTION_CANCEL_FAILED = 'stripe_subscription_cancel_failed';
}
