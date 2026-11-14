<?php

use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Vorschläge mit UUID als Primärschlüssel (#344, Schritt 2 zu #322; DB-Standard A3): `proposals.id` wird die UUID v7
 * aus Schritt 1 (`uuid`), die fortlaufende Zahl bleibt als Anzeige «Vorschlag Nr. …» in `number` (eindeutig, öffentlicher
 * Kurzcode nach A3). `proposal_events.proposal_id` verweist auf die UUID.
 *
 * abwaertskompatibel: Schritt 2 nach U18 – die UUID gibt es seit 0.14.0 (Schritt 1), die API nutzt nur sie; die Zahl-ID
 * als Primärschlüssel entfällt eine Version später wie vorgesehen. Ein Rücksprung auf 0.14.0 braucht die Sicherung vor
 * dem Update (docker/entrypoint.sh).
 *
 * Ohne Datenverlust: beide Tabellen werden gelesen, neu angelegt und wieder befüllt (gleiche Werte, Zählung vorher und
 * nachher), in der Transaktion der Migration – bei Abweichung bricht sie ab und alles bleibt unverändert.
 */
return new class extends Migration
{
    public function up(): void
    {
        // abwaertskompatibel: Schritt 2 nach U18 zu #322 (UUID seit 0.14.0, siehe Kopf)
        $proposals = DB::table('proposals')->orderBy('id')->get()->map(fn (object $r): array => (array) $r)->all();
        $events = DB::table('proposal_events')->orderBy('id')->get()->map(fn (object $r): array => (array) $r)->all();

        $uuidOf = [];
        foreach ($proposals as $p) {
            $uuidOf[(int) $p['id']] = is_string($p['uuid'] ?? null) && $p['uuid'] !== '' ? $p['uuid'] : (string) Str::uuid7();
        }

        Schema::drop('proposal_events');
        Schema::drop('proposals');
        $this->createTables();

        foreach (array_chunk($proposals, 200) as $chunk) {
            DB::table('proposals')->insert(array_map(function (array $p) use ($uuidOf): array {
                $number = (int) $p['id'];
                unset($p['uuid']);

                return ['id' => $uuidOf[$number], 'number' => $number] + array_diff_key($p, ['id' => true]);
            }, $chunk));
        }
        foreach (array_chunk($events, 200) as $chunk) {
            DB::table('proposal_events')->insert(array_map(
                fn (array $e): array => ['proposal_id' => $uuidOf[(int) $e['proposal_id']]] + $e,
                $chunk,
            ));
        }
        if (DB::getDriverName() === 'pgsql' && $events !== []) {
            DB::statement("select setval(pg_get_serial_sequence('proposal_events', 'id'), (select max(id) from proposal_events))");
        }

        $after = [DB::table('proposals')->count(), DB::table('proposal_events')->count()];
        if ($after !== [count($proposals), count($events)]) {
            throw new RuntimeException('Vorschläge: Zählung nach der Umstellung weicht ab – abgebrochen, nichts geändert.');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Zurück auf Zahl-IDs nur über die Sicherung vor dem Update (docker/entrypoint.sh).');
    }

    private function createTables(): void
    {
        Schema::create('proposals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->unsignedInteger('number')->unique();             // Anzeige «Vorschlag Nr. …»
            $t->string('proposal_type', 10);
            $t->foreignUuid('term_id')->nullable()->index()->constrained('terms')->nullOnDelete();
            $t->unsignedInteger('base_revision')->nullable();
            $t->json('changes');
            $t->text('reason')->nullable();
            $t->string('status', 30)->default(ProposalStatus::Submitted->value)->index();
            $t->foreignUuid('author_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->foreignUuid('service_account_id')->nullable()->index()->constrained('service_accounts')->nullOnDelete();
            $t->foreignUuid('organization_id')->nullable()->index()->constrained()->nullOnDelete();
            $t->string('author_label', 120);
            $t->foreignUuid('result_term_id')->nullable()->index()->constrained('terms')->nullOnDelete();
            $t->unsignedInteger('result_revision')->nullable();
            $t->timestamp('resolved_at')->nullable()->index();
            $t->timestamps();
            $t->unsignedInteger('lock_version')->default(0);
        });
        StrictSchema::harden('proposals', [
            'proposals_proposal_type_check' => ProposalType::check('proposal_type'),
            'proposals_status_check' => ProposalStatus::check('status'),
            'proposals_number_check' => '"number" >= 1',
            'proposals_lock_version_check' => '"lock_version" >= 0',
        ]);

        Schema::create('proposal_events', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('proposal_id')->index()->constrained('proposals')->cascadeOnDelete();
            $t->string('event_type', 30);
            $t->uuid('actor_id')->nullable()->index();            // ohne Fremdschlüssel: Verlauf bleibt
            $t->string('actor_label', 120)->nullable();
            $t->boolean('is_by_author')->default(false);
            $t->text('message')->nullable();
            $t->json('details')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->uuid('event_id')->nullable()->unique();
        });
        StrictSchema::harden('proposal_events', [
            'proposal_events_event_type_check' => ProposalEventType::check('event_type'),
            'proposal_events_is_by_author_check' => '"is_by_author" in (0, 1)',
        ]);
    }
};
