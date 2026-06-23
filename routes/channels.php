<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Firma paneli kanalı — sadece o firmaya ait kullanıcı dinleyebilir
Broadcast::channel('company.{companyId}', function ($user, int $companyId) {
    return $user->company?->id === $companyId;
});

// Müşteri tracking kanalı — herkese açık (token zaten gizli)
Broadcast::channel('lead.{token}', function () {
    return true;
});
