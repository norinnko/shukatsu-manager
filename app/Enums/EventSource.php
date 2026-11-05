<?php

namespace App\Enums;

enum EventSource: string
{
    case Manual = 'manual';
    case Import = 'import';
    case Gmail = 'gmail';
    case Line = 'line';
}
