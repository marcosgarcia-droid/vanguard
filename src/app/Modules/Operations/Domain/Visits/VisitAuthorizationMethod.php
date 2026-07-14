<?php

namespace App\Modules\Operations\Domain\Visits;

enum VisitAuthorizationMethod: string
{
    case System = 'system';
    case Phone = 'phone';
    case Message = 'message';
    case Radio = 'radio';
    case InPerson = 'in_person';
    case PriorAuthorization = 'prior_authorization';
    case Contingency = 'contingency';

    public function label(): string
    {
        return match ($this) {
            self::System => 'Pelo sistema',
            self::Phone => 'Ligação telefônica',
            self::Message => 'Mensagem',
            self::Radio => 'Rádio',
            self::InPerson => 'Presencial',
            self::PriorAuthorization => 'Autorização prévia',
            self::Contingency => 'Contingência',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $method): array => [
                $method->value => $method->label(),
            ])
            ->all();
    }
}
