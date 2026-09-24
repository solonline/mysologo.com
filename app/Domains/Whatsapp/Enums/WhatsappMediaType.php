<?php

namespace App\Domains\Whatsapp\Enums;

enum WhatsappMediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Document = 'document';
    case Audio = 'audio';
}
