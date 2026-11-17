<?php

namespace App\Support\Chat\Security;

use App\Support\Chat\ChatSecuritySettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Decides whether a chat attachment is safe to store. Nothing the browser
 * says is trusted: the type comes from the file's contents and signature,
 * the name is cleaned and given the real extension, and documents with
 * scripts or embedded files are refused.
 */
class AttachmentInspector
{
    /** File signatures ("magic bytes") per allowed MIME type. */
    private const SIGNATURES = [
        'application/pdf' => '%PDF-',
        'image/jpeg' => "\xFF\xD8\xFF",
        'image/png' => "\x89PNG\r\n\x1A\n",
    ];

    /** Types that are an attack when they turn up as an attachment, whatever they are called. */
    private const DANGEROUS_MIMES = [
        'application/x-dosexec', 'application/x-msdownload', 'application/x-executable', 'application/x-sharedlib',
        'application/x-mach-binary', 'application/x-elf', 'application/x-pie-executable', 'application/java-archive',
        'application/x-msi', 'application/vnd.microsoft.portable-executable', 'text/x-php', 'application/x-php',
        'text/x-shellscript', 'application/x-sh', 'text/html', 'application/xhtml+xml', 'application/javascript',
        'text/javascript', 'image/svg+xml', 'application/x-httpd-php', 'text/x-python', 'text/x-perl',
    ];

    private const DANGEROUS_EXTENSIONS = [
        'exe', 'dll', 'scr', 'bat', 'cmd', 'com', 'msi', 'ps1', 'vbs', 'vbe', 'js', 'jse', 'wsf', 'hta', 'jar', 'apk',
        'sh', 'php', 'phtml', 'phar', 'pl', 'py', 'rb', 'cgi', 'html', 'htm', 'svg', 'xhtml', 'lnk', 'iso', 'reg',
    ];

    /**
     * Active content in a PDF: scripts, launch actions and embedded files.
     * Markers are long enough that random bytes in compressed streams do
     * not trip them (a bare "/JS" must be followed by its value).
     */
    private const PDF_ACTIVE = '~/(JavaScript|Launch|EmbeddedFiles?|RichMedia)\b|/JS\s*(\(|<|\d+\s+\d+\s+R)~';

    /**
     * Code hidden inside an image or document (a "polyglot" file). Only
     * markers of five or more characters, so ordinary photo data never
     * matches by chance.
     */
    private const EMBEDDED_CODE = '~<\?php|<script\b|#!/(usr/)?bin/~i';

    public function __construct(private ChatSecuritySettings $settings, private MalwareScanner $scanner) {}

    public function inspect(UploadedFile $file): InspectedAttachment
    {
        $allowed = $this->settings->allowedExtensions();
        $clientExt = strtolower($file->getClientOriginalExtension());

        if (! $file->isValid() || ($path = $file->getRealPath()) === false) {
            throw new AttachmentRejected(__('The file did not upload completely. Try again.'), 'upload_failed');
        }

        $size = (int) $file->getSize();

        if ($size === 0) {
            throw new AttachmentRejected(__(':name is empty.', ['name' => self::displayName($file->getClientOriginalName(), $clientExt)]), 'empty');
        }

        if ($size > $this->settings->int('max_attachment_kb') * 1024) {
            throw new AttachmentRejected(__('Each file must be :max MB or smaller.', ['max' => round($this->settings->int('max_attachment_kb') / 1024, 1)]), 'too_large');
        }

        $detected = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (in_array($detected, self::DANGEROUS_MIMES, true) || in_array($clientExt, self::DANGEROUS_EXTENSIONS, true)) {
            throw new AttachmentRejected(__('This type of file cannot be sent in chat.'), 'dangerous_type', malicious: true);
        }

        $extension = $this->extensionFor($detected, $allowed);

        // The name must agree with the contents: a PDF renamed .png is refused.
        if ($extension === null || ! isset($allowed[$clientExt]) || ! in_array($detected, $allowed[$clientExt], true)) {
            throw new AttachmentRejected(__('Attach a :types file.', ['types' => $this->allowedLabel()]), 'type_not_allowed');
        }

        $contents = (string) file_get_contents($path);

        if (! str_starts_with($contents, self::SIGNATURES[$detected])) {
            throw new AttachmentRejected(__('This file is damaged or is not really a :type.', ['type' => strtoupper($extension)]), 'bad_signature', malicious: true);
        }

        if (preg_match(self::EMBEDDED_CODE, $contents) === 1) {
            throw new AttachmentRejected(__('This file contains code and cannot be sent in chat.'), 'embedded_code', malicious: true);
        }

        if ($detected === 'application/pdf' && preg_match(self::PDF_ACTIVE, $contents) === 1) {
            throw new AttachmentRejected(__('This PDF contains scripts or embedded files, so it cannot be sent in chat. Save it again as a plain PDF, or upload it under Documents.'), 'pdf_active_content', malicious: true);
        }

        if (str_starts_with($detected, 'image/')) {
            $info = @getimagesize($path);

            if ($info === false || $info['mime'] !== $detected) {
                throw new AttachmentRejected(__('This image could not be read.'), 'bad_image', malicious: true);
            }
        }

        if ($this->scanner->enabled()) {
            try {
                $found = $this->scanner->scan($path);
            } catch (\RuntimeException $e) {
                Log::error('Chat attachment could not be scanned for malware.', ['error' => $e->getMessage()]);

                throw new AttachmentRejected(__('Files cannot be checked for viruses right now, so they cannot be sent. Try again later or send text only.'), 'scanner_unavailable');
            }

            if ($found !== null) {
                throw new AttachmentRejected(__('This file was flagged by the virus scanner and cannot be sent.'), 'malware:'.mb_substr($found, 0, 100), malicious: true);
            }
        }

        return new InspectedAttachment($file, $extension, $detected, self::displayName($file->getClientOriginalName(), $extension), $size);
    }

    /**
     * A name that is safe to show and to send in a download header: no
     * path, control or direction-override characters, at most 100
     * characters, ending in the real extension.
     */
    public static function displayName(string $original, string $extension): string
    {
        $base = pathinfo(str_replace('\\', '/', $original), PATHINFO_FILENAME);
        $base = (string) preg_replace('~[\p{C}]~u', '', $base);
        $base = (string) preg_replace('~[^\p{L}\p{N} ._()\-]~u', '_', $base);
        $base = trim((string) preg_replace('~[_ .]{2,}~', '_', $base), ' ._-');
        $base = mb_substr($base, 0, 100);

        return ($base !== '' ? $base : 'attachment').'.'.$extension;
    }

    /** @param  array<string, list<string>>  $allowed */
    private function extensionFor(string $mime, array $allowed): ?string
    {
        foreach (ChatSecuritySettings::ATTACHMENT_TYPES as $type => $info) {
            if (in_array($mime, $info['mimes'], true) && isset($allowed[$info['extensions'][0]])) {
                return $info['extensions'][0];
            }
        }

        return null;
    }

    private function allowedLabel(): string
    {
        $labels = array_map(fn (string $t) => ChatSecuritySettings::ATTACHMENT_TYPES[$t]['label'], $this->settings->allowedAttachmentTypes());

        return implode(', ', $labels);
    }
}
