<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function me()
    {
        return UserResource::make(auth()->user());
    }

    public function update(UpdateUserRequest $request)
    {
        return UserResource::make((new UserService())->update($request->validated()));
    }
}
