<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => 'sanctum']);
Broadcast::channel('my-private-channel.user.{id}', function ($user, $id) {
    return (int) $user->profile_id === (int) $id;
}, ['guards' => 'sanctum']);
Broadcast::channel('check-online.user.{id}', function ($user, $id) {
    return (int) $user->profile_id === (int) $id;
}, ['guards' => 'sanctum']);
