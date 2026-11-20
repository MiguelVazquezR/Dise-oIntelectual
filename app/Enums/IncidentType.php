<?php

namespace App\Enums;

enum IncidentType: string
{
    case Vacation = 'vacation';
    case JustifiedAbsence = 'justified_absence';
    case UnjustifiedAbsence = 'unjustified_absence';
    case SickLeave = 'sick_leave';
    case PaidLeave = 'paid_leave';
    case UnpaidLeave = 'unpaid_leave';
    case WorkedRestDay = 'worked_rest_day';
    case WorkedHoliday = 'worked_holiday';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Vacation => 'Vacaciones',
            self::JustifiedAbsence => 'Falta justificada',
            self::UnjustifiedAbsence => 'Falta injustificada',
            self::SickLeave => 'Incapacidad',
            self::PaidLeave => 'Permiso con goce de sueldo',
            self::UnpaidLeave => 'Permiso sin goce de sueldo',
            self::WorkedRestDay => 'Descanso laborado',
            self::WorkedHoliday => 'Día festivo laborado',
            self::Other => 'Otro',
        };
    }

    /**
     * Tipos que descuentan tiempo laborado en la pre-nómina.
     *
     * @return array<int, self>
     */
    public static function absenceTypes(): array
    {
        return [
            self::UnjustifiedAbsence,
            self::UnpaidLeave,
            self::JustifiedAbsence,
            self::SickLeave,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
