<?php

namespace App\Services;

use App\Models\Allegato;
use App\Models\Pratica;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AllegatoService
{
    private const PRESIGNED_TTL = '+5 minutes';

    public function upload(UploadedFile $file, Pratica $pratica, ?int $categoryId = null): Allegato
    {
        $extension  = $file->getClientOriginalExtension();
        $randomName = Str::uuid() . ($extension ? '.' . $extension : '');
        $s3Key = sprintf(
            'tenant_%d/pratica_%d/%s',
            $pratica->tenant_id,
            $pratica->id,
            $randomName
        );

        Storage::disk('s3')->put($s3Key, $file->getContent(), 'private');

        return Allegato::create([
            'pratica_id'           => $pratica->id,
            'tenant_id'            => $pratica->tenant_id,
            'nome_file'            => $file->getClientOriginalName(),
            's3_key'               => $s3Key,
            'document_category_id' => $categoryId,
            'source'               => 'caricato',
        ]);
    }

    public function generatePresignedUrl(Allegato $allegato): string
    {
        $this->assertTenantOwnership($allegato);

        $s3Client = Storage::disk('s3')->getClient();

        $command = $s3Client->getCommand('GetObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key'    => $allegato->s3_key,
        ]);

        return (string) $s3Client->createPresignedRequest($command, self::PRESIGNED_TTL)->getUri();
    }

    public function delete(Allegato $allegato): void
    {
        $this->assertTenantOwnership($allegato);

        Storage::disk('s3')->delete($allegato->s3_key);
        $allegato->delete();
    }

    private function assertTenantOwnership(Allegato $allegato): void
    {
        // Il TenantScope già filtra le query, ma questo check protegge
        // le chiamate dirette al service (es. da job o comandi Artisan).
        abort_unless(
            $allegato->tenant_id === auth()->user()->tenant_id,
            403,
            'Accesso non autorizzato a questo allegato.'
        );
    }
}
