<?php

namespace App\Domains\Push\Enums;

enum PushPlatform: string
{
    case Ios = 'ios';
    case Android = 'android';
    case Web = 'web';
}
