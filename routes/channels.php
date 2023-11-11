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

// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });

// This channel checks whether the user has access to the chat specified by $id. This is done by searching for a record in the ChatParticipant table where user_id equals $user->id and chat_id equals $id. If there is a recording, the user is allowed to join the channel, otherwise access is denied.
Broadcast::channel('chat.{id}', function ($user, $id) {
    $participant = \App\Models\ChatParticipant::where([
        [
            'user_id',
            $user->id,
        ],
        [
            'chat_id',
            $id
        ]
    ])->first();

    return $participant !== null;
});
