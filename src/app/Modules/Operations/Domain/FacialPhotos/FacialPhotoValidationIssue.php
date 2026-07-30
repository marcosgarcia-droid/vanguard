<?php

namespace App\Modules\Operations\Domain\FacialPhotos;

enum FacialPhotoValidationIssue: string
{
    case NoFaceDetected = 'no_face_detected';
    case MultipleFacesDetected = 'multiple_faces_detected';
    case FaceTooSmall = 'face_too_small';
    case FaceTooLarge = 'face_too_large';
    case FaceNotCentered = 'face_not_centered';
    case FaceOutsideFrame = 'face_outside_frame';
    case HeadPoseInvalid = 'head_pose_invalid';
    case EyesNotVisible = 'eyes_not_visible';
    case FaceOccluded = 'face_occluded';
    case ConfidenceInsufficient = 'confidence_insufficient';
    case ValidatorUnavailable = 'validator_unavailable';
    case InvalidValidatorResponse = 'invalid_validator_response';

    case ValidationPolicyUnavailable = 'validation_policy_unavailable';

    case ValidationCalibrationRequired = 'validation_calibration_required';

    public function label(): string
    {
        return match ($this) {
            self::NoFaceDetected => 'Nenhum rosto detectado',

            self::MultipleFacesDetected => 'Mais de um rosto detectado',

            self::FaceTooSmall => 'Rosto muito distante',

            self::FaceTooLarge => 'Rosto muito próximo',

            self::FaceNotCentered => 'Rosto fora do centro',

            self::FaceOutsideFrame => 'Rosto fora do enquadramento',

            self::HeadPoseInvalid => 'Posição da cabeça inadequada',

            self::EyesNotVisible => 'Olhos não identificados',

            self::FaceOccluded => 'Rosto parcialmente coberto',

            self::ConfidenceInsufficient => 'Confiança insuficiente',

            self::ValidatorUnavailable => 'Validador indisponível',

            self::InvalidValidatorResponse => 'Resposta inválida do validador',

            self::ValidationPolicyUnavailable => 'Política de validação indisponível',

            self::ValidationCalibrationRequired => 'Calibração da validação pendente',
        };
    }

    public function guidance(): string
    {
        return match ($this) {
            self::NoFaceDetected => 'Posicione uma pessoa de frente para a câmera e repita a captura.',

            self::MultipleFacesDetected => 'Mantenha somente uma pessoa visível na imagem.',

            self::FaceTooSmall => 'Aproxime-se da câmera até o rosto ocupar uma área adequada.',

            self::FaceTooLarge => 'Afaste-se um pouco para que todo o rosto permaneça visível.',

            self::FaceNotCentered => 'Centralize o rosto no enquadramento antes de repetir a captura.',

            self::FaceOutsideFrame => 'Mantenha cabeça, queixo e laterais do rosto dentro da imagem.',

            self::HeadPoseInvalid => 'Olhe diretamente para a câmera, sem inclinar ou virar a cabeça.',

            self::EyesNotVisible => 'Mantenha os olhos abertos e totalmente visíveis.',

            self::FaceOccluded => 'Remova objetos que estejam cobrindo partes relevantes do rosto.',

            self::ConfidenceInsufficient => 'Repita a captura em melhores condições de enquadramento e iluminação.',

            self::ValidatorUnavailable => 'A validação não pôde ser concluída. Tente novamente posteriormente.',

            self::InvalidValidatorResponse => 'A validação retornou um resultado inválido e deve ser repetida.',

            self::ValidationPolicyUnavailable => 'A análise foi recebida, mas a política de aprovação ainda não está disponível.',

            self::ValidationCalibrationRequired => 'A foto atende aos critérios determinísticos, mas a aprovação automática ainda aguarda calibração.',
        };
    }

    public function requiresInconclusiveDecision(): bool
    {
        return match ($this) {
            self::ValidatorUnavailable,
            self::InvalidValidatorResponse,
            self::ValidationPolicyUnavailable,
            self::ValidationCalibrationRequired => true,

            default => false,
        };
    }
}
