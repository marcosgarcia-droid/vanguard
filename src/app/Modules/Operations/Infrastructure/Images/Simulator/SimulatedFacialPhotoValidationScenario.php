<?php

namespace App\Modules\Operations\Infrastructure\Images\Simulator;

enum SimulatedFacialPhotoValidationScenario: string
{
    case Approved = 'approved';
    case NoFaceDetected = 'no_face_detected';
    case MultipleFacesDetected = 'multiple_faces_detected';
    case InvalidFraming = 'invalid_framing';
    case FaceOccluded = 'face_occluded';
    case ValidatorUnavailable = 'validator_unavailable';
    case InvalidValidatorResponse = 'invalid_validator_response';
}
