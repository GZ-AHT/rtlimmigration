<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
