<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $alerts = $request->user()->notifications()->latest()->paginate(20);

        return view('alerts.index', compact('alerts'));
    }

    public function read(Request $request, string $notification)
    {
        $alert = $request->user()->notifications()->findOrFail($notification);
        $alert->markAsRead();

        return back();
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Todos os alertas foram marcados como lidos.');
    }
}
