<?php

namespace App\Support\Email;

use App\Models\Lead;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Fills {{ placeholders }} in templates and turns plain text into the HTML
 * that is mailed. Bodies are written as plain text, so nothing a user types
 * becomes markup: text is escaped, links are made clickable and line breaks
 * kept.
 */
class TemplateRenderer
{
    /** Placeholders offered in the editors, with what they stand for. */
    public const PLACEHOLDERS = [
        'name' => 'Recipient\'s full name',
        'first_name' => 'Recipient\'s first name',
        'email' => 'Recipient\'s email',
        'student_number' => 'Student number',
        'file_number' => 'Latest file number',
        'counselor_name' => 'Assigned counselor',
        'sender_name' => 'Your name',
        'company_name' => 'Company name',
    ];

    /** @return array<string, string> */
    public function variables(?Model $recipient, ?User $sender, ?string $name = null, ?string $email = null): array
    {
        $vars = [
            'name' => (string) $name,
            'email' => (string) $email,
            'student_number' => '',
            'file_number' => '',
            'counselor_name' => '',
            'sender_name' => (string) $sender?->name,
            'company_name' => (string) config('app.name'),
        ];

        if ($recipient instanceof Student) {
            $recipient->loadMissing(['user:id,name,email', 'counselor:id,name']);
            $vars['name'] = $recipient->user->name;
            $vars['email'] = $recipient->user->email;
            $vars['student_number'] = $recipient->student_number;
            $vars['file_number'] = (string) $recipient->files()->latest('id')->value('file_number');
            $vars['counselor_name'] = (string) $recipient->counselor?->name;
        } elseif ($recipient instanceof Lead) {
            $recipient->loadMissing('counselor:id,name');
            $vars['name'] = $recipient->name;
            $vars['email'] = (string) $recipient->email;
            $vars['counselor_name'] = (string) $recipient->counselor?->name;
        } elseif ($recipient instanceof User) {
            $vars['name'] = $recipient->name;
            $vars['email'] = $recipient->email;
        }

        $vars['first_name'] = (string) strtok(trim($vars['name']), ' ');

        return $vars;
    }

    /**
     * Unknown placeholders are left as typed so a typo is visible in the log.
     *
     * @param  array<string, string>  $vars
     */
    public function fill(string $text, array $vars): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn (array $m) => array_key_exists($m[1], $vars) ? $vars[$m[1]] : $m[0], $text);
    }

    /** Plain text (already filled) plus an optional signature, as the HTML body. */
    public function toHtml(string $text, ?string $signature = null): string
    {
        $html = $this->paragraphs($text);

        if (filled($signature)) {
            $html .= '<div style="margin-top:24px;padding-top:12px;border-top:1px solid #e5e7eb;color:#4b5563">'.$this->paragraphs((string) $signature).'</div>';
        }

        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#111827">'.$html.'</div>';
    }

    private function paragraphs(string $text): string
    {
        $escaped = e(str_replace("\r\n", "\n", trim($text)));

        $linked = (string) preg_replace_callback(
            '~\bhttps?://[^\s<>"\']+~i',
            function (array $m) {
                // Leave trailing punctuation outside the link.
                $url = rtrim($m[0], '.,;:!?)');
                $rest = substr($m[0], strlen($url));

                return '<a href="'.$url.'" style="color:#1d4ed8">'.$url.'</a>'.$rest;
            },
            $escaped,
        );

        return nl2br($linked, false);
    }
}
