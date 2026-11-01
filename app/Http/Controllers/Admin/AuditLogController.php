<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\DataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('audit.view');

        if (! $request->expectsJson()) {
            return view('admin.audit-logs.index', [
                'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
            ]);
        }

        return DataTable::of(AuditLog::query()->with('user:id,name,email'), $request)
            ->searchable(['event', 'ip_address'])
            ->sortable(['created_at' => 'id', 'event' => 'event'])
            ->defaultOrder('id')
            ->filter(function (Builder $query, Request $request): void {
                if (is_string($event = $request->input('filters.event')) && $event !== '') {
                    $query->where('event', $event);
                }

                if (is_numeric($userId = $request->input('filters.user_id'))) {
                    $query->where('user_id', (int) $userId);
                }
            })
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'created_at' => e(format_datetime($log->created_at)),
                'user' => $log->user ? e($log->user->name).'<div class="text-muted fs-7">'.e($log->user->email).'</div>' : '<span class="text-muted">'.e(__('admin.audit.system')).'</span>',
                'event' => view('admin.audit-logs.partials.event-badge', ['event' => $log->event])->render(),
                'subject' => e($log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—'),
                'details' => view('admin.audit-logs.partials.details', ['log' => $log])->render(),
                'ip_address' => e($log->ip_address ?? '—'),
            ])
            ->toResponse();
    }
}
