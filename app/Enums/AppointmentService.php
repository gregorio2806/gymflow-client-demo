<?php

namespace App\Enums;

enum AppointmentService: string
{
    case PersonalTraining = 'personal-training';
    case GroupSession = 'group-session';
    case FitnessAssessment = 'fitness-assessment';
    case Consultation = 'consultation';

    public function label(): string
    {
        return match ($this) {
            self::PersonalTraining => 'Personal training', self::GroupSession => 'Group session',
            self::FitnessAssessment => 'Fitness assessment', self::Consultation => 'Consultation',
        };
    }
}
