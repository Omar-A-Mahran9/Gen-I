<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';
        $query = $filter === 'unread' ? $request->user()->unreadNotifications() : $request->user()->notifications();

        return view('admin.notifications.index', [
            'notifications' => $query->paginate(15)->withQueryString(),
            'filter' => $filter,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        // Scoped to the current user: other users' notifications resolve to 404.
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        if (! $request->expectsJson() && $request->boolean('open') && is_string($url = $item->data['url'] ?? null) && str_starts_with($url, '/')) {
            return redirect()->to($url);
        }

        return $this->success($request, __('admin.notifications.messages.marked_read'), data: [
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success($request, __('admin.notifications.messages.all_marked_read'), data: ['unread' => 0]);
    }
}
