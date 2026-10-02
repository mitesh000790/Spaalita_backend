<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parses multipart/form-data and application/x-www-form-urlencoded bodies
 * the way the original Express/multer backend did:
 *
 * - repeated field names become arrays ("images" sent twice => 2 files),
 *   where PHP's built-in parser would keep only the last value;
 * - bodies of PUT / PATCH / DELETE requests are parsed too (PHP only parses POST);
 * - "name[]" and "name[key]" build arrays / nested objects.
 *
 * It needs the raw request body, so PHP's own form parsing is turned off
 * with enable_post_data_reading=Off (public/.htaccess, public/.user.ini and
 * `php artisan serve`). If PHP has already consumed a POST body, the
 * request is left as PHP parsed it.
 */
class ParseFormBody
{
    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    public function handle(Request $request, Closure $next): Response
    {
        $contentType = strtolower((string) $request->header('Content-Type', ''));
        $body = $request->getContent();

        if ($body !== '') {
            if (str_starts_with($contentType, 'multipart/form-data') && ($boundary = $this->boundary($request)) !== null) {
                [$fields, $files] = $this->parseMultipart($body, $boundary);

                $request->request->replace($fields);
                $request->files->replace($files);
            } elseif (str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
                $request->request->replace($this->parseUrlEncoded($body));
            }
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->temporaryFiles = [];
    }

    private function boundary(Request $request): ?string
    {
        $contentType = (string) $request->header('Content-Type', '');

        if (preg_match('/boundary=(?:"([^"]+)"|([^;]+))/i', $contentType, $matches) !== 1) {
            return null;
        }

        return trim($matches[1] !== '' ? $matches[1] : $matches[2]);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseUrlEncoded(string $body): array
    {
        $fields = [];

        foreach (explode('&', $body) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $this->assign($fields, urldecode($name), urldecode($value));
        }

        return $fields;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function parseMultipart(string $body, string $boundary): array
    {
        $fields = [];
        $files = [];

        foreach (array_slice(explode('--'.$boundary, $body), 1) as $part) {
            if (str_starts_with($part, '--')) {
                break;
            }

            $part = preg_replace('/^\r?\n/', '', $part);
            $separator = str_contains($part, "\r\n\r\n") ? "\r\n\r\n" : "\n\n";

            if (! str_contains($part, $separator)) {
                continue;
            }

            [$rawHeaders, $content] = explode($separator, $part, 2);
            $content = preg_replace('/\r?\n$/', '', $content);
            $headers = $this->headers($rawHeaders);
            $disposition = $headers['content-disposition'] ?? '';

            if (preg_match('/\bname="([^"]*)"/i', $disposition, $name) !== 1) {
                continue;
            }

            if (preg_match('/\bfilename="([^"]*)"/i', $disposition, $filename) === 1) {
                // Empty file inputs are ignored, as multer does.
                if ($filename[1] !== '') {
                    $this->assign($files, $name[1], $this->temporaryUpload($content, $filename[1], $headers['content-type'] ?? null));
                }

                continue;
            }

            $this->assign($fields, $name[1], $content);
        }

        return [$fields, $files];
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $rawHeaders): array
    {
        $headers = [];

        foreach (preg_split('/\r?\n/', $rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }

        return $headers;
    }

    private function temporaryUpload(string $content, string $originalName, ?string $mimeType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $originalName, $mimeType, UPLOAD_ERR_OK, true);
    }

    /**
     * Add a field value under its name, merging repeated names into arrays.
     *
     * @param  array<string|int, mixed>  $target
     */
    private function assign(array &$target, string $name, mixed $value): void
    {
        $steps = $this->path($name);
        $last = count($steps) - 1;
        $node = &$target;

        foreach ($steps as $index => [$key, $append]) {
            if ($index === $last) {
                $this->setValue($node, $key, $append, $value);

                return;
            }

            if (! array_key_exists($key, $node) || $node[$key] === null) {
                $node[$key] = [];
            } elseif (! is_array($node[$key])) {
                $node[$key] = ['' => $node[$key]];
            }

            $node = &$node[$key];
        }
    }

    /**
     * @param  array<string|int, mixed>  $node
     */
    private function setValue(array &$node, string|int $key, bool $append, mixed $value): void
    {
        if (! array_key_exists($key, $node)) {
            $node[$key] = $append ? [$value] : $value;
        } elseif (is_array($node[$key]) && array_is_list($node[$key])) {
            $node[$key][] = $value;
        } elseif (is_array($node[$key])) {
            $this->setValue($node[$key], '', false, $value);
        } else {
            $node[$key] = [$node[$key], $value];
        }
    }

    /**
     * Split a field name into [key, appendsToList] steps: "a[b][]" => [["a", false], ["b", true]].
     * Names that do not follow the bracket syntax are used as a single key.
     *
     * @return list<array{0: string|int, 1: bool}>
     */
    private function path(string $name): array
    {
        $plain = [[$name, false]];

        if (preg_match('/^[^\[]+/', $name, $first) !== 1) {
            return $plain;
        }

        $steps = [[$first[0], false]];
        $position = strlen($first[0]);
        $length = strlen($name);

        while ($position < $length) {
            if (substr($name, $position, 2) === '[]') {
                $position += 2;
                $steps[count($steps) - 1][1] = true;

                if ($position !== $length) {
                    return $plain;
                }

                continue;
            }

            if (preg_match('/^\[([^\]]+)\]/', substr($name, $position), $segment) !== 1) {
                return $plain;
            }

            $position += strlen($segment[0]);
            $steps[] = [ctype_digit($segment[1]) ? (int) $segment[1] : $segment[1], false];
        }

        return $steps;
    }
}
