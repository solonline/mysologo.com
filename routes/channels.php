<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privado para notificações do dashboard (backoffice)
Broadcast::channel('dashboard.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
