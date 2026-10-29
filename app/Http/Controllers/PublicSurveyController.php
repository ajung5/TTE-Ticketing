<?php

namespace App\Http\Controllers;

use App\Models\ServiceSurveyResponse;
use App\Models\Ticket;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class PublicSurveyController extends Controller {
    public function show(Ticket $ticket) {
        abort_unless($ticket->status === 'Done', 404);
        $alreadySubmitted = ServiceSurveyResponse::where('ticket_id', $ticket->id)->exists();

        return response()->view('public.survey', compact('ticket', 'alreadySubmitted'))
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request, Ticket $ticket) {
        abort_unless($ticket->status === 'Done', 404);
        $data = $request->validate([
            'respondent_name' => ['required', 'string', 'min:3', 'max:160'],
            'overall_rating' => ['required', 'integer', 'between:1,5'],
            'staff_rating' => ['required', 'integer', 'between:1,5'],
            'clarity_rating' => ['required', 'integer', 'between:1,5'],
            'speed_rating' => ['required', 'integer', 'between:1,5'],
            'feedback' => ['nullable', 'string', 'max:1000'],
            'website' => ['prohibited'],
        ]);
        if (ServiceSurveyResponse::where('ticket_id', $ticket->id)->exists()) {
            return redirect()->route('public.survey.thanks');
        }
        try {
            ServiceSurveyResponse::create([
                'ticket_id' => $ticket->id,
                'respondent_name' => trim($data['respondent_name']),
                'overall_rating' => $data['overall_rating'],
                'staff_rating' => $data['staff_rating'],
                'clarity_rating' => $data['clarity_rating'],
                'speed_rating' => $data['speed_rating'],
                'feedback' => trim((string) ($data['feedback'] ?? '')) ?: null,
            ]);
        } catch (QueryException $e) {
            // Unique(ticket_id) menangani dua submit bersamaan. Error database lain tetap diteruskan.
            if (!ServiceSurveyResponse::where('ticket_id', $ticket->id)->exists()) {
                throw $e;
            }
        }
        return redirect()->route('public.survey.thanks');
    }

    public function thanks() {
        return response()->view('public.survey-thanks')
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
