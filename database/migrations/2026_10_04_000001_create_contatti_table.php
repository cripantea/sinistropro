<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Periti e carrozzerie come anagrafica a sé: non devono essere utenti
        // della piattaforma per poter essere assegnati a un sinistro.
        Schema::create('contatti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('tipo', 20); // perito | carrozzeria
            $table->string('nome');
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'tipo']);
        });

        Schema::table('ispezioni', function (Blueprint $table) {
            $table->foreignId('perito_contatto_id')->nullable()->after('carrozzeria_user_id')
                ->constrained('contatti')->nullOnDelete();
            $table->foreignId('carrozzeria_contatto_id')->nullable()->after('perito_contatto_id')
                ->constrained('contatti')->nullOnDelete();
        });

        $this->migraUtentiEsterni();
    }

    /**
     * Gli utenti "external" esistenti diventano contatti, e le ispezioni già
     * assegnate a loro vengono ricollegate al contatto corrispondente.
     */
    private function migraUtentiEsterni(): void
    {
        $mapPerito = [];
        $mapCarrozzeria = [];

        $utenti = DB::table('users')->where('role', 'external')->whereNotNull('tenant_id')->get();

        foreach ($utenti as $u) {
            // Come i vecchi menu: senza tipo = perito. Un tipo diverso (es. "altro") non era
            // selezionabile né come perito né come carrozzeria: resta in rubrica col proprio
            // tag, senza diventare un perito assegnabile.
            $tipo = match (true) {
                $u->external_type === 'carrozzeria'                  => 'carrozzeria',
                in_array($u->external_type, [null, '', 'perito'], true) => 'perito',
                default                                                => mb_substr(mb_strtolower((string) $u->external_type), 0, 20),
            };

            $id = DB::table('contatti')->insertGetId([
                'tenant_id'  => $u->tenant_id,
                'tipo'       => $tipo,
                'nome'       => $u->name,
                'email'      => $u->email,
                'is_active'  => (bool) $u->is_active,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($tipo === 'perito') {
                $mapPerito[$u->id] = $id;
            } elseif ($tipo === 'carrozzeria') {
                $mapCarrozzeria[$u->id] = $id;
            }
        }

        // Un utente "perito" può essere stato scelto anche come carrozzeria (e viceversa)
        // dai vecchi menu: garantiamo un contatto per ogni uso.
        $contattoPer = function (int $userId, string $tipo) use (&$mapPerito, &$mapCarrozzeria, $utenti): ?int {
            $map = $tipo === 'perito' ? $mapPerito : $mapCarrozzeria;
            if (isset($map[$userId])) {
                return $map[$userId];
            }
            $u = $utenti->firstWhere('id', $userId);
            if (! $u) {
                return null;
            }
            $id = DB::table('contatti')->insertGetId([
                'tenant_id'  => $u->tenant_id,
                'tipo'       => $tipo,
                'nome'       => $u->name,
                'email'      => $u->email,
                'is_active'  => (bool) $u->is_active,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($tipo === 'perito') {
                $mapPerito[$userId] = $id;
            } else {
                $mapCarrozzeria[$userId] = $id;
            }
            return $id;
        };

        DB::table('ispezioni')
            ->where(fn ($q) => $q->whereNotNull('assegnato_a_user_id')->orWhereNotNull('carrozzeria_user_id'))
            ->get(['id', 'assegnato_a_user_id', 'carrozzeria_user_id'])
            ->each(function ($row) use ($contattoPer): void {
                $update = [];
                if ($row->assegnato_a_user_id) {
                    $update['perito_contatto_id'] = $contattoPer((int) $row->assegnato_a_user_id, 'perito');
                }
                if ($row->carrozzeria_user_id) {
                    $update['carrozzeria_contatto_id'] = $contattoPer((int) $row->carrozzeria_user_id, 'carrozzeria');
                }
                if ($update) {
                    DB::table('ispezioni')->where('id', $row->id)->update($update);
                }
            });
    }

    public function down(): void
    {
        Schema::table('ispezioni', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carrozzeria_contatto_id');
            $table->dropConstrainedForeignId('perito_contatto_id');
        });

        Schema::dropIfExists('contatti');
    }
};
