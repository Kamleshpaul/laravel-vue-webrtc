<?php

namespace App\Http\Controllers;

use App\Events\StartCall;
use App\Models\User;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function startCall(Request $request)
    {
        $user = User::findOrFail($request->id);
        event(new StartCall($user, auth()->user()));

        return response([
            "status" => true,
            "message" => "calling.."
        ]);
    }
}
