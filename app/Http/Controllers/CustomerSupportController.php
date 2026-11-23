<?php

namespace App\Http\Controllers;

use App\Models\SupportConversation;
use App\Models\User;
use App\Services\CustomerSupport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerSupportController extends Controller
{
    public function index(Request $request): View
    {
        return view('customer.support.index', [
            'conversations' => $this->customer($request)->supportConversations()->latest('last_message_at')->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('customer.support.create');
    }

    public function store(Request $request, CustomerSupport $support): RedirectResponse
    {
        $data = $request->validate($this->startRules());
        $conversation = $support->start($this->customer($request), $data['subject'], $data['message']);

        return to_route('support.show', $conversation, 303)->with('status', 'Đã gửi tin nhắn đến cửa hàng.');
    }

    public function show(Request $request, SupportConversation $conversation): View
    {
        abort_unless($conversation->user_id === $this->customer($request)->id, 404);
        $conversation->load(['messages.sender', 'closedBy']);

        return view('customer.support.show', compact('conversation'));
    }

    public function reply(Request $request, SupportConversation $conversation, CustomerSupport $support): RedirectResponse
    {
        abort_unless($conversation->user_id === $this->customer($request)->id, 404);
        $support->reply($this->customer($request), $conversation, $request->validate($this->replyRules())['message']);

        return to_route('support.show', $conversation, 303)->with('status', 'Đã gửi phản hồi.');
    }

    /** @return array<string, array<int, string>> */
    private function startRules(): array
    {
        return ['subject' => ['required', 'string', 'max:120'], 'message' => ['required', 'string', 'max:2000']];
    }

    /** @return array<string, array<int, string>> */
    private function replyRules(): array
    {
        return ['message' => ['required', 'string', 'max:2000']];
    }

    private function customer(Request $request): User
    {
        /** @var User $customer */
        $customer = $request->user();

        return $customer;
    }
}
