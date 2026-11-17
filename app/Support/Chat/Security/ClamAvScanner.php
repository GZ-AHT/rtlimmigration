<?php

namespace App\Support\Chat\Security;

use RuntimeException;

/**
 * Scans a file with a running ClamAV daemon (clamd) over its socket, using
 * the INSTREAM command. No PHP extension or package is needed.
 */
class ClamAvScanner implements MalwareScanner
{
    private const CHUNK = 8192;

    public function __construct(private string $socket, private int $timeout = 30) {}

    public function enabled(): bool
    {
        return true;
    }

    public function scan(string $path): ?string
    {
        $errno = 0;
        $error = '';
        $stream = @stream_socket_client($this->socket, $errno, $error, $this->timeout);

        if ($stream === false) {
            throw new RuntimeException("ClamAV is not reachable at {$this->socket}: {$error}");
        }

        stream_set_timeout($stream, $this->timeout);
        $file = fopen($path, 'rb');

        if ($file === false) {
            fclose($stream);

            throw new RuntimeException('Could not open the file for scanning.');
        }

        try {
            fwrite($stream, "zINSTREAM\0");

            while (! feof($file)) {
                $chunk = (string) fread($file, self::CHUNK);

                if ($chunk === '') {
                    break;
                }

                fwrite($stream, pack('N', strlen($chunk)).$chunk);
            }

            fwrite($stream, pack('N', 0));
            $reply = trim((string) stream_get_contents($stream), "\0\r\n ");
        } finally {
            fclose($file);
            fclose($stream);
        }

        // "stream: OK" or "stream: Eicar-Signature FOUND"
        if (str_ends_with($reply, 'OK')) {
            return null;
        }

        if (preg_match('~:\s*(.+)\s+FOUND$~', $reply, $m) === 1) {
            return $m[1];
        }

        throw new RuntimeException("Unexpected ClamAV reply: {$reply}");
    }
}
