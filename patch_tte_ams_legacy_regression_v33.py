#!/usr/bin/env python3
"""TTE-Ticketing v33: migrasi skenario regression test AMS v16-v25 ke kontrak v27.

Jalankan dari root repository setelah patch v31:
  python3 patch_tte_ams_legacy_regression_v33.py --check
  python3 patch_tte_ams_legacy_regression_v33.py
  php artisan test

Fokus: revisi test yang secara historis mengizinkan aktivasi tidak diperiksa,
menyamakan 'issued' dengan 'active', atau otomatis mengembalikan tiket belum aktivasi.
TIDAK mengubah source aplikasi, middleware, database, migration, seeder, atau data.
Semua perubahan diperiksa dahulu, lalu ditulis atomik dengan rollback jika gagal.
File patch menghapus dirinya hanya setelah pemasangan berhasil.
"""
from __future__ import annotations
import argparse
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--check', action='store_true', help='Validasi semua anchor tanpa menulis apa pun')
args = parser.parse_args()
ROOT = Path.cwd().resolve()
if not (ROOT / 'artisan').is_file() or not (ROOT / 'tests/Feature/AmsSequentialVerificationV27Test.php').is_file():
    sys.exit('ERROR: jalankan dari root TTE-Ticketing SETELAH AMS v27 terpasang.')
original: dict[str,str] = {}
changes: dict[str,str] = {}

def get(rel: str) -> str:
    if rel not in original:
        path = ROOT / rel
        if not path.is_file(): raise RuntimeError('Berkas wajib hilang: ' + rel)
        original[rel] = path.read_text(encoding='utf-8')
        changes[rel] = original[rel]
    return changes[rel]

def replace(rel: str, old: str, new: str) -> None:
    s = get(rel)
    n = s.count(old)
    if n != 1:
        raise RuntimeError(f'Anchor tidak cocok: {rel} ({n} temuan; seharusnya 1). Semua perubahan dibatalkan.')
    changes[rel] = s.replace(old, new, 1)

def replace_in(rel: str, test_start: str, old: str, new: str) -> None:
    s = get(rel)
    begin = s.find(test_start)
    if begin < 0: raise RuntimeError(f'Bagian test tidak ditemukan: {rel}: {test_start[:70]}')
    next_test = s.find('\nit(', begin + len(test_start))
    end = next_test if next_test >= 0 else len(s)
    fragment = s[begin:end]
    n = fragment.count(old)
    if n != 1: raise RuntimeError(f'Anchor dalam test tidak cocok: {rel}: {test_start[:55]}, {n} temuan.')
    changes[rel] = s[:begin] + fragment.replace(old, new, 1) + s[end:]

def rewrite_test(rel: str, start: str, stop: str, replacement: str):
    s = get(rel)
    if s.count(start) != 1 or s.count(stop) != 1:
        raise RuntimeError(f'Batas test berubah pada {rel}; update tidak aman.')
    i = s.index(start)
    j = s.index(stop, i + len(start))
    changes[rel] = s[:i] + replacement.rstrip() + '\n\n' + s[j:]

# 1) v25: sejak v27 status masa berlaku wajib dicatat bila SE sudah terbit.
p='tests/Feature/ActivatedPendingIssuanceV25Test.php'
replace_in(p, "it('finishes an activated pending issuance", "'wa_verification_status' => 'unverified', 'activation_status' => 'activated',\n        'certificate_issuance_status' => 'issued',\n    ])->assertSessionHasNoErrors();", "'wa_verification_status' => 'unverified', 'activation_status' => 'activated',\n        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',\n    ])->assertSessionHasNoErrors();")
replace_in(p, "it('finishes an activated pending issuance", "'wa_verification_status' => 'verified', 'certificate_issuance_status' => 'issued',\n    ])->assertSessionHasNoErrors();", "'wa_verification_status' => 'verified', 'activation_status' => 'activated',\n        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',\n    ])->assertSessionHasNoErrors();")
replace_in(p, "it('keeps completion blocked when WA becomes verified", "'wa_verification_status' => 'verified', 'certificate_issuance_status' => 'not_issued',", "'wa_verification_status' => 'verified', 'activation_status' => 'activated',\n        'certificate_issuance_status' => 'not_issued',")

# 2) v18: tiga tahap eksplisit; data historis 'issued' tidak berarti 'active'.
p='tests/Feature/AmsCertificateIssuanceV18Test.php'
replace_in(p,"it('requires explicit certificate issuance", "'wa_verification_status' => 'verified',", "'activation_status' => 'activated', 'wa_verification_status' => 'verified',")
replace_in(p, "'continues issuance and blocks completion", "'activation_status' => 'not_activated', // Ignored: not part of the verified-WA branch.", "'activation_status' => 'activated', // Aktivasi eksplisit wajib meski WA verified.")
replace_in(p, "'continues issuance and blocks completion", "expect($ticket->fresh()->activation_status)->toBeNull();", "expect($ticket->fresh()->activation_status)->toBe('activated');")
replace_in(p, "'prevents a verified-WA Pendaftaran", "'wa_verification_status' => 'verified',", "'activation_status' => 'activated', 'wa_verification_status' => 'verified',")
replace_in(p, "it('switches from SE Belum Terbit", "'certificate_issuance_status' => 'not_issued',", "'activation_status' => 'activated',\n            'certificate_issuance_status' => 'not_issued',")
replace_in(p, "it('switches from SE Belum Terbit", "'certificate_issuance_status' => 'issued',", "'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',")
replace(p, "expect($ticket->fresh()->certificate_status)->toBe('active');", "expect($ticket->fresh()->certificate_status)->toBe('active');\n    expect($ticket->fresh()->certificate_validity_status)->toBe('active');")

# 3) v17: tombol Selesai menuntut aktivasi + WA verified + SE active.
p='tests/Feature/AmsCompletionGateV17Test.php'
replace_in(p,"'enables completion as soon as", "'wa_verification_status' => 'verified',", "'activation_status' => 'activated', 'wa_verification_status' => 'verified',")
replace_in(p,"'enables completion as soon as", "'certificate_issuance_status' => 'issued',", "'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',")
replace_in(p,"'keeps completion disabled after the WA", "'activation_status' => 'activated',\n                'certificate_issuance_status' => 'issued',", "'activation_status' => 'activated',\n                'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',")
replace_in(p,"'keeps completion disabled after the WA", "'wa_verification_status' => 'verified',\n                'certificate_issuance_status' => 'issued',", "'wa_verification_status' => 'verified', 'activation_status' => 'activated',\n                'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',")

# 4) v16: perubahan substansial: jangan lagi mengasumsikan WA membuktikan aktivasi.
p='tests/Feature/AmsDropdownDecisionV16Test.php'
rewrite_test(p, "it(\n    'skips activation but requires issuance when WA is verified before selecting reset',", "it('refuses an incomplete unverified WA inspection'", '''it('requires activation and validity even when WA is verified before selecting reset', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV16Ticket($verifier);
    $this->actingAs($verifier)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified', 'activation_status' => 'not_activated',
        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',
    ])->assertSessionHasErrors('activation_status');
    expect($ticket->fresh()->wa_checked_at)->toBeNull();
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated', 'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->activation_status)->toBe('activated');
    expect($ticket->fresh()->certificate_validity_status)->toBe('active');
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', [
        'ticket_id' => $ticket->id, 'event_type' => 'reset',
    ]);
});''')
rewrite_test(p, "it('returns a mismatched reset request administratively and never marks it Done',", "it(\n    'requires an explicit service recommendation", '''it('keeps an unactivated account on the same ticket without returning it automatically', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV16Ticket($verifier);
    $this->actingAs($verifier)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'not_activated',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->recommended_service)->toBeNull();
    expect($ticket->fresh()->certificate_issuance_status)->toBeNull();
    $this->actingAs($verifier)->get(route('tickets.show', $ticket))
        ->assertSee('Aktivasi Akun Diperlukan')->assertDontSee('Kembalikan Permohonan');
    $this->actingAs($verifier)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', [
        'ticket_id' => $ticket->id, 'event_type' => 'activation_required',
    ]);
    $this->actingAs($verifier)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->actingAs($verifier)->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    $this->actingAs($verifier)->post(route('tickets.complete', $ticket))->assertStatus(409);
    expect($ticket->fresh()->status)->toBe('In Progress');
});''')
replace_in(p,"'requires an explicit service recommendation", "'wa_verification_status' => 'verified',\n                'certificate_issuance_status' => 'issued',\n            ])", "'activation_status' => 'activated', 'wa_verification_status' => 'verified',\n                'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',\n            ])")
replace_in(p,"'requires an explicit service recommendation", "'wa_verification_status' => 'verified',\n                'certificate_issuance_status' => 'issued',\n                'recommended_service'", "'activation_status' => 'activated', 'wa_verification_status' => 'verified',\n                'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',\n                'recommended_service'")
replace_in(p,"it('preserves legitimate issuance flow", "'wa_verification_status' => 'verified',\n            'certificate_issuance_status' => 'issued',", "'activation_status' => 'activated', 'wa_verification_status' => 'verified',\n            'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',")

# 5) v19: setelah set passphrase, 'issued' juga memerlukan pemeriksaan active.
p='tests/Feature/AmsIssuanceCompletionV19Test.php'
for start in ["it('shows SE Belum Terbit first", "it('does not skip ordinary service"]:
    replace_in(p,start, "'wa_verification_status' => 'verified',\n        'certificate_issuance_status' => 'not_issued',", "'activation_status' => 'activated', 'wa_verification_status' => 'verified',\n        'certificate_issuance_status' => 'not_issued',")
replace_in(p,"it('keeps completion locked", "'wa_verification_status' => 'verified',", "'activation_status' => 'activated', 'wa_verification_status' => 'verified',")
replace_in(p,"it('shows SE Belum Terbit first", "'certificate_issuance_status' => 'issued',", "'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',")
replace_in(p,"it('does not skip ordinary service", "'wa_verification_status' => 'verified',\n        'certificate_issuance_status' => 'issued',", "'activation_status' => 'activated', 'wa_verification_status' => 'verified',\n        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',")

# 6) v24: cross-service correction sesudah WA verified; case unverified dicatat tanpa premature recommendation.
p='tests/Feature/AmsServiceCorrectionV24Test.php'
replace_in(p,"it('allows completion after confirmed", "'wa_verification_status' => 'verified', 'certificate_issuance_status' => 'issued',", "'activation_status' => 'activated', 'wa_verification_status' => 'verified',\n        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',")
replace_in(p,"it('allows an explicit reset-renewal", "$inspection = ['wa_verification_status' => $wa, 'activation_status' => 'activated', 'certificate_issuance_status' => 'issued'];", '''$inspection = ['wa_verification_status' => 'verified', 'activation_status' => 'activated',
        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active'];
    if ($wa === 'unverified') {
        // Saat WA belum verified, jangan menerbitkan koreksi layanan sebelum verifikasi.
        $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
            'wa_verification_status' => 'unverified', 'activation_status' => 'activated',
            'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',
            'recommended_service' => $recommended,
        ])->assertRedirect()->assertSessionHasNoErrors();
        expect($ticket->fresh()->recommended_service)->toBeNull();
        $this->get(route('tickets.show', $ticket))->assertSee('Nomor WA Belum Terverifikasi');
    }''')
replace_in(p,"it('rejects a recommendation equal", "'wa_verification_status' => 'verified', 'certificate_issuance_status' => 'issued',", "'activation_status' => 'activated', 'wa_verification_status' => 'verified',\n        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',")

# 7) Inbox: all six decision types now require activation stage and issuance/validity status.
p='tests/Feature/InboxReminderWhatsAppTest.php'
replace_in(p,"it('prevents sending or completion", "'wa_verification_status' => '',", "'activation_status' => 'activated', 'wa_verification_status' => '',")
replace_in(p,"it('prevents sending or completion", "->assertSee('Verifikasi Akun TTE dan Nomor WA')", "->assertSee('Pemeriksaan Akun, WhatsApp, dan Sertifikat')")
replace_in(p,"'uses certificate status before WA", "['Pendaftaran', 'inactive', 'unverified', 'unregistered'],", "['Pendaftaran', 'inactive', 'unverified', 'activation_required'],")
replace_in(p,"'uses certificate status before WA", "['Pembaruan', 'inactive', 'unverified', 'service_mismatch'],", "['Pembaruan', 'inactive', 'unverified', 'certificate_not_issued'],")
replace_in(p,"'uses certificate status before WA", '''            $this->actingAs($owner)
                ->post(route('tickets.verify-whatsapp', $ticket), [
                    'certificate_status' => $certificate,
                    'wa_verification_status' => $wa,
                    'certificate_issuance_status' => $wa === 'verified' ? 'issued' : null,
                    'recommended_service' => 'Reset Passphrase',
                ])
                ->assertRedirect();''', '''            $activation = $expected === 'activation_required' ? 'not_activated' : 'activated';
            $inspection = ['activation_status' => $activation];
            if ($activation === 'activated') {
                $inspection['wa_verification_status'] = $wa;
                $inspection['certificate_issuance_status'] = $expected === 'certificate_not_issued' ? 'not_issued' : 'issued';
                if ($inspection['certificate_issuance_status'] === 'issued') {
                    $inspection['certificate_validity_status'] = 'active';
                }
                if ($expected === 'service_mismatch') {
                    $inspection['recommended_service'] = 'Reset Passphrase';
                }
            }
            $this->actingAs($owner)
                ->post(route('tickets.verify-whatsapp', $ticket), $inspection)
                ->assertRedirect()->assertSessionHasNoErrors();''')
replace_in(p,"'allows optional WhatsApp confirmation", "'certificate_status' => 'active',", "'certificate_status' => 'active',\n                'activation_status' => 'activated', 'certificate_validity_status' => 'active',")
replace_in(p,"it('blocks other verifiers and simulated", "'certificate_status' => 'inactive',\n            'wa_verification_status' => 'unverified',", "'activation_status' => 'activated', 'wa_verification_status' => 'unverified',\n            'certificate_issuance_status' => 'not_issued',")
replace_in(p,"it('blocks other verifiers and simulated", "->assertSee('Perbaikan Jenis Permohonan')", "->assertSee('SE Belum Terbit')")
replace_in(p,"it('revokes old confirmation after", "'certificate_status' => 'active',\n            'wa_verification_status' => 'verified',", "'certificate_status' => 'active',\n            'activation_status' => 'activated', 'certificate_validity_status' => 'active',\n            'wa_verification_status' => 'verified',")
replace_in(p,"it('revokes old confirmation after", "'wa_verification_status' => 'unverified',", "'activation_status' => 'activated', 'wa_verification_status' => 'unverified',\n            'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',")

# 8) Public end-to-end: verified and active must be explicit on reinspection.
p='tests/Feature/PublicRequestAndAmsVerificationTest.php'
replace_in(p,"it('selects WA-unverified notice", "'certificate_status' => 'active',\n            'wa_verification_status' => 'unverified',", "'certificate_status' => 'active',\n            'activation_status' => 'activated', 'certificate_issuance_status' => 'issued',\n            'certificate_validity_status' => 'active',\n            'wa_verification_status' => 'unverified',")
replace_in(p,"it('selects WA-unverified notice", "'wa_verification_status' => 'verified',\n            'certificate_issuance_status' => 'issued',", "'activation_status' => 'activated', 'wa_verification_status' => 'verified',\n            'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',")

# 9) V21 return action requires an actual cross-service mismatch, NOT merely unactivated account.
p='tests/Feature/ReturnForCorrectionV21Test.php'
rewrite_test(p,"it('requires confirmed correction notification before a separate return action'", "it('blocks non-owners and administrators from returning a ticket'", '''it('requires confirmed cross-service correction before a separate return action', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = v21Ticket($owner, 'Pendaftaran');
    $number = $ticket->ticket_number;
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated', 'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',
        'recommended_service' => 'Pembaruan',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $response = $this->actingAs($owner)->get(route('tickets.show', $ticket))->assertOk();
    expect(v21ReturnButtonAttributes($response->getContent(), $ticket))->toContain('disabled');
    $response->assertSee('Kembalikan Permohonan')->assertDontSee('Tandai Selesai');
    $this->actingAs($owner)->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    $this->actingAs($owner)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertStatus(409);
    $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    $this->actingAs($owner)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('In Progress');
    expect($ticket->fresh()->ticket_number)->toBe($number);
    $html = $this->actingAs($owner)->get(route('tickets.show', $ticket))->assertOk()->getContent();
    expect(v21ReturnButtonAttributes($html, $ticket))->not->toContain('disabled');
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->actingAs($owner)->post(route('tickets.return-for-correction', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Returned');
    expect($ticket->fresh()->ticket_number)->toBe($number);
    expect($ticket->fresh()->completed_at)->toBeNull();
    $this->assertDatabaseHas('ticket_activities', ['ticket_id' => $ticket->id, 'action' => 'returned_for_correction']);
    $this->actingAs($owner)->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('Perlu Pengajuan Ulang')->assertDontSee('completed-wa-message');
    $this->actingAs($owner)->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    expect(TicketActivity::where('ticket_id', $ticket->id)
        ->where('action', 'returned_for_correction')->count())->toBe(1);
    $this->actingAs($owner)->post(route('tickets.complete', $ticket))->assertStatus(409);
});''')
rewrite_test(p,"it('prevents returning when AMS outcome changes after a previously confirmed correction notice'", "it('returns an incorrect Pendaftaran ticket only after choosing a corrected service'", '''it('prevents returning when AMS outcome changes after a previously confirmed correction notice', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = v21Ticket($owner, 'Pendaftaran');
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated', 'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',
        'recommended_service' => 'Pembaruan',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated', 'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'not_issued',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($owner)->post(route('tickets.return-for-correction', $ticket))->assertStatus(409);
    expect($ticket->fresh()->status)->toBe('In Progress');
    $this->assertDatabaseHas('wa_notifications', [
        'ticket_id' => $ticket->id, 'event_type' => 'service_mismatch', 'confirmed_at' => null,
    ]);
});''')
replace_in(p,"it('returns an incorrect Pendaftaran ticket", "'wa_verification_status' => 'verified',", "'activation_status' => 'activated', 'wa_verification_status' => 'verified',")
replace_in(p,"it('returns an incorrect Pendaftaran ticket", "'certificate_issuance_status' => 'issued',", "'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',")

# 10) TTE CRUD + WhatsApp links: no bypassing the v27 AMS gates.
p='tests/Feature/TteManagementTest.php'
replace_in(p,"it('allows completion without a resolution", "'certificate_status' => 'active',", "'certificate_status' => 'active', 'activation_status' => 'activated',\n            'certificate_validity_status' => 'active',")
p='tests/Feature/WhatsAppWebLinkTest.php'
replace_in(p,"it('opens service and completion", "'certificate_status' => 'active',", "'certificate_status' => 'active', 'activation_status' => 'activated',\n            'certificate_validity_status' => 'active',")

changed = {rel: value for rel,value in changes.items() if original[rel] != value}
print(f'PREFLIGHT OK: {len(changed)} berkas test legacy akan dimigrasikan, tanpa perubahan source aplikasi.')
for rel in changed: print('  - ' + rel)
if not changed:
    print('Tidak ada perubahan.'); sys.exit(0)
if args.check:
    print('Mode --check: tidak ada file ditulis, patch tidak dihapus.')
    sys.exit(0)

stage_dir=Path(tempfile.mkdtemp(prefix='.tte-test-v33-',dir=ROOT))
written: list[str]=[]
backups: dict[str,bytes]={}
try:
    for i,(rel,value) in enumerate(changed.items()):
        tmp=stage_dir / f'lint-{i}.php'
        tmp.write_text(value,encoding='utf-8')
        if shutil.which('php'):
            proc=subprocess.run(['php','-l',str(tmp)],capture_output=True,text=True)
            if proc.returncode:
                raise RuntimeError(f'PHP lint GAGAL pada {rel}: {proc.stdout}\n{proc.stderr}')
    for rel in changed:
        if (ROOT/rel).read_text(encoding='utf-8') != original[rel]:
            raise RuntimeError('Berkas berubah selama proses: ' + rel)
    for rel,value in changed.items():
        path=ROOT/rel
        backups[rel]=path.read_bytes()
        tmp=path.with_name(path.name+'.tte-v33-pending')
        tmp.write_text(value,encoding='utf-8')
        os.chmod(tmp,path.stat().st_mode)
        os.replace(tmp,path)
        written.append(rel)
except Exception as exc:
    for rel in reversed(written):
        (ROOT/rel).write_bytes(backups[rel])
    sys.exit('ERROR: '+str(exc)+'\nSeluruh perubahan ditarik kembali. File patch tetap tersedia.')
finally:
    shutil.rmtree(stage_dir, ignore_errors=True)

print('PATCH v33 BERHASIL: hanya legacy feature tests yang disesuaikan dengan kontrak AMS v27.')
print('Jalankan: php artisan test (full suite)')
print('Tidak ada test yang dihapus, dilewati dengan skip, atau assertion dilonggarkan secara global.')
try:
    Path(__file__).resolve().unlink()
    print('File patch v33 otomatis dihapus setelah sukses.')
except OSError as exc: print('PERINGATAN: gagal hapus patch:',exc)
