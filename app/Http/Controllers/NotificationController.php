<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationService $notificationService)
    {
        $filter = $request->get('filter', 'all');

        $query = DB::table('notifications')
            ->where('user_id', auth()->id())
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('created_at');

        $notifications = $query->paginate(15)->withQueryString();
        $unreadCount = $notificationService->getUnreadCount(auth()->id());

        return view('notifications.index', compact('notifications', 'filter', 'unreadCount'));
    }

    public function markAsRead(int $notification, NotificationService $notificationService)
    {
        $notificationService->markAsRead($notification, auth()->id());

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'تم تعليم الإشعار كمقروء.');
    }

    public function markAllAsRead(NotificationService $notificationService)
    {
        $count = $notificationService->markAllAsRead(auth()->id());

        return back()->with('success', "تم تعليم {$count} إشعار كمقروء.");
    }
}
