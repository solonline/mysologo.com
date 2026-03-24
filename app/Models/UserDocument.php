<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDocument extends Model
{
    protected $fillable = [
        'user_id',
        'document_type',
        'document_number',
        'personal_number',
        'date_of_birth',
        'age',
        'expiration_date',
        'date_of_issue',
        'issuing_state',
        'issuing_state_name',
        'first_name',
        'last_name',
        'full_name',
        'gender',
        'address',
        'formatted_address',
        'place_of_birth',
        'marital_status',
        'nationality',
        'front_image',
        'back_image',
        'full_front_image',
        'full_back_image',
        'portrait_image',
        'front_video',
        'back_video',
        'back_image_camera_front',
        'front_image_camera_front',
        'face_match_score',
        'liveness_score',
        'back_image_camera_front_face_match_score',
        'front_image_camera_front_face_match_score',
        'front_image_quality_score',
        'back_image_quality_score',
        'extra_fields',
        'parsed_address',
        'ip_analyses',
        'nfc_verifications',
        'phone_verifications'
    ];

    protected $casts = [
        'age' => 'integer',
        'face_match_score' => 'float',
        'liveness_score' => 'float',
        'back_image_camera_front_face_match_score' => 'float',
        'front_image_camera_front_face_match_score' => 'float',
        'front_image_quality_score' => 'array',
        'back_image_quality_score' => 'array',
        'extra_fields' => 'array',
        'parsed_address' => 'array',
        'ip_analyses' => 'array',
        'nfc_verifications' => 'array',
        'phone_verifications' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
