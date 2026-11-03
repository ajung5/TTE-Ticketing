<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminTicketReportController extends Controller {
    private const TIMEZONE = 'Asia/Jakarta';
    private const STATUS_LABELS = [
        'Open' => 'Belum Diambil',
        'In Progress' => 'Diproses',
        'Done' => 'Selesai',
        'Returned' => 'Perlu Pengajuan Ulang'
    ];

    private function authorizeAdmin(Request $request): void {
        abort_unless($request->user()?->isAdmin(), 403, 'Laporan tiket hanya tersedia untuk administrator.');
    }

    /**
     * Periode inklusif berdasarkan waktu masuk (created_at) dalam WIB.
     * Batas SQL memakai UTC, dengan akhir eksklusif agar tiket pukul 23.59 tetap dihitung.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: array<string, string>, 3: string}
     */
    private function period(Request $request): array {
        $data = $request->validate([
            'mode' => ['sometimes', 'required', Rule::in(['month', 'custom'])],
            'month' => ['required_if:mode,month', 'nullable', 'date_format:Y-m'],
            'from' => ['required_if:mode,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:mode,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(array_keys(self::STATUS_LABELS))]
        ]);

        $mode = $data['mode'] ?? 'month';
        if ($mode === 'custom') {
            $from = CarbonImmutable::createFromFormat('!Y-m-d', $data['from'], self::TIMEZONE)->startOfDay();
            $to = CarbonImmutable::createFromFormat('!Y-m-d', $data['to'], self::TIMEZONE)->startOfDay();
            $filters = ['mode' => 'custom', 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')];
            $label =
                $from->locale('id')->translatedFormat('d F Y') . ' – ' . $to->locale('id')->translatedFormat('d F Y');
        } else {
            $month = $data['month'] ?? now(self::TIMEZONE)->format('Y-m');
            $from = CarbonImmutable::createFromFormat('!Y-m', $month, self::TIMEZONE)->startOfMonth();
            $to = $from->endOfMonth()->startOfDay();
            $filters = ['mode' => 'month', 'month' => $month];
            $label = $from->locale('id')->translatedFormat('F Y');
        }

        $filters['status'] = $data['status'] ?? '';

        return [$from->utc(), $to->addDay()->utc(), $filters, $label];
    }

    /** Semua perhitungan laporan utama berdasarkan tanggal tiket MASUK. */
    private function incoming(CarbonImmutable $start, CarbonImmutable $endExclusive, ?string $status = null): Builder {
        $query = Ticket::query()->where('created_at', '>=', $start)->where('created_at', '<', $endExclusive);
        // Filter status hanya untuk rincian/CSV. Rekap tetap mencakup seluruh tiket masuk periode.
        if ($status) {
            $query->where('status', $status);
        }
        return $query;
    }

    public function index(Request $request) {
        $this->authorizeAdmin($request);
        [$start, $endExclusive, $filters, $periodLabel] = $this->period($request);

        $summary = $this->incoming($start, $endExclusive)
            ->selectRaw(
                "COUNT(*) as total,
                SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) as open_count,
                SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as progress_count,
                SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) as done_count,
                SUM(CASE WHEN status = 'Returned' THEN 1 ELSE 0 END) as returned_count"
            )
            ->first();

        // Status hasil pemeriksaan terakhir untuk tiket yang MASUK pada periode.
        $amsSummary = $this->incoming($start, $endExclusive)->selectRaw(
            "COUNT(*) as total,
            SUM(CASE WHEN activation_status = 'activated' THEN 1 ELSE 0 END) as activated,
            SUM(CASE WHEN activation_status = 'not_activated' THEN 1 ELSE 0 END) as not_activated,
            SUM(CASE WHEN activation_status = 'activated' AND wa_verification_status = 'unverified' THEN 1 ELSE 0 END) as wa_unverified,
            SUM(CASE WHEN activation_status = 'activated' AND wa_verification_status = 'verified' THEN 1 ELSE 0 END) as wa_verified,
            SUM(CASE WHEN certificate_issuance_status = 'not_issued' THEN 1 ELSE 0 END) as not_issued,
            SUM(CASE WHEN certificate_issuance_status = 'issued' AND certificate_validity_status = 'active' THEN 1 ELSE 0 END) as cert_active,
            SUM(CASE WHEN certificate_issuance_status = 'issued' AND certificate_validity_status = 'expired' THEN 1 ELSE 0 END) as cert_expired"
        )->first();

        // Indikator berbeda: semua tiket yang SELESAI dalam periode,
        // walaupun diterima sebelum periode tersebut.
        $completedDuringPeriod = Ticket::query()
            ->where('status', 'Done')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $endExclusive)
            ->count();

        $byService = $this->incoming($start, $endExclusive)
            ->select('layanan')
            ->selectRaw(
                "COUNT(*) as total,
                SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) as open_count,
                SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as progress_count,
                SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) as done_count,
                SUM(CASE WHEN status = 'Returned' THEN 1 ELSE 0 END) as returned_count"
            )
            ->groupBy('layanan')
            ->orderBy('layanan')
            ->get();

        $bySource = $this->incoming($start, $endExclusive)
            ->select('source')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('source')
            ->orderBy('source')
            ->get();

        $verifierStats = $this->incoming($start, $endExclusive)
            ->whereNotNull('claimed_by')
            ->select('claimed_by')
            ->selectRaw(
                "COUNT(*) as total,
                SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as progress_count,
                SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) as done_count,
                SUM(CASE WHEN status = 'Returned' THEN 1 ELSE 0 END) as returned_count"
            )
            ->groupBy('claimed_by')
            ->orderByDesc('total')
            ->get();

        $verifiers = User::withTrashed()
            ->whereIn('id', $verifierStats->pluck('claimed_by'))
            ->get(['id', 'name', 'initials'])
            ->keyBy('id');

        $statusLabels = self::STATUS_LABELS;
        $tickets = $this->incoming($start, $endExclusive, $filters['status'])
            ->with('claimant:id,name,initials')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return response()
            ->view(
                'admin.ticket-reports',
                compact(
                    'summary',
                    'amsSummary',
                    'completedDuringPeriod',
                    'byService',
                    'bySource',
                    'verifierStats',
                    'verifiers',
                    'tickets',
                    'filters',
                    'periodLabel',
                    'statusLabels'
                )
            )
            ->header('Cache-Control', 'private, no-store');
    }

    /** CSV berisi semua baris sesuai periode dan filter status; data sensitif (NIK/WA/email) tidak diekspor. */
    public function csv(Request $request) {
        $this->authorizeAdmin($request);
        [$start, $endExclusive, $filters] = $this->period($request);

        $filename =
            'laporan-tiket-' .
            ($filters['mode'] === 'month' ? $filters['month'] : $filters['from'] . '_sampai_' . $filters['to']) .
            ($filters['status'] !== '' ? '-' . strtolower(str_replace(' ', '-', $filters['status'])) : '') .
            '.csv';

        return response()->streamDownload(
            function () use ($start, $endExclusive, $filters): void {
                $output = fopen('php://output', 'wb');
                if ($output === false) {
                    throw new \RuntimeException('Tidak dapat membuat ekspor CSV.');
                }

                // BOM mempermudah pembukaan UTF-8 dengan aplikasi spreadsheet.
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv(
                    $output,
                    [
                        'No. Tiket',
                        'Tanggal Masuk (WIB)',
                        'Nama Pemohon',
                        'Unit Kerja/OPD',
                        'UPTD',
                        'Jenis Layanan',
                        'Aktivasi Akun',
                        'Verifikasi WhatsApp',
                        'Status Sertifikat Elektronik',
                        'Sumber',
                        'Status Saat Ekspor',
                        'Verifikator',
                        'Tanggal Selesai (WIB)'
                    ],
                    ';',
                    '"',
                    ''
                );

                foreach (
                    $this->incoming($start, $endExclusive, $filters['status'])->with('claimant:id,name,initials')->lazyById(500)
                    as $ticket
                ) {
                    fputcsv(
                        $output,
                        array_map(
                            [$this, 'csvSafe'],
                            [
                                $ticket->ticket_number ?: '#' . $ticket->id,
                                $ticket->created_at->timezone(self::TIMEZONE)->format('d/m/Y H:i'),
                                $ticket->nama_user,
                                $ticket->opd ?: '-',
                                trim((string) $ticket->uptd) ?: '-',
                                $ticket->service_label,
                                $ticket->activation_label,
                                $ticket->wa_verification_label,
                                $ticket->certificate_status_label,
                                $ticket->source,
                                $ticket->status_label,
                                $ticket->claimant?->name ?? 'Belum ditangani',
                                $ticket->status === 'Done'
                                    ? ($ticket->completed_at?->timezone(self::TIMEZONE)->format('d/m/Y H:i') ?? '-')
                                    : '-'
                            ]
                        ),
                        ';',
                        '"',
                        ''
                    );
                }
                fclose($output);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff'
            ]
        );
    }

    /** Cegah formula injection saat CSV dibuka melalui aplikasi spreadsheet. */
    private function csvSafe(mixed $value): string {
        $text = (string) $value;
        return preg_match('/^\s*[=+\-@]/u', $text) || preg_match('/^[\t\r\n]/u', $text) ? "'" . $text : $text;
    }
}
