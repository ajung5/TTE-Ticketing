<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('service_code', 40);
            $table->string('event_type', 20);
            $table->text('body');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['service_code', 'event_type']);
        });

        $instructions = [
            'Reset Passphrase' => [
                'followup' => 'Permohonan reset passphrase sedang ditindaklanjuti. Mohon pantau email dinas untuk instruksi resmi BSrE apabila tersedia.',
                'completed' => 'Permohonan telah dinyatakan selesai dalam sistem layanan. Silakan periksa email dinas untuk instruksi resmi reset passphrase apabila telah diterbitkan.',
            ],
            'Pembaruan' => [
                'followup' => 'Permohonan pembaruan Sertifikat Elektronik sedang ditindaklanjuti. Mohon pantau email dinas untuk langkah selanjutnya.',
                'completed' => 'Permohonan telah dinyatakan selesai dalam sistem layanan. Silakan pantau email dinas untuk pemberitahuan pembaruan Sertifikat Elektronik.',
            ],
            'Pendaftaran' => [
                'followup' => 'Permohonan pendaftaran TTE baru sedang ditindaklanjuti. Mohon pantau email dinas untuk informasi verifikasi atau aktivasi.',
                'completed' => 'Permohonan telah dinyatakan selesai dalam sistem layanan. Silakan pantau email dinas untuk informasi tahapan aktivasi TTE.',
            ],
        ];
        foreach ($instructions as $service => $events) {
            foreach ($events as $event => $instruction) {
                DB::table('wa_message_templates')->insert([
                    'service_code' => $service,
                    'event_type' => $event,
                    'body' => "Yth. Bapak/Ibu {nama_pemohon},\n\nPermohonan *{layanan}* dengan nomor tiket *{nomor_tiket}* "
                        .($event === 'completed' ? 'telah ditandai selesai.' : 'telah ditindaklanjuti oleh Tim Verifikator TTE.')
                        ."\n\n{$instruction}\n\nPetugas: {nama_verifikator} ({inisial_verifikator})\n\nTerima kasih.\n{nama_instansi}\n\n"
                        .'Jangan membagikan passphrase, password, atau OTP kepada siapa pun.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_message_templates');
    }
};
