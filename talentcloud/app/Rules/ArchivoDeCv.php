<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Un CV en PDF o DOCX, comprobado por su contenido y no con `mimes`: la detección de tipo del servidor (libmagic)
 * no reconoce algunos DOCX válidos (p. ej. los de Word 2010) y los da por `application/octet-stream`. La extensión
 * decide qué se espera, como en `SubirCv`, y el contenido tiene que corresponder a ella.
 */
class ArchivoDeCv implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $extension = $value instanceof UploadedFile ? strtolower($value->getClientOriginalExtension()) : '';

        $valido = match ($extension) {
            'pdf' => $this->esPdf($value),
            'docx' => $this->esDocx($value),
            default => false,
        };

        if (! $valido) {
            $fail('validation.mimes')->translate(['values' => implode(', ', config('talentcloud.cvs.mimes'))]);
        }
    }

    private function esPdf(UploadedFile $archivo): bool
    {
        $inicio = @file_get_contents($archivo->getRealPath(), false, null, 0, 1024);

        return is_string($inicio) && str_contains($inicio, '%PDF-');
    }

    private function esDocx(UploadedFile $archivo): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($archivo->getRealPath(), ZipArchive::RDONLY) !== true) {
            return false;
        }

        $esWord = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName('word/document.xml') !== false;
        $zip->close();

        return $esWord;
    }
}
