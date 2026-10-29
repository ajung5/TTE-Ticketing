<?php

namespace App\Http\Controllers;

use App\Models\WaMessageTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaTemplateController extends Controller {
    private function check(Request $request): void {
        abort_unless($request->user()?->isAdmin(), 403, 'Hanya admin yang dapat mengelola preset WhatsApp.');
    }

    public function index(Request $request) {
        $this->check($request);
        $templates = WaMessageTemplate::query()->get()->filter(function (WaMessageTemplate $template) {
            return (WaMessageTemplate::TEMPLATE_SERVICE_CODES[$template->event_type] ?? null) === $template->service_code;
        })->keyBy('event_type');
        return view('admin.wa-templates', compact('templates'));
    }

    public function update(Request $request, WaMessageTemplate $template) {
        $this->check($request);
        abort_unless(
            (WaMessageTemplate::TEMPLATE_SERVICE_CODES[$template->event_type] ?? null) === $template->service_code,
            404, 'Preset tidak aktif.'
        );
        $data = $request->validate(['body' => ['required', 'string', 'min:40', 'max:3500']]);
        $body = trim($data['body']);
        foreach (['{nama_pemohon}', '{nomor_tiket}', '{nama_verifikator}'] as $required) {
            if (!str_contains($body, $required)) {
                throw ValidationException::withMessages(['body' => "Preset harus menyertakan {$required}."]);
            }
        }
        preg_match_all('/\{[^{}]+\}/u', $body, $matches);
        $unknown = array_diff($matches[0], WaMessageTemplate::PLACEHOLDERS);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['body' => 'Placeholder tidak dikenal: '.implode(', ', array_unique($unknown))]);
        }
        DB::transaction(function () use ($template, $request, $body) {
            $template->update(['body' => $body, 'updated_by' => $request->user()->id]);
            DB::table('audit_logs')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'admin_updated_wa_template',
                'target_type' => 'wa_message_template',
                'target_id' => $template->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return redirect()->route('admin.templates.index')->with('success', 'Preset WhatsApp berhasil disimpan.');
    }
}
