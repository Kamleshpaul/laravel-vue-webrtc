<?php

namespace App\Http\Controllers;

use App\Events\AnswerCall;
use App\Events\sendCandidate;
use App\Events\StartCall;
use App\Models\User;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function startCall(Request $request)
    {
        $user = User::findOrFail($request->id);
        event(new StartCall($user, auth()->user(), $request->data));

        return response([
            "status" => true,
            "message" => "calling.."
        ]);
    }

    public function AnswerCall(Request $request)
    {
        $user = User::findOrFail($request->id);
        event(new AnswerCall($user, $request->data));

        return response([
            "status" => true,
            "message" => "calling.."
        ]);
    }

    public function candidate(Request $request)
    {
        $user = User::findOrFail($request->id);
        event(new sendCandidate($user, $request->data));

        return response([
            "status" => true,
            "message" => "candidate send.."
        ]);
    }
}
