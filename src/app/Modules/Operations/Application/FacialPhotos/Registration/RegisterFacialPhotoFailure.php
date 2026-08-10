<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Registration;

enum RegisterFacialPhotoFailure: string
{
    case InvalidSubject = 'invalid_subject';
    case SubjectNotFound = 'subject_not_found';
    case SubjectUnavailable = 'subject_unavailable';
    case SourceFileUnavailable = 'source_file_unavailable';
    case InvalidExpectedFingerprint = 'invalid_expected_fingerprint';
    case DefinitiveFingerprintUnavailable = 'definitive_fingerprint_unavailable';
    case DefinitiveFingerprintMismatch = 'definitive_fingerprint_mismatch';
    case InvalidConfirmationProof = 'invalid_confirmation_proof';
    case ConfirmationAlreadyConsumed = 'confirmation_already_consumed';
    case RegistrationFailed = 'registration_failed';
}
