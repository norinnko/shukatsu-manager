<?php

namespace App\Enums;

enum MailAiStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Failed = 'failed';
}
