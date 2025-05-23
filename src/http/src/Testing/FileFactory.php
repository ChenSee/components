<?php

declare(strict_types=1);

namespace Hypervel\Http\Testing;

use LogicException;

class FileFactory
{
    /**
     * Create a new fake file.
     */
    public function create(string $name, int|string $kilobytes = 0, ?string $mimeType = null): File
    {
        if (is_string($kilobytes)) {
            return $this->createWithContent($name, $kilobytes);
        }

        return tap(new File($name, tmpfile()), function ($file) use ($kilobytes, $mimeType) {
            $file->sizeToReport = $kilobytes * 1024;
            $file->mimeTypeToReport = $mimeType;
        });
    }

    /**
     * Create a new fake file with content.
     */
    public function createWithContent(string $name, string $content): File
    {
        $tmpfile = tmpfile();

        fwrite($tmpfile, $content);

        return tap(new File($name, $tmpfile), function ($file) use ($tmpfile) {
            $file->sizeToReport = fstat($tmpfile)['size'];
        });
    }

    /**
     * Create a new fake image.
     *
     * @throws LogicException
     */
    public function image(string $name, int $width = 10, int $height = 10): File
    {
        return new File($name, $this->generateImage(
            $width,
            $height,
            pathinfo($name, PATHINFO_EXTENSION)
        ));
    }

    /**
     * Generate a dummy image of the given width and height.
     *
     * @return resource
     *
     * @throws LogicException
     */
    protected function generateImage(int $width, int $height, string $extension)
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new LogicException('GD extension is not installed.');
        }

        return tap(tmpfile(), function ($temp) use ($width, $height, $extension) {
            ob_start();

            $extension = in_array($extension, ['jpeg', 'png', 'gif', 'webp', 'wbmp', 'bmp'])
                ? strtolower($extension)
                : 'jpeg';

            $image = imagecreatetruecolor($width, $height);

            if (! function_exists($functionName = "image{$extension}")) {
                ob_get_clean();

                throw new LogicException("{$functionName} function is not defined and image cannot be generated.");
            }

            call_user_func($functionName, $image);

            fwrite($temp, ob_get_clean());
        });
    }
}
