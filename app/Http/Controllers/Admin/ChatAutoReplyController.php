<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Chat\AutoReply\AutoReplySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Live Chat → Auto-replies: the predefined answers the website chat gives visitors. */
class ChatAutoReplyController extends Controller
{
    public function edit(AutoReplySettings $settings): Response
    {
        Gate::authorize('chat.manage');

        return Inertia::render('admin/chat/AutoReplies', [
            'settings' => $settings->forAdmin(),
            'agents' => User::query()->where('status', UserStatus::Active->value)->whereHas('roles')->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u) => ['value' => (string) $u->id, 'label' => $u->name])->values()->all(),
            'limits' => [
                'rules' => AutoReplySettings::MAX_RULES,
                'keywords' => AutoReplySettings::MAX_KEYWORDS,
                'reply' => AutoReplySettings::MAX_REPLY,
            ],
        ]);
    }

    public function update(Request $request, AutoReplySettings $settings): RedirectResponse
    {
        Gate::authorize('chat.manage');

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'assistant_name' => ['required', 'string', 'max:40'],
            'fallback' => ['required', 'string', 'max:'.AutoReplySettings::MAX_REPLY],
            'welcome' => ['required', 'string', 'max:300'],
            'handover_enabled' => ['required', 'boolean'],
            'handover_agent_name' => ['required', 'string', 'max:60'],
            'handover_agent_id' => ['nullable', 'integer', 'exists:users,id'],
            'handover_avatar' => ['nullable', 'integer', 'exists:media,id'],
            'handover_message' => ['required', 'string', 'max:300'],
            'handover_keywords' => ['present', 'array', 'max:'.AutoReplySettings::MAX_KEYWORDS * 2],
            'handover_keywords.*' => ['required', 'string', 'max:60'],
            'rules' => ['present', 'array', 'max:'.AutoReplySettings::MAX_RULES],
            'rules.*.id' => ['nullable', 'string', 'max:40'],
            'rules.*.name' => ['required', 'string', 'max:80'],
            'rules.*.active' => ['required', 'boolean'],
            'rules.*.keywords' => ['required', 'array', 'min:1', 'max:'.AutoReplySettings::MAX_KEYWORDS],
            'rules.*.keywords.*' => ['required', 'string', 'max:60'],
            'rules.*.reply' => ['required', 'string', 'max:'.AutoReplySettings::MAX_REPLY],
        ], [
            'rules.*.keywords.required' => __('Add at least one keyword to each reply.'),
            'rules.*.keywords.min' => __('Add at least one keyword to each reply.'),
            'rules.*.reply.required' => __('Write the reply text.'),
            'rules.*.name.required' => __('Give each reply a name.'),
        ]);

        $settings->save($data);

        activity('chat')->causedBy($request->user())->withProperties(['rules' => count($data['rules']), 'enabled' => (bool) $data['enabled']])->log('Chat auto-replies updated');

        $this->toast(['type' => 'success', 'message' => __('Auto-replies saved.')]);

        return back();
    }
}
