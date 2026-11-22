<?php

namespace App\Http\Controllers;

use App\Models\SupportConversation;
use App\Models\User;
use App\Services\CustomerSupport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OperationsSupportController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', 'in:open,closed']])['status'] ?? 'open';

        return view('operations.support.index', [
            'status' => $status,
            'conversations' => SupportConversation::query()->with('customer')->where('status', $status)
                ->latest('last_message_at')->paginate(20)->withQueryString(),
        ]);
    }

    public function show(SupportConversation $conversation): View
    {
        $conversation->load(['customer', 'messages.sender', 'closedBy']);

        return view('operations.support.show', compact('conversation'));
    }

    public function reply(Request $request, SupportConversation $conversation, CustomerSupport $support): RedirectResponse
    {
        $support->reply($this->actor($request), $conversation, $request->validate(['message' => ['required', 'string', 'max:2000']])['message']);

        return to_route('operations.support.show', $conversation, 303)->with('status', 'Đã gửi phản hồi cho khách.');
    }

    public function close(Request $request, SupportConversation $conversation, CustomerSupport $support): RedirectResponse
    {
        $support->close($this->actor($request), $conversation);

        return to_route('operations.support.show', $conversation, 303)->with('status', 'Đã đóng cuộc trao đổi.');
    }

    private function actor(Request $request): User
    {
        /** @var User $actor */
        $actor = $request->user();

        return $actor;
    }
}
